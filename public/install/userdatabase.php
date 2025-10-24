<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


ini_set("display_errors", "no");

use app\conf\App;
use app\conf\Connection as ConnConf;
use app\models\Database;
use app\migration\Sql;

include("../../app/inc/Model.php");
include("../../app/inc/Connection.php");
include("../../app/models/Database.php");
include("../../app/conf/App.php");
include("../../app/conf/Connection.php");
include("../../app/migration/Sql.php");

new App();

$messages = [];
$errors = [];
$needsElevated = false;

// Helper to detect SQLSTATE in exception code or message
if (!function_exists('exceptionHasSqlState')) {
    function exceptionHasSqlState($e, string $state): bool
    {
        try { $code = (string)$e->getCode(); } catch (\Throwable) { $code = ''; }
        try { $msg = (string)$e->getMessage(); } catch (\Throwable) { $msg = ''; }
        return stripos($code, $state) !== false || stripos($msg, $state) !== false;
    }
}

// Allow override of Postgres user/pw via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['pg_user'])) {
        ConnConf::$param['postgisuser'] = trim((string)$_POST['pg_user']);
    }
    if (isset($_POST['pg_password'])) {
        ConnConf::$param['postgispw'] = (string)$_POST['pg_password'];
    }
}

// Step 1: Connect to cluster default database (e.g., 'postgres')
$clusterConnected = false;
try {
    $cluster = new \app\inc\Model();
    $cluster->connect();
    $clusterConnected = true;
    $messages[] = "Connected to cluster on host '" . (ConnConf::$param['postgishost'] ?? '') . "' as user '" . (ConnConf::$param['postgisuser'] ?? '') . "'";
} catch (\Throwable $e) {
    $errors[] = "Connection to cluster failed: " . $e->getMessage();
}

// Prepare paths/SQL
$createDbSql = @file_get_contents(__DIR__ . "/sql/createUserDatabase.sql");
$userTableSql = @file_get_contents(__DIR__ . "/sql/createUserTable.sql");

$mapcentiaExists = false;

if ($clusterConnected) {
    try {
        $dbList = new Database();
        $info = $dbList->doesDbExist('mapcentia');
        $mapcentiaExists = (bool)($info['success'] ?? false);
    } catch (\Throwable $e) {
        // If check fails, continue and attempt creation anyway
    }

    if (!$mapcentiaExists) {
        // Try create database (must not be in transaction)
        try {
            if ($createDbSql) {
                $cluster->execQuery($createDbSql, 'PG');
            } else {
                $cluster->execQuery("CREATE DATABASE mapcentia WITH ENCODING='UTF8' TEMPLATE=template0 CONNECTION LIMIT=-1", 'PG');
            }
            $messages[] = "Database 'mapcentia' created.";
            $mapcentiaExists = true;
        } catch (\Throwable $e) {
            if (exceptionHasSqlState($e, '42P04')) { // duplicate_database
                $messages[] = "Database 'mapcentia' already exists (SQLSTATE 42P04).";
                $mapcentiaExists = true; // treat as exists and continue
            } elseif (exceptionHasSqlState($e, '42501')) { // insufficient_privilege
                $needsElevated = true;
                $errors[] = "Failed to create database 'mapcentia' due to insufficient privileges (SQLSTATE 42501). Provide elevated credentials below.";
            } else {
                $needsElevated = true;
                $errors[] = "Failed to create database 'mapcentia': " . $e->getMessage();
            }
        }
    } else {
        $messages[] = "Database 'mapcentia' already exists.";
    }
}

// Step 2: If DB exists, connect to it and run migrations
$migrationsRun = false;
if ($mapcentiaExists) {
    try {
        Database::setDb('mapcentia');
        $db = new \app\inc\Model();
        $db->connect();
        $messages[] = "Connected to database 'mapcentia'.";

        echo "<!doctype html>\n";
        echo "<html lang='en'>\n<head>\n<meta charset='utf-8'>\n<meta name='viewport' content='width=device-width, initial-scale=1'>\n<title>User database setup</title>\n<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet' integrity='sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH' crossorigin='anonymous'>\n<script>document.documentElement.setAttribute('data-bs-theme', (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'))</script>\n</head>\n<body class='d-flex flex-column min-vh-100'>\n<main class='container py-4 flex-grow-1'>\n<h1 class='mb-3'>User database setup</h1>\n<p class='text-muted'>This tool creates the required mapcentia database (if missing) and applies migrations. If the default credentials from app/conf/Connection.php do not work, enter a PostgreSQL superuser and retry.</p>\n";
        foreach ($messages as $m) { echo "<div class='alert alert-success' role='alert'>" . htmlspecialchars($m) . "</div>\n"; }
        foreach ($errors as $e) { echo "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($e) . "</div>\n"; }

        echo "<div class='card mb-4'><div class='card-header'>Migration progress</div><div class='card-body'>";

        // Ensure users table exists
        echo "<div class='mb-2'>Ensuring users table: ";
        try {
            if ($userTableSql) {
                $db->execQuery($userTableSql, 'PDO', 'transaction');
            } else {
                $db->execQuery("CREATE TABLE IF NOT EXISTS users (screenname varchar(255), pw varchar(255), email varchar(255), zone varchar, parentdb varchar(255), created timestamptz default now())", 'PDO', 'transaction');
            }
            echo "<span class='badge text-bg-success ms-2'>OK</span>";
        } catch (\PDOException $e) {
            // If already exists or similar, mark as skip
            echo "<span class='badge text-bg-secondary ms-2'>SKIP</span>";
        }
        echo "</div>";

        // Run application-defined migrations for mapcentia
        echo "<div class='mb-2'>Running migrations: ";
        $sqls = Sql::mapcentia();
        $i = 0;
        foreach ($sqls as $s) {
            try {
                $db->execQuery($s, 'PDO', 'transaction');
                echo "<span class='badge text-bg-success me-1'>OK</span>";
            } catch (\PDOException $e) {
                echo "<span class='badge text-bg-secondary me-1'>SKIP</span>";
            }
            $i++;
        }
        echo "</div>";

        echo "<div class='alert alert-success mt-3'>Migrations completed for 'mapcentia'.</div>";
        echo "<div class='mt-3'><a class='btn btn-success' href='index.php'>Back to overview</a></div>";

        // If elevation was needed earlier but DB exists now, still show form so user can retry if something failed before
        if ($needsElevated) {
            echo "<div class='mt-4'><div class='card border-warning'><div class='card-header bg-warning-subtle'>Elevated privileges may be required</div><div class='card-body'><p class='mb-3'>Some operations might have failed due to insufficient privileges. Provide a PostgreSQL user with rights to create databases and retry.</p><form method='post' action='userdatabase.php'><div class='row g-3 align-items-end'><div class='col-md-4'><label for='pg_user2' class='form-label'>PostgreSQL user</label><input type='text' class='form-control' id='pg_user2' name='pg_user' value='" . htmlspecialchars((string)(ConnConf::$param['postgisuser'] ?? '')) . "' required></div><div class='col-md-4'><label for='pg_password2' class='form-label'>Password</label><input type='password' class='form-control' id='pg_password2' name='pg_password' value='" . htmlspecialchars((string)(ConnConf::$param['postgispw'] ?? '')) . "'></div><div class='col-md-4'><button type='submit' class='btn btn-primary'>Retry with elevated credentials</button></div></div></form></div></div></div>";
        }

        echo "</div></div></main></body></html>";
        $migrationsRun = true;
        return; // We already output the page
    } catch (\Throwable $e) {
        if (exceptionHasSqlState($e, '08006')) {
            $needsElevated = true;
            $errors[] = "Could not connect to 'mapcentia' (connection failure, SQLSTATE 08006): " . $e->getMessage() . " Provide elevated PostgreSQL credentials below.";
        } else {
            $errors[] = "Could not connect to 'mapcentia': " . $e->getMessage();
        }
    }
}

// Fallback: render page when not connected or creation failed
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User database setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script>
        document.documentElement.setAttribute('data-bs-theme', (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'))
    </script>
</head>
<body class="d-flex flex-column min-vh-100">
<main class="container py-4 flex-grow-1">
    <h1 class="mb-3">User database setup</h1>
    <p class="text-muted">This tool creates the required mapcentia database (if missing) and applies migrations. If the default credentials from app/conf/Connection.php do not work, enter a PostgreSQL superuser and retry.</p>

    <?php foreach ($messages as $m): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if (!$clusterConnected || $needsElevated): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning-subtle">PostgreSQL Credentials</div>
            <div class="card-body">
                <p class="mb-3">The operation requires additional privileges or the connection failed. Provide a PostgreSQL user with rights to create databases, then submit to retry.</p>
                <form method="post" action="userdatabase.php">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="pg_user" class="form-label">PostgreSQL user</label>
                            <input type="text" class="form-control" id="pg_user" name="pg_user" value="<?= htmlspecialchars((string)(ConnConf::$param['postgisuser'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="pg_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="pg_password" name="pg_password" value="<?= htmlspecialchars((string)(ConnConf::$param['postgispw'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">Retry</button>
                            <a class="btn btn-secondary" href="index.php">Back</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-header">Status</div>
            <div class="card-body">
                <p>Database 'mapcentia' exists. Click the button below to run migrations.</p>
                <form method="post" action="user.php">
                    <button type="submit" class="btn btn-primary">Run migrations</button>
                    <a class="btn btn-secondary" href="index.php">Back</a>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

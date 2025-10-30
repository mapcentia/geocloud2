<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


use app\conf\App;
use app\inc\Cache;
use app\inc\Connection;
use app\migration\Sql;
use app\models\Database;

ini_set("display_errors", "no");

include('../../app/vendor/autoload.php');
include("../../app/inc/Connection.php");
include("../../app/inc/Model.php");
include("../../app/inc/Cache.php");
include("../../app/inc/Util.php");
include("../../app/models/Database.php");
include("../../app/migration/Sql.php");
include("../../app/conf/App.php");
include("../../app/conf/Connection.php");
include("../../app/models/User.php");
include("../../app/models/Setting.php");
include("../../app/exceptions/GC2Exception.php");

new App();
Cache::setInstance();

// sql.php defines $sql for creating the GC2 settings schema
$schemaSql = file_get_contents(__DIR__ . "/sql/createSettings.sql");


$messages = [];
$errors = [];
$dbName = $_GET['db'] ?? null;
$needsElevated = false;

// Helper to detect SQLSTATE in exception code or message
if (!function_exists('exceptionHasSqlState')) {
    function exceptionHasSqlState($e, string $state): bool
    {
        try {
            $code = (string)$e->getCode();
        } catch (\Throwable) {
            $code = '';
        }
        try {
            $msg = (string)$e->getMessage();
        } catch (\Throwable) {
            $msg = '';
        }
        return stripos($code, $state) !== false || stripos($msg, $state) !== false;
    }
}

$connection = new Connection();

// Allow override of Postgres user/pw via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['pg_user'])) {
        $connection->user = trim((string)$_POST['pg_user']);
        // Also set static config so newly created connections (used by changeOwner) use these creds
        \app\conf\Connection::$param['postgisuser'] = $connection->user;
    }
    if (!empty($_POST['pg_password'])) {
        $connection->password = (string)$_POST['pg_password'];
        \app\conf\Connection::$param['postgispw'] = $connection->password;
    }
}

$connected = false;
$conn = null;

if ($dbName) {
    $connection->database = $dbName;
    try {
        $conn = new \app\inc\Model(connection: $connection);
        $conn->connect();
        $connected = true;
        $messages[] = "Connected to database '$dbName' as user '" . $connection->user . "'";
    } catch (\Throwable $e) {
        $errors[] = "\app\conf\Connecsaasation failed: " . $e->getMessage();
    }
} else {
    $errors[] = "No database selected. Please go back and choose a database.";
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GC2 Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script>
        document.documentElement.setAttribute('data-bs-theme', (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'))
    </script>
</head>
<body class="d-flex flex-column min-vh-100">
<main class="container py-4 flex-grow-1">
    <h1 class="mb-3">GC2 Database Installer</h1>
    <p class="text-muted">This tool initializes the required PostGIS extensions and GC2 schemas in the selected
        PostgreSQL database. If the default credentials from app/conf/Connection.php do not work, enter the correct
        PostgreSQL superuser credentials below and retry.</p>

    <?php foreach ($messages as $m): ?>
        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($m); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <?php if (!$connected): ?>
        <div class="card mb-4">
            <div class="card-header">PostgreSQL Credentials</div>
            <div class="card-body">
                <p class="mb-3">The installer could not connect using the configured credentials. Provide a PostgreSQL
                    user with privileges to create extensions and schemas, then submit to retry.</p>
                <form method="post" action="prepare.php?db=<?php echo urlencode((string)$dbName); ?>">
                    <div class="mb-3">
                        <label for="pg_user" class="form-label">PostgreSQL user</label>
                        <input type="text" class="form-control" id="pg_user" name="pg_user"
                               value="<?php echo htmlspecialchars(($connection->user ?? '')); ?>"
                               required>
                    </div>
                    <div class="mb-3">
                        <label for="pg_password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="pg_password" name="pg_password"
                               value="<?php echo htmlspecialchars($connection->password ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Retry installation</button>
                    <a class="btn btn-secondary" href="index.php">Back</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($connected): ?>
        <div class="card mb-4">
            <div class="card-header">Installation progress</div>
            <div class="card-body">
                <?php
                // Try to create extensions
                try {
                    $conn->execQuery("create extension postgis_raster cascade", "PG", "transaction");
                    echo '<div class="alert alert-success">PostGIS extensions ensured.</div>';
                } catch (Exception $e) {
                    if (exceptionHasSqlState($e, '42501')) {
                        $needsElevated = true;
                        echo '<div class="alert alert-warning">Failed to create PostGIS extensions (insufficient privileges, SQLSTATE 42501): ' . htmlspecialchars($e->getMessage()) . '<br>You can provide elevated PostgreSQL credentials below to retry.</div>';
                    } else {
                        echo '<div class="alert alert-warning">Failed to create PostGIS extensions: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }

                $conn->begin();
                // Try to create schema
                try {
                    if ($schemaSql) {
                        $conn->execQuery($schemaSql, "PDO", "transaction");
                        echo '<div class="alert alert-success">GC2 settings schema created.</div>';
                    } else {
                        echo '<div class="alert alert-warning">Schema SQL not found.</div>';
                    }
                    $conn->commit();
                } catch (Exception $e) {
                    if (exceptionHasSqlState($e, '42P06')) {
                        echo '<div class="alert alert-success">GC2 settings schema already exists (SQLSTATE 42P06). Continuing.</div>';
                    } elseif (exceptionHasSqlState($e, '42501')) {
                        $needsElevated = true;
                        echo '<div class="alert alert-danger">Failed to create GC2 settings schema (insufficient privileges, SQLSTATE 42501): ' . htmlspecialchars($e->getMessage()) . '<br>Provide elevated PostgreSQL credentials below to retry.</div>';
                    } else {
                        echo '<div class="alert alert-danger">Failed to create GC2 settings schema: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    $conn->rollback();
                }

                echo '<div class="mt-3">Running post-install SQL scripts: ';
                $sqls = Sql::get();
                $firstOrLastFailed = false;
                $firstFailed = false;
                $lastFailed = false;
                $firstFailMsg = '';
                $lastFailMsg = '';
                $count = is_array($sqls) ? count($sqls) : 0;
                $i = 0;
                foreach ($sqls as $s) {
                    $isFirst = ($i === 0);
                    $isLast = ($count > 0 && $i === $count - 1);
                    try {
                        $conn->execQuery($s, "PDO", "transaction");
                        if ($isFirst || $isLast) {
                            echo '<span class="badge text-bg-primary me-1">OK</span>';
                        } else {
                            echo '<span class="badge text-bg-success me-1">OK</span>';
                        }
                    } catch (\PDOException $e) {
                        if ($isFirst || $isLast) {
                            echo '<span class="badge text-bg-danger me-1">SKIP</span>';
                        } else {
                            echo '<span class="badge text-bg-secondary me-1">SKIP</span>';
                        }
                        if ($isFirst) {
                            $firstOrLastFailed = true;
                            $firstFailed = true;
                            $firstFailMsg = (string)$e->getMessage();
                        }
                        if ($isLast) {
                            $firstOrLastFailed = true;
                            $lastFailed = true;
                            $lastFailMsg = (string)$e->getMessage();
                        }
                    }
                    $i++;
                }
                echo '</div>';
                if ($firstOrLastFailed) {
                    $detail = '';
                    if ($firstFailed) {
                        $detail .= 'First script failed. ';
                    }
                    if ($lastFailed) {
                        $detail .= 'Last script failed.';
                    }
                    echo '<div class="alert alert-danger mt-3">One or more critical post-install scripts failed. ' . htmlspecialchars($detail) . ' These scripts must succeed. Please click "Re-run scripts" to try again. It may take a couple of re-runs.</div>';
                    echo '<form method="post" action="prepare.php?db=' . urlencode((string)$dbName) . '">';
                    echo '<input type="submit" class="btn btn-outline-primary" value="Re-run scripts">';
                    echo '<input type="hidden" name="pg_user" value="'. ($_POST['pg_user'] ?? '')  . '">';
                    echo '<input type="hidden" name="pg_password" value="'. ($_POST['pg_password'] ?? '')  . '">';
                    echo '</form>';
                } else {
                    echo '<div class="alert alert-success mt-3">Post-install SQL scripts completed successfully. First and last scripts are OK.</div>';
                    $userExists = false;
                    $userModel = new \app\models\User(userId: (string)$dbName, connection: $connection);
                    $userModel->connect();
                    try {
                        $userModel->doesUserExist();
                        $userExists = true;
                        echo '<div class="alert alert-success mt-3">Owner user for database ' . htmlspecialchars((string)$dbName) . ' already exists. Skipping creation.</div>';
                    } catch (\Throwable) {
                        // Handle create owner user submission
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_owner'])) {
                            $ownerEmail = trim((string)($_POST['owner_email'] ?? ''));
                            $ownerPassword = trim((string)($_POST['owner_password'] ?? ''));
                            if ($ownerEmail === '' || $ownerPassword === '') {
                                echo '<div class="alert alert-danger mt-3">Please provide both email and password to create the owner user.</div>';
                            } else {
                                try {
                                    // Create the owner user record in mapcentia; name must be the database name
                                    $result = $userModel->createUser([
                                            'name' => (string)$dbName,
                                            'email' => $ownerEmail,
                                            'password' => $ownerPassword,
                                            'subuser' => false
                                    ]);
                                    if (!empty($result['success'])) {
                                        echo '<div class="alert alert-success mt-3">Owner user for database ' . htmlspecialchars((string)$dbName) . ' was created successfully.</div>';
                                        $userExists = true;
                                    } else {
                                        echo '<div class="alert alert-danger mt-3">Could not create owner user. Please try again.</div>';
                                    }
                                } catch (\Throwable $e) {
                                    echo '<div class="alert alert-danger mt-3">Failed to create owner user: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                }
                            }
                        }

                        // Show form to create owner user
                        if (!$userExists) {
                            echo '<div class="card mt-3">';
                            echo '  <div class="card-header">Create owner user for this database</div>';
                            echo '  <div class="card-body">';
                            echo '    <p class="mb-3">Create the primary GC2 user for <strong>' . htmlspecialchars((string)$dbName) . '</strong>. The user\'s identifier will be derived from the database name. Enter an email and password:</p>';
                            echo '    <form method="post" action="prepare.php?db=' . urlencode((string)$dbName) . '">';
                            echo '      <input type="hidden" name="create_owner" value="1">';
                            echo '      <div class="row g-3 align-items-end">';
                            echo '        <div class="col-md-5">';
                            echo '          <label for="owner_email" class="form-label">Email</label>';
                            echo '          <input type="email" class="form-control" id="owner_email" name="owner_email" value="'. ($_POST['owner_email'] ?? '')  . '" required>';
                            echo '        </div>';
                            echo '        <div class="col-md-4">';
                            echo '          <label for="owner_password" class="form-label">Password</label>';
                            echo '          <input type="password" class="form-control" id="owner_password" name="owner_password" required>';
                            echo '        </div>';
                            echo '        <div class="col-md-3">';
                            echo '          <button type="submit" class="btn btn-primary">Create user</button> ';
                            echo '          <a class="btn btn-secondary" href="index.php">Back to overview</a>';
                            echo '        </div>';
                            echo '      </div>';
                            echo '      <input type="hidden" name="pg_user" value="'. ($_POST['pg_user'] ?? '')  . '">';
                            echo '      <input type="hidden" name="pg_password" value="'. ($_POST['pg_password'] ?? '')  . '">';
                            echo '    </form>';
                            echo '  </div>';
                            echo '</div>';
                        }

                    }

                    // Offer to change ownership if owner user exists (either already existed or was just created)
                    if (!empty($userExists) && $userExists === true) {
                        // Handle change owner submission
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_change_owner'])) {
                            try {
                                (new \app\models\Database())->changeOwner(db: (string)$dbName, newOwner: (string)$dbName);
                                echo '<div class="alert alert-success mt-3">Database ownership changed to user ' . htmlspecialchars((string)$dbName) . '.</div>';
                            } catch (\Throwable $e) {
                                if (exceptionHasSqlState($e, '42501')) {
                                    $needsElevated = true;
                                    echo '<div class="alert alert-warning mt-3">Insufficient privileges to change ownership (SQLSTATE 42501). Provide elevated credentials below and try again. Message: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                } else {
                                    echo '<div class="alert alert-danger mt-3">Failed to change ownership: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                }
                            }
                        }
                        echo '<div class="card mt-3">';
                        echo '  <div class="card-header">Change ownership to the new owner</div>';
                        echo '  <div class="card-body">';
                        echo '    <p class="mb-3">After creating the owner user, you can change ownership of the database and all non-system schemas, tables, views and sequences to <strong>' . htmlspecialchars((string)$dbName) . '</strong>. Would you like to do this now? You can also return to this page later and do it.</p>';
                        echo '    <form method="post" action="prepare.php?db=' . urlencode((string)$dbName) . '">';
                        echo '      <input type="hidden" name="do_change_owner" value="1">';
                        echo '      <div class="d-flex gap-2">';
                        echo '        <button type="submit" class="btn btn-outline-primary">Change ownership now</button>';
                        echo '        <a class="btn btn-secondary" href="index.php">Back to overview</a>';
                        echo '      </div>';
                        echo '      <input type="hidden" name="pg_user" value="'. ($_POST['pg_user'] ?? '')  . '">';
                        echo '      <input type="hidden" name="pg_password" value="'. ($_POST['pg_password'] ?? '')  . '">';
                        echo '    </form>';
                        echo '  </div>';
                        echo '</div>';
                    }
                }
                ?>
                <?php if ($needsElevated): ?>
                    <div class="mt-4">
                        <div class="card border-warning">
                            <div class="card-header bg-warning-subtle">Elevated privileges required</div>
                            <div class="card-body">
                                <p class="mb-3">One or more operations failed due to insufficient privileges. Provide a
                                    PostgreSQL user with rights to create extensions and schemas, then retry.</p>
                                <form method="post" action="prepare.php?db=<?php echo urlencode((string)$dbName); ?>">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-4">
                                            <label for="pg_user2" class="form-label">PostgreSQL user</label>
                                            <input type="text" class="form-control" id="pg_user2" name="pg_user"
                                                   value="<?php echo htmlspecialchars($connection->user ?? ''); ?>"
                                                   required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="pg_password2" class="form-label">Password</label>
                                            <input type="password" class="form-control" id="pg_password2"
                                                   name="pg_password"
                                                   value="<?php echo htmlspecialchars($connection->password ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-primary">Retry with elevated
                                                credentials
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="mt-4">
                    <a class="btn btn-success" href="index.php">Back to overview</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

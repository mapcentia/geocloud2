<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


ini_set("display_errors", "no");

use app\conf\App;
use app\conf\Connection as ConnConf;
use app\models\Database;

include("../../app/inc/Model.php");
include("../../app/inc/Connection.php");
include("../../app/models/Database.php");
include("../../app/conf/App.php");
include("../../app/conf/Connection.php");

new App();

include("./check.php");

$messages = [];
$errors = [];
$needsElevated = false;
$created = false;
$dbName = trim((string)($_POST['new_db_name'] ?? ''));

// Names we never allow creating from here (system/template databases).
$reserved = ['mapcentia', 'postgres', 'template0', 'template1', 'postgis_template', 'rdsadmin', 'gc2scheduler'];

// Helper to detect SQLSTATE in exception code or message
if (!function_exists('exceptionHasSqlState')) {
    function exceptionHasSqlState($e, string $state): bool
    {
        try { $code = (string)$e->getCode(); } catch (\Throwable) { $code = ''; }
        try { $msg = (string)$e->getMessage(); } catch (\Throwable) { $msg = ''; }
        return stripos($code, $state) !== false || stripos($msg, $state) !== false;
    }
}

/**
 * Validate a PostgreSQL database name. CREATE DATABASE cannot use bound
 * parameters and Database::createdb() interpolates the name directly into
 * SQL, so the name must be strictly validated to prevent SQL injection.
 */
function validateDbName(string $name, array $reserved): ?string
{
    if ($name === '') {
        return "Please enter a database name.";
    }
    if (strlen($name) > 63) {
        return "Database name must be 63 characters or less.";
    }
    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
        return "Invalid name. Use only lowercase letters, digits and underscore, starting with a letter or underscore.";
    }
    if (in_array($name, $reserved, true)) {
        return "'" . $name . "' is a reserved/system database name and cannot be created here.";
    }
    return null;
}

// Allow override of Postgres user/pw via POST (elevated credentials)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['pg_user'])) {
        ConnConf::$param['postgisuser'] = trim((string)$_POST['pg_user']);
    }
    if (!empty($_POST['pg_password'])) {
        ConnConf::$param['postgispw'] = (string)$_POST['pg_password'];
    }

    $validationError = validateDbName($dbName, $reserved);
    if ($validationError !== null) {
        $errors[] = $validationError;
    } else {
        // Step 1: Connect to the cluster default database (e.g. 'postgres')
        $clusterConnected = false;
        $db = null;
        try {
            $db = new Database();
            $db->connect();
            $clusterConnected = true;
            $messages[] = "Connected to cluster on host '" . (ConnConf::$param['postgishost'] ?? '') . "' as user '" . (ConnConf::$param['postgisuser'] ?? '') . "'";
        } catch (\Throwable $e) {
            $needsElevated = true;
            $errors[] = "Connection to cluster failed: " . $e->getMessage() . " Provide elevated PostgreSQL credentials below and retry.";
        }

        // Step 2: Create the database if it does not already exist
        if ($clusterConnected) {
            $exists = false;
            try {
                $info = $db->doesDbExist($dbName);
                $exists = (bool)($info['success'] ?? false);
            } catch (\Throwable) {
                // If the check fails, fall through and attempt creation anyway
            }

            if ($exists) {
                $messages[] = "Database '" . $dbName . "' already exists.";
                $created = true;
            } else {
                try {
                    $db->createdb($dbName, 'template0');
                    $messages[] = "Database '" . $dbName . "' created.";
                    $created = true;
                } catch (\Throwable $e) {
                    if (exceptionHasSqlState($e, '42P04')) { // duplicate_database
                        $messages[] = "Database '" . $dbName . "' already exists (SQLSTATE 42P04).";
                        $created = true;
                    } elseif (exceptionHasSqlState($e, '42501')) { // insufficient_privilege
                        $needsElevated = true;
                        $errors[] = "Failed to create database '" . $dbName . "' due to insufficient privileges (SQLSTATE 42501). Provide elevated credentials below.";
                    } else {
                        $needsElevated = true;
                        $errors[] = "Failed to create database '" . $dbName . "': " . $e->getMessage();
                    }
                }
            }
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create new database</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script>
        document.documentElement.setAttribute('data-bs-theme', (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'))
    </script>
</head>
<body class="d-flex flex-column min-vh-100">
<main class="container py-4 flex-grow-1">
    <h1 class="mb-3">Create new database</h1>
    <p class="text-muted">This tool creates a new, empty PostgreSQL database. After it is created you can continue to
        install the PostGIS extensions and GC2 settings schema. If the default credentials from
        app/conf/Connection.php do not have privileges to create databases, enter a PostgreSQL user with those rights
        below and retry.</p>

    <?php foreach ($messages as $m): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if ($created): ?>
        <div class="card mb-4">
            <div class="card-header">Next step</div>
            <div class="card-body">
                <p class="mb-3">Database <strong><?= htmlspecialchars($dbName) ?></strong> is ready. Continue to install
                    the PostGIS extensions and GC2 settings schema, or create the owner user.</p>
                <a class="btn btn-primary" href="prepare.php?db=<?= urlencode($dbName) ?>">Install PostGIS &amp; GC2
                    schema</a>
                <a class="btn btn-secondary" href="index.php">Back to overview</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card mb-4<?= $needsElevated ? ' border-warning' : '' ?>">
            <div class="card-header<?= $needsElevated ? ' bg-warning-subtle' : '' ?>">
                <?= $needsElevated ? 'Elevated privileges required' : 'New database' ?>
            </div>
            <div class="card-body">
                <?php if ($needsElevated): ?>
                    <p class="mb-3">The operation requires additional privileges or the connection failed. Provide a
                        PostgreSQL user with rights to create databases, then submit to retry.</p>
                <?php endif; ?>
                <form method="post" action="createdb.php">
                    <div class="mb-3">
                        <label for="new_db_name" class="form-label">Database name</label>
                        <input type="text" class="form-control" id="new_db_name" name="new_db_name"
                               value="<?= htmlspecialchars($dbName) ?>"
                               pattern="[a-z_][a-z0-9_]*" maxlength="63"
                               placeholder="my_new_db" required>
                        <div class="form-text">Lowercase letters, digits and underscore only; must start with a letter
                            or underscore.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="pg_user" class="form-label">PostgreSQL user <span
                                        class="text-muted">(optional, for elevation)</span></label>
                            <input type="text" class="form-control" id="pg_user" name="pg_user"
                                   value="<?= htmlspecialchars((string)(ConnConf::$param['postgisuser'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="pg_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="pg_password" name="pg_password"
                                   value="<?= htmlspecialchars((string)(ConnConf::$param['postgispw'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary"><?= $needsElevated ? 'Retry with elevated credentials' : 'Create database' ?></button>
                        <a class="btn btn-secondary" href="index.php">Back to overview</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

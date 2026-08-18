<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Keeps app/wms/mapcache/mapcache.conf in sync with the per-database MapCache config files
 * (one "{db}.xml" per database). Meant to run from cron once a minute — the stable replacement
 * for the watch_mapcache_changes.sh busy-loop + reload.js.
 *
 * It rebuilds the desired MapCacheAlias list from the *.xml files and:
 *   - does nothing (no Apache reload) when the content is unchanged,
 *   - writes the new content atomically,
 *   - validates the resulting Apache config with `apachectl configtest` and REVERTS if it is
 *     invalid, so a broken MapCache xml (e.g. an unsupported cache backend) can never take Apache
 *     down — it simply doesn't get an alias until it is fixed,
 *   - gracefully reloads Apache only when the config actually changed and is valid.
 *
 * A non-blocking flock guards against overlapping runs; the lock is released automatically when the
 * process exits (no stale lock file to get stuck on).
 */

include_once(__DIR__ . "/../conf/App.php");

use app\conf\App;

new App();

const APACHECTL = "/usr/sbin/apachectl";

$dir = rtrim(App::$param['path'], "/") . "/app/wms/mapcache";
$confFile = $dir . "/mapcache.conf";

// Single-instance guard. LOCK_NB so a run never waits on another; the lock frees on exit.
$lock = fopen($dir . "/.mapcache_conf.lock", "c");
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // another run is in progress
}

// Build the desired mapcache.conf: one alias per "{db}.xml". The database name is the file's
// basename without the .xml extension (handles names containing dots, unlike a split on ".").
$xmls = glob($dir . "/*.xml") ?: [];
sort($xmls);
$lines = [""];
foreach ($xmls as $xml) {
    $db = basename($xml, ".xml");
    if ($db === "") {
        continue;
    }
    $lines[] = "MapCacheAlias /mapcache/$db $xml";
}
$new = implode("\n", $lines) . "\n";

$current = is_file($confFile) ? (string)file_get_contents($confFile) : "";
if ($new === $current) {
    exit(0); // nothing changed — no write, no reload
}

if (!writeAtomic($confFile, $new)) {
    fwrite(STDERR, "mapcache_conf: could not write $confFile\n");
    exit(1);
}

// Validate before reloading. If the config is invalid (typically a broken/unsupported xml), revert
// to the previous content so the running Apache is never asked to load a config that would fail.
exec(APACHECTL . " configtest 2>&1", $out, $code);
if ($code !== 0) {
    writeAtomic($confFile, $current);
    fwrite(STDERR, "mapcache_conf: config invalid, reverted mapcache.conf:\n" . implode("\n", $out) . "\n");
    exit(1);
}

exec(APACHECTL . " graceful 2>&1", $reloadOut, $reloadCode);
echo "mapcache_conf: updated (" . count($xmls) . " databases), Apache "
    . ($reloadCode === 0 ? "reloaded" : "reload FAILED: " . implode("\n", $reloadOut)) . "\n";
exit($reloadCode);

/**
 * Writes $content to $path atomically (temp file + rename) so a reader never sees a half-written
 * config.
 */
function writeAtomic(string $path, string $content): bool
{
    $tmp = $path . ".tmp." . getmypid();
    if (file_put_contents($tmp, $content) === false) {
        return false;
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

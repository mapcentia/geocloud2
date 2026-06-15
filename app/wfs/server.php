<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Legacy WFS adapter. The procedural body that lived here previously
 * has been extracted into the worker-safe app\wfs\Server class plus
 * its handlers. This file now exposes a single bootstrap function
 * that public/index.php calls per request.
 */

namespace app\wfs;

use app\conf\App;
use app\conf\Connection as StaticConnection;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Util;
use app\wfs\output\ExceptionReport;
use app\wfs\output\GmlWriter;
use Throwable;

function bootstrap_legacy_wfs(string $db, string $user, bool $parentUser): void
{
    ini_set('max_execution_time', '0');
    header('Content-Type: text/xml; charset=UTF-8');
    Util::disableOb();

    $schema = StaticConnection::$param['postgisschema'] ?? 'public';
    $srsParam = Input::getPath()->part(4);
    $srs = ($srsParam !== null && $srsParam !== '') ? (int) $srsParam : null;
    $trusted = false;
    foreach ((App::$param['trustedAddresses'] ?? []) as $address) {
        if (Util::ipInRange(Util::clientIp(), $address) && getenv('MODE_ENV') !== 'test') {
            $trusted = true;
            break;
        }
    }

    $ctx = new Context(
        connection: new Connection(database: $db, schema: $schema),
        database:   $db,
        schema:     $schema,
        user:       $user,
        parentUser: $parentUser,
        trusted:    $trusted,
        host:       Util::host(),
        thePath:    Util::thePath(),
        startTime:  microtime(true),
        srs:        $srs,
    );

    $writer = new GmlWriter(
        gmlNameSpace:    $schema,
        gmlNameSpaceUri: str_replace('https://', 'http://', "$ctx->host/$db/$schema"),
    );

    $req = null;
    try {
        $req = Request::fromHttp($ctx);
        new Server($ctx)->dispatch($req, $writer);
        $writer->writeMemoryFooter();
    } catch (Throwable $e) {
        // Legacy server.php caught Exception (incl. PDOException) and rendered
        // an OWS exception report rather than letting the request 500. Match
        // that behaviour so misconfigured/empty URLs return a structured
        // exception body, not a fatal error JSON.
        ExceptionReport::render($e, $req?->version ?? '1.1.0', $writer);
    }
}

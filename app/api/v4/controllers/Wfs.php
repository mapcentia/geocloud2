<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\Controller;
use app\api\v4\Responses\StreamedResponse;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\OwsException;
use app\exceptions\ServiceException;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\Util;
use app\wfs\Context;
use app\wfs\Request as WfsRequest;
use app\wfs\Server;
use app\wfs\output\ExceptionReport;
use app\wfs\output\GmlWriter;

#[Controller(route: 'api/v4/wfs/{db}/{schema}/{srs}/[timeSlice]', scope: Scope::PUBLIC)]
final class Wfs extends AbstractApi
{
    public function __construct(public Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
    }

    public function get_index(): StreamedResponse
    {
        return $this->stream();
    }

    public function post_index(): StreamedResponse
    {
        return $this->stream();
    }

    // WFS uses GET and POST only. PUT/PATCH/DELETE are required by ApiInterface
    // but rejected upstream by AcceptableMethods. Stub them to satisfy the contract.
    public function put_index(): StreamedResponse    { return $this->stream(); }
    public function patch_index(): StreamedResponse  { return $this->stream(); }
    public function delete_index(): StreamedResponse { return $this->stream(); }

    public function validate(): void
    {
        // Auth & layer checks happen inside Server::dispatch; nothing to validate here
    }

    private function stream(): StreamedResponse
    {
        $ctx = $this->buildContext();
        $writer = new GmlWriter(
            gmlNameSpace: $ctx->schema,
            gmlNameSpaceUri: str_replace('https://', 'http://', "{$ctx->host}/{$ctx->database}/{$ctx->schema}"),
        );

        return new StreamedResponse(
            contentType: 'text/xml; charset=UTF-8',
            callback: function () use ($ctx, $writer) {
                Util::disableOb();
                $req = null;
                try {
                    $req = WfsRequest::fromHttp($ctx);
                    (new Server($ctx))->dispatch($req, $writer);
                } catch (OwsException|ServiceException $e) {
                    ExceptionReport::render($e, $req?->version ?? '1.1.0', $writer);
                }
            },
        );
    }

    private function buildContext(): Context
    {
        $jwtUser = $this->route->jwt['data']['uid'] ?? null;
        $jwtDb = $this->route->jwt['data']['database'] ?? null;

        if ($jwtUser && $jwtDb) {
            $user = $jwtUser;
            $database = $jwtDb;
        } else {
            $authUser = Input::getAuthUser();
            $database = $this->route->getParam('db');
            if (!$authUser && empty($database)) {
                throw new OwsException(
                    'Authentication required',
                    attributes: ['exceptionCode' => 'NoApplicableCode']
                );
            }
            // For anonymous-readable layers, use the database name as the implicit user.
            // BasicAuth is invoked per-layer inside Server::dispatch when actually needed.
            $user = $authUser ?: $database;
        }

        $schema = $this->route->getParam('schema');
        $parentUser = $user === $database;

        $trusted = false;
        foreach ((App::$param['trustedAddresses'] ?? []) as $address) {
            if (Util::ipInRange(Util::clientIp(), $address) && getenv('MODE_ENV') !== 'test') {
                $trusted = true;
                break;
            }
        }

        return new Context(
            connection: new Connection(user: $user, database: $database),
            database: $database,
            schema: $schema,
            user: $user,
            parentUser: $parentUser,
            trusted: $trusted,
            host: Util::host(),
            thePath: Util::thePath(),
            startTime: microtime(true),
        );
    }
}

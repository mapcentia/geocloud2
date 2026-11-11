<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Responses\StreamedResponse;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Route2;
use app\models\Configuration as ConfigurationModel;
use OpenApi\Attributes as OA;
use Override;
use PDOException;

/**
 * Anonymous, read-only access to published Vidi configurations.
 *
 * There is no token, so the database comes from the path. A token sent along
 * is ignored: this route always answers as an anonymous caller would see it.
 * Unpublished and unknown keys are both 404, so keys cannot be probed.
 * Management lives in Configuration.
 */
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/configurations/database/{database}/[key]', scope: Scope::PUBLIC)]
class ConfigurationPublic extends AbstractApi
{
    private const string FILE_SUFFIX = '.json';

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct(connection: $connection);
        $this->resource = 'configurations';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/configurations/database/{database}/{key}', operationId: 'getPublicConfiguration', description: "Read published configurations without a token. Omit the key to list all published configurations. Append '.json' to the key to download the raw configuration body as a JSON file, which is the form Vidi loads.", security: [], tags: ['Configuration'],
        parameters: [
            new OA\Parameter(name: 'database', description: 'Database name.', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'mydb'),
            new OA\Parameter(name: 'key', description: "Configuration key, optionally followed by '.json' to get the raw body. Omit to list all published configurations.", in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'configuration_my_map_673211a0b1c2d312345678.json'),
        ],
        responses: [
            new OA\Response(response: 200, description: "Ok. With '.json' the response is the configuration body itself.", content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Configuration"),
                new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Configuration"))])),
            new OA\Response(response: 400, description: 'Invalid key'),
            new OA\Response(response: 404, description: 'The configuration does not exist or is not published, or the database does not exist'),
        ])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        [$key, $asFile] = $this->key();
        $database = $this->route->getParam('database');
        // Anything outside this set cannot be a GC2 database.
        if (!preg_match('/^[A-Za-z0-9_\-]+$/D', (string)$database)) {
            throw self::databaseNotFound();
        }
        $this->connection->database = $database;
        $model = new ConfigurationModel($this->connection);
        try {
            if ($key === null) {
                return $this->getResponse($model->list(true));
            }
            $configuration = $model->get($key, true);
        } catch (PDOException $e) {
            // The connection is opened lazily, so a database that is unknown, refuses
            // connections or is not a GC2 database (no settings.key_value) surfaces on
            // the first query. All three look the same to an anonymous caller.
            $message = $e->getMessage();
            if (str_contains($message, 'database "' . $database . '" does not exist')
                || str_contains($message, 'not currently accepting connections')
                || str_contains($message, '42P01')
                || str_contains($message, 'relation "settings.key_value" does not exist')) {
                throw self::databaseNotFound();
            }
            throw $e;
        }
        if ($asFile) {
            $body = $configuration['body'];
            return new StreamedResponse('application/json', function () use ($body) {
                echo $body;
            }, 200, ['Content-Disposition' => "attachment; filename=$key" . self::FILE_SUFFIX]);
        }
        return $this->getResponse([$configuration], single: true);
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        [$key] = $this->key();
        if ($key !== null) {
            ConfigurationModel::assertKey($key);
        }
    }

    /**
     * The key segment without its optional '.json' suffix, and whether the
     * suffix was there.
     *
     * @return array{0: string|null, 1: bool}
     */
    private function key(): array
    {
        // Not getParam(): it urldecodes, which turns the '+' of a Dashboard-made key into a space
        $raw = rawurldecode($this->route->params['key'] ?? '');
        if ($raw === '') {
            return [null, false];
        }
        if (str_ends_with($raw, self::FILE_SUFFIX)) {
            return [substr($raw, 0, -strlen(self::FILE_SUFFIX)), true];
        }
        return [$raw, false];
    }

    private static function databaseNotFound(): GC2Exception
    {
        return new GC2Exception("Database not found", 404, null, "DATABASE_NOT_FOUND");
    }

    #[Override]
    public function post_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    #[Override]
    public function patch_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    #[Override]
    public function delete_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    #[Override]
    public function put_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }
}

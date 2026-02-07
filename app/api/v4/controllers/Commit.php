<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;


use app\api\v4\AbstractApi;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Model;
use app\inc\Route2;
use app\models\Layer;
use app\models\Table as TableModel;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Commit",
    description: "Schema commit.",
    required: ["schema", "repo", "message"],
    properties: [
        new OA\Property(
            property: "schema",
            title: "Schema",
            description: "Schema to commit",
            type: "string",
            example: "myschema",
        ),
        new OA\Property(
            property: "repo",
            title: "Repository",
            description: "An Git repository URL with credentials.",
            type: "string",
            example: "https://user:password@github.com/path/repo.git"
        ),
        new OA\Property(
            property: "message",
            title: "Commit message",
            description: "The commit message.",
            type: "string",
            example: "My first commit"
        ),
        new OA\Property(
            property: "meta_query",
            title: "Meta query string",
            description: "Only commit meta for this search.",
            type: "string",
            default: null,
            example: "tag:mytables"
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['POST', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/commit', scope: Scope::SUPER_USER_ONLY)]
class Commit extends AbstractApi
{

    protected const string PATH = 'app/tmp/';

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     * @throws GitException
     */
    #[OA\Post(path: '/api/v4/commit', operationId: 'postCommit', description: "Commit schema changes to Git and push to remote.", tags: ['Commit'],)]
    #[OA\RequestBody(description: 'New index', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Commit"))]
    #[OA\Response(response: 200, description: 'Committed')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json'])]
    public function post_index(): Response
    {
        $body = Input::getBody();
        $data = json_decode($body);

        $schema = $data->schema;
        $message = $data->message;
        $repoStr = $data->repo;
        $metaQuery = $data->meta_query;
        $response = [];
        $targetDir = App::$param['path'] . self::PATH . $this->route->jwt["data"]['uid'] . '/__git/';

        function destroy($dir): void
        {
            if (!is_dir($dir)) {
                return;
            }
            $mydir = opendir($dir);
            while (false !== ($file = readdir($mydir))) {
                if ($file != "." && $file != "..") {
                    chmod($dir . $file, 0775);
                    if (is_dir($dir . $file)) {
                        chdir('.');
                        destroy($dir . $file . '/');
                        rmdir($dir . $file);
                    } else
                        unlink($dir . $file);
                }
            }
            closedir($mydir);
        }

        destroy($targetDir);

        $git = new Git;
        $repo = $git->cloneRepository($repoStr, $targetDir);
        $baseDir = $repo->getRepositoryPath() . '/_data';

        @mkdir($baseDir);
        @mkdir($baseDir . "/$schema", 0777, true);
        @mkdir($baseDir . "/$schema/tables", 0777, true);
        @mkdir($baseDir . '/meta', 0777, true);
        @mkdir($repo->getRepositoryPath() . '/pages', 0777, true);

        foreach ((new Model())->getTablesFromSchema($schema) as $name) {
            $table = Table::getTable(new TableModel($schema . "." . $name, false, true, false, connection: $this->connection), $this);
            $file = $baseDir . "/$schema/tables/" . $name . '.json';
            file_put_contents($file, json_encode($table, JSON_PRETTY_PRINT));
        }

        if ($metaQuery) {
            $layers = new Layer(connection: $this->connection);
            $auth = $this->route->jwt["data"]['superUser'];
            $res = $layers->getAll($this->route->jwt["data"]["database"], $auth, $metaQuery, false, true, false, false);
            $rows = $res["data"];
            $out = Meta::processRows($rows);
            foreach ($out as $item) {
                $file = $baseDir . '/meta/' . $item['f_table_schema'] . '.' . $item['f_table_name'] . '.json';
                file_put_contents($file, json_encode($item, JSON_PRETTY_PRINT));
                // Check if template and write it out
                $template = App::$param['path'] . '/app/conf/template.markdown';
                if (is_file($template)) {
                    $file = $repo->getRepositoryPath() . '/pages/' . preg_replace('/t_/', '', $item['f_table_name'], 1) . '.markdown';
                    echo $file . "\n";
                    file_put_contents($file, file_get_contents($template));
                }
            }
        }
        $response['changes'] = false;
        $repo->addAllChanges();
        if ($repo->hasChanges()) {
            $repo->commit($message);
            $repo->push(null, ['--repo' => $repoStr]);
            $response['changes'] = true;
        }
        return $this->postResponse('/api/v4/commit/', $response);
    }

    public function get_index(): Response
    {
        // TODO: Implement post_index() method.
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    public function patch_index(): Response
    {
        // TODO: Implement patch_index() method.
    }

    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $body = Input::getBody();
        $collection = self::getAssert();
        $this->validateRequest($collection, $body, Input::getMethod());
        $this->initiate();
    }

    static public function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'schema' => new Assert\Required([
                new Assert\Type('string'),
            ]),
            'message' => new Assert\Required([
                new Assert\Type('string'),
            ]),
            'repo' => new Assert\Required([
                new Assert\Type('string'),
            ]),
            'meta_query' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
        ]);
    }
}
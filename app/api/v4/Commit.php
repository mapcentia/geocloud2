<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;


use app\api\v3\Meta;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Model;
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
    required: ["schema", "repo", "message"],
    properties: [
        new OA\Property(
            property: "schema",
            title: "Schema to commit",
            type: "string",
            example: "myschema",
        ),
        new OA\Property(
            property: "repo",
            title: "Repository",
            description: "An Git repository URL",
            type: "string",
            example: "https://user:password@github.com/path/repo.git"
        ),
        new OA\Property(
            property: "message",
            title: "Commit message",
            type: "string",
            example: "My first commit"
        ),
        new OA\Property(
            property: "meta_query",
            title: "Meta query string",
            description: "Only commit meta for this search",
            type: "string",
            default: null,
            example: "tags:mytables"
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['POST', 'HEAD', 'OPTIONS'])]
class Commit extends AbstractApi
{

    protected const string PATH = 'app/tmp/git/';

    /**
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     * @throws GitException
     */
    #[OA\Post(path: '/api/v4/commit', operationId: 'postCommit', description: "Commit schema changes to Git and push to remote", tags: ['Commit'],)]
    #[OA\RequestBody(description: 'New index', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Commit"))]
    #[OA\Response(response: 200, description: 'Committed')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json'])]
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);

        $schema = $data->schema;
        $message = $data->message;
        $repoStr = $data->repo;
        $metaQuery = $data->meta_query;
        $response = [];
        $targetDir = App::$param['path'] . self::PATH;

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
        $baseDir = $repo->getRepositoryPath() . '/' . $schema;

        @mkdir($baseDir . '/schema', 0777, true);
        @mkdir($baseDir . '/schema/tables', 0777, true);
        @mkdir($baseDir . '/meta', 0777, true);

        foreach ((new Model())->getTablesFromSchema($schema) as $name) {
            $table = Table::getTable(new TableModel($schema . "." . $name, false, true, false));
            $file = $baseDir . '/schema/tables/' . $name . '.json';
            file_put_contents($file, json_encode($table, JSON_PRETTY_PRINT));
//            $repo->removeFile($schema . '/schema/tables/' . $name . '.json');
        }

        if ($metaQuery) {
            $layers = new Layer();
            $jwt = Jwt::validate()["data"];
            $auth = $jwt['superUser'];
            $res = $layers->getAll($jwt["database"], $auth, $metaQuery, false, true, false, false);
            $rows = $res["data"];
            $out = Meta::processRows($rows);
            foreach ($out as $item) {
                $file = $baseDir . '/meta/' . $item['f_table_name'] . '.json';
                file_put_contents($file, json_encode($item, JSON_PRETTY_PRINT));
//            $repo->removeFile($schema. '/meta/' . $item['f_table_name'] . '.json');
            }
        }

        $response['changes'] = false;
        $repo->addAllChanges();
        if ($repo->hasChanges()) {
            $repo->commit($message);
            $repo->push(null, ['--repo' => $repoStr]);
            $response['changes'] = true;
        }
        return $response;
    }

    public function get_index(): array
    {
        // TODO: Implement post_index() method.
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    public function patch_index(): array
    {
        // TODO: Implement patch_index() method.
    }

    public function delete_index(): array
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
        $data = json_decode($body);
        $collection = self::getAssert();
        $this->validateRequest($collection, $body, 'commit', Input::getMethod());
        $this->jwt = Jwt::validate()["data"];
        $this->initiate($data->schema, null, null, null, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
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
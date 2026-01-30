<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/sequences/[sequence]', scope: Scope::SUB_USER_ALLOWED)]
class Sequence extends AbstractApi
{

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'sequences';
    }

    /**
     * @throws GC2Exception
     */
    public function get_index(): Response
    {
        $r = [];
        $res = self::getSequences($this->table[0], $this->schema[0]);
        if (!empty($this->sequence)) {
            foreach ($this->sequence as $sequence) {
                foreach ($res as $c) {
                    if ($c['name'] == $sequence) {
                        $r[] = $c;
                    }
                }
            }
        } else {
            $r = $res;
        }
        return $this->getResponse($r);
    }

    public function post_index(): Response
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $list = [];
        $this->table[0]->connect();
        $this->table[0]->begin();
        if (!isset($data->sequences)) {
            $sequences = [$data];
        } else {
            $sequences = $data->sequences;
        }
        foreach ($sequences as $datum) {
            $list[] = self::addSequence($this->table[0], $this->schema[0], (array)$datum);
        }
        $this->table[0]->commit();
        $baseUri = "/api/v4/schemas/{$this->schema[0]}/sequences/";
        return $this->postResponse($baseUri, $list);    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    public function patch_index(): Response
    {
        $body = Input::getBody();
        $data = json_decode($body, true);
        $names = explode(',', $this->route->getParam("sequence"));
        $this->table[0]->begin();
        $list = [];

        foreach ($names as $name) {
            $list[] = $this->table[0]->alterSequence($name, $this->schema[0], $data);
        }
        $this->table[0]->commit();
        return $this->patchResponse('"/api/v4/schemas/{$this->schema[0]}/sequences/', $list);

    }

    public function delete_index(): Response
    {
        $names = explode(',', $this->route->getParam("sequence"));
        $this->table[0]->begin();
        foreach ($names as $name) {
            $this->table[0]->deleteSequence($name);
        }
        $this->table[0]->commit();
        return $this->deleteResponse();

    }

    public static function getSequences(\app\models\Table $table, string $schema): array
    {
        return $table->getSequences($schema)['data'];
    }

    public static function addSequence(\app\models\Table $table, string $schema, array $data, bool $withOwner = true): string
    {
        return $table->createSequence($data['name'], $schema, $data, $withOwner);
    }
    public static function alterSequence(\app\models\Table $table, string $schema, array $data, bool $withOwner = true): string
    {
        return $table->alterSequence($data['name'], $schema, $data);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $schema = $this->route->getParam("schema");
        $sequence = $this->route->getParam("sequence");
        $body = Input::getBody();
        // Patch and delete on collection is not allowed
        if (empty($sequence) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("", 406);
        }
        // Throw exception if tried with resource id
        if (Input::getMethod() == 'post' && $sequence) {
            $this->postWithResource();
        }
//        $collection = self::getAssert();
//        $this->validateRequest($collection, $body, Input::getMethod());
        $this->initiate(schema: $schema, key: $sequence, sequence: $sequence);    }
}
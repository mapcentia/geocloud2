<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\exceptions\RPCException;
use app\models\Preparedstatement as PreparedstatementModel;
use app\models\Sql;
use Exception;


readonly class Rpc
{

    function __construct(private Connection $connection)
    {
    }

    /**
     * @throws RPCException
     */
    function run(string $user, Sql $api, array $query, bool $subuser, ?string $userGroup): ?array
    {
        $pres = new PreparedstatementModel(connection: $this->connection);;
        try {
            $preStm = $pres->getByName($query['method']);
        } catch (Exception) {
            throw new RPCException("Method not found", -32601, null, id: $query['id']);
        }
        $query['q'] = $preStm['data']['statement'];
        $query['type_hints'] = json_decode($preStm['data']['type_hints'], true);
        $query['type_formats'] = json_decode($preStm['data']['type_formats'], true);
        $query['format'] = $preStm['data']['output_format'];
        $query['params'] = $query['params'] ?? null;
        $api->setSRS($preStm['data']['srs']);
        try {
            $statement = new Statement(connection: $this->connection, convertReturning: true);
            $res = $statement->run(user: $user, api: $api, query: $query, subuser: $subuser, userGroup: $userGroup);;
        } catch (Exception $e) {
            if (in_array($e->getCode(), ['HY093', '406'])) {
                throw new RPCException("Invalid params", -32602, null, $e->getMessage(), $query['id']);
            }
            throw new RPCException("Internal error", -32603, null, $e->getMessage(), $query['id']);
        }
        $jsonRpcResponse = [
            'jsonrpc' => $query['jsonrpc'],
            'result' => $res,
            'method' => $query['method'],
            'type_hints' => $query['type_hints'],
            'params' => $query['params'],
        ];
        if (isset($query['id'])) {
            $jsonRpcResponse['id'] = $query['id'];
            return $jsonRpcResponse;
        }
        return null;
    }
}
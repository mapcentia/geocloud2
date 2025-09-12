<?php

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
    function run(string $user, Sql $api, array $json, bool $subuser, ?string $userGroup): array
    {
        $pres = new PreparedstatementModel(connection: $this->connection);;
        try {
            $preStm = $pres->getByName($json['method']);
        } catch (Exception) {
            throw new RPCException("Method not found", -32601, null, id: $json['id']);
        }
        $json['q'] = $preStm['data']['statement'];
        $json['type_hints'] = json_decode($preStm['data']['type_hints'], true);
        $json['type_formats'] = json_decode($preStm['data']['type_formats'], true);
        $json['format'] = $preStm['data']['output_format'];
        $json['srs'] = $preStm['data']['srs'];
        $json['params'] = $json['params'] ?? null;
        try {
            $statement = new Statement(connection: $this->connection, convertReturning: true);
            $res = $statement->run(user: $user, api: $api, json: $json, subuser: $subuser, userGroup: $userGroup);;
        } catch (Exception $e) {
            if (in_array($e->getCode(), ['HY093', '406'])) {
                throw new RPCException("Invalid params", -32602, null, $e->getMessage(), $json['id']);
            }
            throw new RPCException("Internal error", -32603, null, $e->getMessage(), $json['id']);
        }
        $jsonRpcResponse = [
            'jsonrpc' => $json['jsonrpc'],
            'result' => $res,
        ];
        if (isset($json['id'])) {
            $jsonRpcResponse['id'] = $json['id'];
        }
        return $jsonRpcResponse;
    }
}
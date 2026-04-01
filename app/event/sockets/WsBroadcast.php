<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\event\sockets;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Parallel\Worker\Execution;
use Amp\Parallel\Worker\WorkerPool;
use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\Server\WebsocketGateway;
use Amp\Websocket\WebsocketClient;
use Amp\Websocket\WebsocketClosedException;
use app\event\tasks\AuthTask;
use app\event\tasks\RunGraphQLTask;
use app\event\tasks\RunQueryTask;
use app\event\tasks\RunRpcTask;
use app\event\tasks\ValidateTokenTask;
use app\exceptions\GraphQLException;
use app\inc\Connection;
use app\inc\GraphQL;
use SplObjectStorage;
use Throwable;
use function Amp\Parallel\Worker\workerPool;


class WsBroadcast implements WebsocketClientHandler
{
    private SplObjectStorage $clientProperties;
    public WorkerPool $workerPool;

    public function __construct(public WebsocketGateway $gateway = new WebsocketClientGateway())
    {
        $this->clientProperties = new SplObjectStorage();
        $this->workerPool = workerPool();
    }

    /**
     * @throws WebsocketClosedException
     */
    public function handleClient(WebsocketClient $client, Request $request, Response $response): void
    {
        $query = $request->getUri()->getQuery();
        parse_str($query, $params);
        $errorMsg = null;

        if (isset($params['token'])) {
            $token = $params['token'];

            try {
                // Validate token and parsed data
                $task = new ValidateTokenTask($token);
                $parsed = $this->workerPool->getWorker()->submit($task)->await()['data'];
                // Connection to the database
                $connection = new Connection(database: $parsed["database"]);
                if (!$parsed['superUser']) {
                    foreach (explode(',', $params['rel']) as $rel) {
                        $task = new AuthTask($parsed, $rel, $connection);
                        if (!$this->workerPool->getWorker()->submit($task)->await()) {
                            $errorMsg = [
                                'type' => 'error',
                                'error' => 'not_allowed',
                                'message' => "Not allowed to access this resource: $rel",
                            ];
                            goto end;
                        }
                    }

                }
                $this->gateway->addClient($client);
                $db = $parsed['database'];
                $this->clientProperties->offsetSet($client, [
                    'joinedAt' => time(),
                    'db' => $parsed['database'],
                    'user' => $parsed['uid'],
                    'superUser' => $parsed['superUser'],
                    'userGroup' => $parsed['userGroup'] ?? null,
                    'rels' => !empty($params['rel']) ? explode(',', $params['rel']) : null,
                    'subscriptions' => [],
                ]);
                echo "[INFO] Client {$client->getId()} connected on $db\n";;
            } catch (Throwable $e) {
                $errorMsg = [
                    'type' => 'error',
                    'error' => 'invalid_token',
                ];
            }
        } else {
            $errorMsg = [
                'type' => 'error',
                'error' => 'missing_token',
                'message' => 'Missing token',
            ];
        }
        end:
        if ($errorMsg) {
            echo "[ERROR] Could not connect client\n";
            $this->sendToClient($client, json_encode($errorMsg));
            $client->close();
            echo "[ERROR] {$errorMsg['message']}\n";
        }

        // Keep reading incoming messages to keep the connection open
        while ($message = $client->receive()) {
            try {
                $payload = $message->buffer();
                $props = $this->clientProperties[$client];
                echo "[INFO] message payload from {$client->getId()} on {$props['db']}\n";
                $parsed = json_decode($payload, true);
                if (!$parsed) {
                    $errorMsg = [
                        'type' => 'error',
                        'error' => 'INVALID_JSON',
                        'message' => "Invalid JSON payload: $payload",
                    ];
                    $this->sendToClient($client, json_encode($errorMsg));
                    throw new \Exception("Invalid JSON payload: $payload");
                }
                // Handle the message. Check the type and call the appropriate method.
                if (!array_is_list($parsed)) {
                    $parsed = [$parsed];
                }
                $r = null;

                if (isset($parsed[0]['q'])) {
                    $r = $this->sql($parsed, $props);
                }

                if (isset($parsed[0]['jsonrpc'])) {
                    $r = $this->rpc($parsed, $props);
                }

                if (isset($parsed[0]['type']) && $parsed[0]['type'] === 'subscribe') {
                    try {
                        GraphQL::parseSubscription($parsed[0]['query'], $parsed[0]['schema']);
                        $this->gqlSubscribe($client, $parsed[0]);
                        continue;
                    } catch (Throwable) {
                        $r = $this->gql($parsed, $props, $parsed[0]['schema']);
                    }
                }

                // Add relations dynamically for messaging
                if (isset($parsed[0]['type']) && $parsed[0]['type'] === 'subscription') {
                    $this->rawSubscribe($client, $parsed[0], $parsed[0]['id'] ?? null);
                    continue;
                }
                if ($r) {
                    $result = array_values(array_filter($r->await()));
                    if (count($result) == 1) {
                        $result = $result[0];
                    }
                    if (!empty($result)) {
                        $this->sendToClient($client, json_encode($result));
                    }
                }
            } catch (Throwable $e) {
                echo "[ERROR] " . $e->getMessage() . "\n";
            }
        }
        $this->clientProperties->detach($client);
    }

    /**
     * Register a GraphQL subscription for a client.
     *
     * Expected message format:
     * {
     *   "type": "subscribe",
     *   "id": "sub1",
     *   "schema": "my_schema",
     *   "query": "subscription { MyTableMessageAdded(where: \"col = 'val'\") { id name } }"
     * }
     */
    private function gqlSubscribe(WebsocketClient $client, array $msg): void
    {
        $subId = $msg['id'] ?? null;
        $schema = $msg['schema'] ?? null;
        $query = $msg['query'] ?? null;

        if (!$subId || !$schema || !$query) {
            $this->sendToClient($client, json_encode([
                'type' => 'error',
                'id' => $subId,
                'error' => 'INVALID_SUBSCRIPTION',
                'message' => 'Missing required fields: id, schema, query',
            ]));
            return;
        }

        try {
            $parsed = GraphQL::parseSubscription($query, $schema);
        } catch (GraphQLException $e) {
            $this->sendToClient($client, json_encode([
                'type' => 'error',
                'id' => $subId,
                'error' => 'SUBSCRIPTION_PARSE_ERROR',
                'message' => $e->getMessage(),
            ]));
            return;
        }

        $props = $this->clientProperties[$client];

        foreach ($parsed as $sub) {
            $sub['id'] = $subId;
            $props['subscriptions'][] = $sub;
        }

        $rels = $props['rels'] ?? [];
        foreach ($parsed as $sub) {
            if (!in_array($sub['rel'], $rels, true)) {
                $rels[] = $sub['rel'];
            }
        }
        $props['rels'] = $rels;

        $this->clientProperties[$client] = $props;

        echo "[INFO] Client {$client->getId()} subscribed: $subId on $schema\n";

        $this->sendToClient($client, json_encode([
            'type' => 'subscribe_ack',
            'id' => $subId,
        ]));
    }

    private function rawSubscribe(WebsocketClient $client, array $msg): void {
        $data = $this->clientProperties[$client];
        $data['rels'] = [$msg['schema'] . '.' . $msg['rel']];
        $data['where'] = $msg['where'] ?? null;
        $data['columns'] = $msg['columns'] ?? null;
        $data['op'] = $msg['op'] ?? null;
        $this->clientProperties[$client] = $data;
        $this->sendToClient($client, json_encode([
            'type' => 'subscribe_ack',
            'id' => $msg['id'],
        ]));
    }

    public function sendToAll(string $text): void
    {
        echo "sendToAll\n";
        $this->gateway->broadcastText($text);
    }

    /**
     * @throws WebsocketClosedException
     */
    public function sendToClient(WebsocketClient $client, string $text): void
    {
        $client->sendText($text);
    }

    private function sql(array $query, ?array $props): Execution
    {
        $task = new RunQueryTask($query, $props);
        return $this->workerPool->getWorker()->submit($task);
    }

    private function rpc(array $query, ?array $props): Execution
    {
        $task = new RunRpcTask($query, $props);
        return $this->workerPool->getWorker()->submit($task);
    }

    private function gql(array $query, ?array $props, string $schema): Execution
    {
        $task = new RunGraphQLTask($query, $props, $schema);
        return $this->workerPool->getWorker()->submit($task);
    }

    public function getProperties(WebsocketClient $client): array
    {
        return $this->clientProperties[$client];
    }

    public function setProperties(WebsocketClient $client, array $props): void
    {
        $this->clientProperties[$client] = $props;
    }
}
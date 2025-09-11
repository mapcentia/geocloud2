<?php

namespace app\event\sockets;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Parallel\Worker\Execution;
use Amp\Parallel\Worker\Worker;
use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\Server\WebsocketGateway;
use Amp\Websocket\WebsocketClient;
use Amp\Websocket\WebsocketClosedException;
use app\event\tasks\AuthTask;
use app\event\tasks\RunQueryTask;
use app\event\tasks\ValidateTokenTask;
use app\inc\Connection;
use SplObjectStorage;
use Throwable;
use function Amp\Parallel\Worker\createWorker;


readonly class WsBroadcast implements WebsocketClientHandler
{
    private SplObjectStorage $clientProperties;
    public Worker $worker;

    public function __construct(public WebsocketGateway $gateway = new WebsocketClientGateway())
    {
        $this->clientProperties = new SplObjectStorage();
        $this->worker = createWorker();
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
                $parsed = $this->worker->submit($task)->await()['data'];
                print_r($parsed);
                // Connection to the database
                $connection = new Connection(database: $parsed["database"]);
                if (!$parsed['superUser']) {
                    foreach (explode(',', $params['rel']) as $rel) {
                        $task = new AuthTask($parsed, $rel, $connection);
                        if (!$this->worker->submit($task)->await()) {
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
                $this->clientProperties->attach($client, [
                    'joinedAt' => time(),
                    'db' => $parsed['database'],
                    'user' => $parsed['uid'],
                    'superUser' => $parsed['superUser'],
                    'userGroup' => $parsed['userGroup'] ?? null,
                    'rels' => !empty($params['rel']) ? explode(',', $params['rel']) : null,
                ]);
                echo "[INFO] Client {$client->getId()} connected on $db\n";;
            } catch (Throwable $e) {
                $errorMsg = [
                    'type' => 'error',
                    'error' => 'invalid_token',
                    'message' => $e->getMessage(),
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
                echo "[INFO] message '$payload' from {$client->getId()} on {$props['db']}\n";
                $r = $this->sql($payload, $props);
                $this->sendToClient($client, json_encode($r->await()));
            } catch (Throwable $e) {
                echo "[ERROR] " . $e->getMessage() . "\n";
            }
        }
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

    private function sql(string $sql, ?array $props): Execution
    {
        $task = new RunQueryTask($sql, $props);
        return $this->worker->submit($task);
    }

    public function getProperties(WebsocketClient $client): array
    {
        return $this->clientProperties[$client];
    }
}
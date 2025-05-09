<?php

namespace app\event\sockets;

use Amp\ByteStream\BufferException;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Parallel\Worker\Execution;
use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\Server\WebsocketGateway;
use Amp\Websocket\WebsocketClient;
use Amp\Websocket\WebsocketClosedException;
use app\event\tasks\RunQueryTask;
use SplObjectStorage;
use function Amp\Parallel\Worker\createWorker;


readonly class WsBroadcast implements WebsocketClientHandler
{
    private SplObjectStorage $clientProperties;

    public function __construct(public WebsocketGateway $gateway = new WebsocketClientGateway())
    {
        $this->clientProperties = new SplObjectStorage();

    }

    /**
     * @throws WebsocketClosedException
     * @throws BufferException
     */
    public function handleClient(WebsocketClient $client, Request $request, Response $response): void
    {
        $this->gateway->addClient($client);
        $this->clientProperties->attach($client, [
            'joinedAt' => time(),
            'role' => 'guest'
        ]);
        echo "Client connected: " . $client->getId() . PHP_EOL;
        // Keep reading incoming messages to keep the connection open
        while ($message = $client->receive()) {
            $payload = $message->buffer();
            echo $payload . " from " . $client->getId() . PHP_EOL;
            $props = $this->clientProperties[$client];

            print_r($props);

            $r = $this->sql($payload, 'mydb');
            $this->sendToClient($client, json_encode($r->await()));
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
        $client->sendText($text . " " . $client->getId());

    }

    private function sql(string $sql, string $db): Execution
    {
        $task = new RunQueryTask($sql, $db);
        $worker = createWorker();
        return $worker->submit($task);
    }
}
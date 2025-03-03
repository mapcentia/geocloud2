<?php

namespace app\event;

use Amp\Websocket\WebsocketClient;
use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\Server\WebsocketGateway;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;

readonly class WsBroadcast implements WebsocketClientHandler
{
    public function __construct(private WebsocketGateway $gateway = new WebsocketClientGateway())
    {
    }

    public function handleClient(WebsocketClient $client, Request $request, Response $response): void
    {
        $this->gateway->addClient($client);
        echo "Client connected: " . $client->getId() . PHP_EOL;
        // Keep reading incoming messages to keep the connection open
        while ($message = $client->receive()) {
            $payload = $message->buffer();
            echo $payload . " from " . $client->getId() . PHP_EOL;
            $this->sendToClient($client, "Hello");
        }
    }

    public function sendToAll(string $text): void
    {
        echo "sendToAll\n";
        $this->gateway->broadcastText($text);
    }

    public function sendToClient(WebsocketClient $client, string $text): void
    {
        $client->sendText($text . " " . $client->getId());

    }
}
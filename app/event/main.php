<?php

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Router;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket;
use Amp\Websocket\Server\Rfc6455Acceptor;
use Amp\Websocket\Server\Websocket;
use Amp\Http\Server\Response;
use Amp\Http\HttpStatus;
use app\conf\App;
use app\event\sockets\WsBroadcast;
use Monolog\Logger;
use Revolt\EventLoop;
use function Amp\ByteStream\getStdout;
use function Amp\trapSignal;

include_once __DIR__ . "/../vendor/autoload.php";
include_once __DIR__ . "/../conf/App.php";
include_once __DIR__ . "/../conf/Connection.php";
include_once __DIR__ . "/../inc/Model.php";
include_once __DIR__ . "/../models/Database.php";

// Global event loop error handler for uncaught exceptions
EventLoop::setErrorHandler(function (Throwable $throwable): void {
    error_log('Uncaught exception in event loop: ' . $throwable->getMessage());
});


new App();

$logHandler = new StreamHandler(getStdout());
$logHandler->setFormatter(new ConsoleFormatter());
$logger = new Logger('server');
$logger->pushHandler($logHandler);
$server = SocketHttpServer::createForDirectAccess($logger);
$server->expose(new Socket\InternetAddress('0.0.0.0', 80));
$server->expose(new Socket\InternetAddress('[::1]', 80));
$errorHandler = new DefaultErrorHandler();

$acceptor = new Rfc6455Acceptor();

$broadcastHandler = new WsBroadcast();
$wsBroadcast = new Websocket($server, $logger, $acceptor, $broadcastHandler);

$router = new Router($server, $logger, $errorHandler);
$router->addRoute('GET', '/broadcast', $wsBroadcast);
$router->addRoute('GET', '/ping', new ClosureRequestHandler(
    function () {
        return new Response(
            status: HttpStatus::OK,
            headers: ['content-type' => 'text/plain'],
            body: 'pong',
        );
    },
));//$router->setFallback(new DocumentRoot($server, $errorHandler, '/var/www/geocloud2/public'));

$server->start($router, $errorHandler);

foreach (glob(dirname(__FILE__) . "/functions/*.php") as $filename) {
   include_once($filename);
}

include_once __DIR__ . "/pglistner.php";

// Await SIGINT or SIGTERM to be received.
$signal = trapSignal([SIGINT, SIGTERM]);

$logger->info(sprintf("Received signal %d, stopping HTTP server", $signal));

$server->stop();

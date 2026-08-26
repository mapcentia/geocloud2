<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use app\opensearch\Client;
use app\opensearch\OpenSearchException;

class OpenSearchClientTest extends \Codeception\Test\Unit
{
    private function clientWith(array $responses): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $guzzle = new GuzzleClient(['handler' => $stack, 'http_errors' => false]);
        return new Client('http://os:9200', $guzzle);
    }

    public function testIndexExistsTrueOn200(): void
    {
        $c = $this->clientWith([new Response(200)]);
        $this->assertTrue($c->indexExists('db_s_t'));
    }

    public function testIndexExistsFalseOn404(): void
    {
        $c = $this->clientWith([new Response(404)]);
        $this->assertFalse($c->indexExists('db_s_t'));
    }

    public function testCreateIndexThrowsOnError(): void
    {
        $c = $this->clientWith([new Response(400, [], json_encode(['error' => ['reason' => 'bad analyzer']]))]);
        $this->expectException(OpenSearchException::class);
        $c->createIndex('db_s_t', ['settings' => []]);
    }

    public function testSearchReturnsDecodedBody(): void
    {
        $c = $this->clientWith([new Response(200, [], json_encode(['hits' => ['total' => ['value' => 3]]]))]);
        $res = $c->search('db_s_t', '{"query":{"match_all":{}}}', true);
        $this->assertSame(3, $res['hits']['total']['value']);
    }
}

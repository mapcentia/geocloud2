<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\opensearch;

use app\conf\App;
use GuzzleHttp\Client as GuzzleClient;

class Client
{
    private string $host;
    private GuzzleClient $http;

    public function __construct(?string $host = null, ?GuzzleClient $http = null)
    {
        $raw = $host ?? (App::$param['esHost'] ?: 'http://127.0.0.1');
        $split = explode(':', $raw);
        $port = !empty($split[2]) ? $split[2] : '9200';
        $this->host = $split[0] . ':' . $split[1] . ':' . $port;
        $this->http = $http ?? new GuzzleClient([
            'timeout' => 60.0,
            'http_errors' => false,
            'headers' => ['Content-Type' => 'application/json'],
        ]);
    }

    public function indexExists(string $index): bool
    {
        $res = $this->http->head("$this->host/$index");
        return $res->getStatusCode() === 200;
    }

    public function createIndex(string $index, array $body): array
    {
        $res = $this->http->put("$this->host/$index", ['body' => json_encode($body)]);
        return $this->decodeOrThrow($res, "Could not create index '$index'");
    }

    public function deleteIndex(string $index): void
    {
        $res = $this->http->delete("$this->host/$index");
        $code = $res->getStatusCode();
        if ($code !== 200 && $code !== 404) {
            $this->decodeOrThrow($res, "Could not delete index '$index'");
        }
    }

    public function search(string $index, string $query, bool $isBody): array
    {
        $url = "$this->host/$index/_search";
        if ($isBody) {
            $res = $this->http->post($url, ['body' => $query]);
        } else {
            $res = $this->http->get($url . ($query !== '' ? "?$query" : ''));
        }
        return $this->decodeOrThrow($res, "Search failed on index '$index'");
    }

    private function decodeOrThrow(\Psr\Http\Message\ResponseInterface $res, string $context): array
    {
        $body = json_decode((string)$res->getBody(), true);
        $code = $res->getStatusCode();
        if ($code < 200 || $code >= 300) {
            throw new OpenSearchException($context, $code, is_array($body) ? $body : null);
        }
        return is_array($body) ? $body : [];
    }
}

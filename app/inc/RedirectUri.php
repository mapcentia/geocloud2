<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc;

use app\conf\App;
use app\models\Client;
use Throwable;

/**
 * Decides whether sign-in, sign-up or sign-out may send the browser on to a
 * redirect_uri taken from the request.
 */
class RedirectUri
{
    /**
     * Allowed: a path on this server; an http(s) address on this server's host, on
     * a host under the session cookie domain, or on a host the operator listed;
     * and an address registered on an OAuth client, by exact match.
     *
     * @param string[] $registered redirect URIs registered on the client(s)
     * @param string[] $extraHosts App::$param['redirectUriHosts']
     */
    public static function isAllowed(?string $uri, array $registered, ?string $host, ?string $sessionDomain, array $extraHosts): bool
    {
        if ($uri === null || $uri === '') {
            return false;
        }
        if (in_array($uri, $registered, true)) {
            return true;
        }
        // A path on this server. '//host' and '/\host' are other sites to a browser.
        if ($uri[0] === '/') {
            return !isset($uri[1]) || ($uri[1] !== '/' && $uri[1] !== '\\');
        }
        $parts = parse_url($uri);
        if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }
        $target = strtolower($parts['host']);
        $own = array_map('strtolower', $extraHosts);
        if ($host && ($ownHost = parse_url($host, PHP_URL_HOST))) {
            $own[] = strtolower($ownHost);
        }
        if (in_array($target, $own, true)) {
            return true;
        }
        $domain = strtolower(ltrim((string)$sessionDomain, '.'));
        return $domain !== '' && ($target === $domain || str_ends_with($target, '.' . $domain));
    }

    /**
     * isAllowed() with this server's settings and the client's registered URIs.
     * With a database but no client id, every client in that database counts.
     */
    public static function check(?string $uri, ?string $database, ?string $clientId): bool
    {
        return self::isAllowed(
            $uri,
            self::registered($database, $clientId),
            App::$param['host'] ?? null,
            App::$param['sessionDomain'] ?? null,
            App::$param['redirectUriHosts'] ?? [],
        );
    }

    /**
     * @return string[] none when the database or client cannot be found
     */
    public static function registered(?string $database, ?string $clientId): array
    {
        if (empty($database) || !preg_match('/^[A-Za-z0-9_\-]+$/D', $database)) {
            return [];
        }
        try {
            $clients = new Client(connection: new Connection(database: $database))->get($clientId ?: null);
        } catch (Throwable) {
            return [];
        }
        $uris = [];
        foreach ($clients as $client) {
            foreach ((array)($client['redirect_uri'] ?? []) as $uri) {
                $uris[] = $uri;
            }
        }
        return $uris;
    }
}

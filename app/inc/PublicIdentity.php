<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc;

use app\conf\App;
use app\exceptions\GC2Exception;

/**
 * The request identity on a PUBLIC (token-less) v4 route: a validated Bearer token, verified
 * HTTP Basic credentials, a trusted address, or anonymous. Shared by the OWS, WFS and OGC API
 * controllers so they keep one auth model. Mirrors the former Ows::buildContext/Wfs::buildContext.
 */
final readonly class PublicIdentity
{
    public function __construct(
        public Connection $connection,
        public string     $database,
        public ?string    $schema,
        /** JWT uid, the Basic user, or the database name for anonymous requests (connection identity). */
        public string     $user,
        public ?array     $userGroup,
        public bool       $parentUser,
        public bool       $trusted,
        /** The raw Bearer token, for auth-cache keys. */
        public ?string    $bearer,
        /** True when neither a Bearer token nor Basic credentials were presented. */
        public bool       $anonymous,
    ) {}

    /**
     * A presented Bearer token is validated and must match the database in the path. Basic
     * credentials are verified (challenging 401 when wrong) so a fabricated header cannot become
     * the identity on layer-less requests. Trusted addresses skip Basic verification.
     *
     * @throws GC2Exception 401 when the token is issued for another database; Jwt::validate throws on invalid tokens
     */
    public static function resolve(string $database, ?string $schema = null): self
    {
        $bearer = Input::getJwtToken() ?: null;
        $trusted = false;
        foreach ((App::$param['trustedAddresses'] ?? []) as $address) {
            if (Util::ipInRange(Util::clientIp(), $address) && getenv('MODE_ENV') !== 'test') {
                $trusted = true;
                break;
            }
        }
        if ($bearer) {
            $jwt = Jwt::validate($bearer)['data'];
            if (($jwt['database'] ?? null) !== $database) {
                throw new GC2Exception('Token is not valid for this database', 401, null, 'TOKEN_DATABASE_MISMATCH');
            }
            $user = $jwt['uid'];
            return new self(
                connection: new Connection(user: $user, database: $database, schema: $schema),
                database: $database,
                schema: $schema,
                user: $user,
                userGroup: $jwt['userGroup'] ?? null,
                parentUser: $user === $database,
                trusted: $trusted,
                bearer: $bearer,
                anonymous: false,
            );
        }
        $authUser = Input::getAuthUser();
        $user = $authUser ?: $database;
        $connection = new Connection(user: $user, database: $database, schema: $schema);
        if (!$trusted) {
            new BasicAuth(connection: $connection)->verifyCredentials();
        }
        return new self(
            connection: $connection,
            database: $database,
            schema: $schema,
            user: $user,
            userGroup: null,
            parentUser: $user === $database,
            trusted: $trusted,
            bearer: null,
            anonymous: empty($authUser),
        );
    }

    /** Geofence identity: the user for token/Basic requests, "*" for anonymous ones. */
    public function geofenceUser(): string
    {
        return $this->anonymous ? '*' : $this->user;
    }

    public function wfsContext(string $schema, ?int $srs = null): \app\wfs\Context
    {
        return new \app\wfs\Context(
            connection: new Connection(user: $this->user, database: $this->database, schema: $schema),
            database: $this->database,
            schema: $schema,
            user: $this->user,
            userGroup: $this->userGroup,
            parentUser: $this->parentUser,
            trusted: $this->trusted,
            host: Util::host(),
            thePath: Util::thePath(),
            startTime: microtime(true),
            srs: $srs,
            tokenAuth: $this->bearer !== null,
            geofenceUser: $this->geofenceUser(),
        );
    }

    public function owsContext(string $schema): \app\ows\Context
    {
        return new \app\ows\Context(
            connection: new Connection(user: $this->user, database: $this->database, schema: $schema),
            database: $this->database,
            schema: $schema,
            user: $this->user,
            userGroup: $this->userGroup,
            parentUser: $this->parentUser,
            trusted: $this->trusted,
            host: Util::host(),
        );
    }
}

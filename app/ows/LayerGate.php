<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ows;

use app\exceptions\GC2Exception;
use app\inc\BasicAuth;
use app\inc\Cache;
use app\inc\Input;
use app\inc\Model;
use app\inc\PublicIdentity;
use app\models\Authorization;

/**
 * Per-layer authorization for a PublicIdentity — Bearer (Authorization::check with the JWT
 * identity), HTTP Basic (BasicAuth::authenticate, which challenges with 401) or anonymous
 * (allowed for layers below 'Read/write'). Skipped when trusted. A transaction raises the bar:
 * 'Write' layers, readable anonymously, require credentials to transact.
 *
 * Allow decisions for token/Basic identities are cached for AUTH_CACHE_TTL when $cacheAllows is
 * on (WMS tiles hit the same layer many times per second). Anonymous decisions are never cached:
 * a layer just switched to Read/write must stop answering anonymously at once.
 * Extracted from Ows::authorizeLayers.
 */
final class LayerGate
{
    private const int AUTH_CACHE_TTL = 60;

    public function __construct(
        private readonly PublicIdentity $id,
        private readonly bool $cacheAllows = true,
    ) {}

    /** @param list<string> $rels "schema.table" */
    public function authorizeRead(array $rels): void
    {
        $this->authorize($rels, false);
    }

    /**
     * Whether this identity may read the layer, answered as a boolean: never throws for a
     * missing privilege and never challenges with HTTP Basic. For callers that leave a layer
     * out instead of failing the request (the legend API).
     *
     * A layer below 'Read/write' is readable by anyone. A 'Read/write' layer needs a token or
     * verified Basic identity with read privilege; the owner always has it. A relation that is
     * not a registered layer is not readable. Geofence rules are not consulted.
     *
     * @param string $rel "schema.table"
     */
    public function canRead(string $rel): bool
    {
        $auth = new Model(connection: $this->id->connection)->getGeometryColumns($rel, 'authentication');
        if (empty($auth)) {
            return false;
        }
        if ($this->id->trusted || $auth !== 'Read/write') {
            return true;
        }
        if ($this->id->anonymous) {
            return false;
        }
        try {
            new Authorization(connection: $this->id->connection)->check(
                relName: $rel, transaction: false, isAuth: true,
                subUser: $this->id->parentUser ? null : $this->id->user,
                userGroup: $this->id->userGroup, rels: []
            );
        } catch (GC2Exception) {
            return false;
        }
        return true;
    }

    /** @param list<string> $rels "schema.table" */
    public function authorize(array $rels, bool $transaction): void
    {
        if ($this->id->trusted || empty($rels)) {
            return;
        }
        $authUser = Input::getAuthUser();
        $identity = $this->id->bearer
            ? 'j:' . hash('sha256', $this->id->bearer)
            : ($authUser ? 'b:' . hash('sha256', $authUser . ':' . (Input::getAuthPw() ?? '')) : null);
        $model = new Model(connection: $this->id->connection);
        foreach ($rels as $rel) {
            $item = ($this->cacheAllows && $identity !== null)
                ? Cache::getItem($this->id->database . '_owsauth_' . hash('sha256', $identity . '|' . $rel . '|' . ($transaction ? 't' : 'r')))
                : null;
            if ($item !== null && $item->isHit() && $item->get() === true) {
                continue;
            }
            $auth = $model->getGeometryColumns($rel, 'authentication');
            $needsAuth = $auth === 'Read/write'
                || ($transaction && $auth === 'Write')
                || !empty($authUser);
            if ($needsAuth) {
                if ($this->id->bearer) {
                    new Authorization(connection: $this->id->connection)->check(
                        relName: $rel, transaction: $transaction, isAuth: true,
                        subUser: $this->id->parentUser ? null : $this->id->user,
                        userGroup: $this->id->userGroup, rels: []
                    );
                } else {
                    // Verifies credentials (challenges 401 when missing/wrong) and checks per-layer privilege.
                    new BasicAuth(connection: $this->id->connection)->authenticate($rel, $transaction);
                }
            }
            if ($item !== null) {
                $item->set(true)->expiresAfter(self::AUTH_CACHE_TTL);
                Cache::save($item);
            }
        }
    }
}

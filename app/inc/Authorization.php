<?php

namespace app\inc;

use app\models\Geofence;
use app\models\User;
use stdClass;

class Authorization
{

    public function __construct(private readonly stdClass $claims, private readonly User $user, private readonly array $customMap = [])
    {
    }

    public function set(): void
    {
        $this->setAuthorizations();

    }

    /**
     * Sets authorizations for membership, read and write based on the token and custom map.
     */
    public function setAuthorizations(): void
    {
        if (!$this->claims) {
            return;
        }

        foreach ($this->customMap as $path => $authorizations) {

            if ($this->checkPath($path)) {
                $this->applyAuthorizations($authorizations);
            }
        }
    }

    /**
     * Checks if the token contains the claim specified by the path.
     * Path format: "claim1->claim2->value"
     */
    private function checkPath(string $path): bool
    {
        $parts = explode("->", $path);
        $current = $this->claims;
        $count = count($parts);

        foreach ($parts as $i => $part) {
            if (is_object($current) && isset($current->$part)) {
                $current = $current->$part;
            } elseif (is_array($current) && in_array($part, $current)) {
                return true;
            } elseif ($i === $count - 1 && is_scalar($current) && (string)$current === $part) {
                return true;
            } else {
                return false;
            }
        }

        return (bool)$current;
    }

    private function applyAuthorizations(array $authorizations): void
    {

        if (isset($authorizations['__membership'])) {
            codecept_debug(print_r($authorizations, true));

            $this->setMembership($authorizations['__membership']);
        }
        if (isset($authorizations['__read'])) {
            $this->setRead($authorizations['__read']);
        }
        if (isset($authorizations['__write'])) {
            $this->setWrite($authorizations['__write']);
        }
    }

    protected function setMembership(array $privileges): void
    {
        // TODO: Implement membership setter
    }

    protected function setRead(array $privileges): void
    {
        // TODO: Implement read setter
    }

    protected function setWrite(array $privileges): void
    {
        // TODO: Implement write setter
    }
}
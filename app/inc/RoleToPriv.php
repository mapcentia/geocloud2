<?php

namespace app\inc;

use app\models\Geofence;
use app\models\User;

class RoleToPriv
{
    function __construct(private readonly array $roles, private readonly User $user, private readonly Geofence $geofence)
    {

    }

    public function set(): void {
        foreach ($this->roles as $role) {

            // TODO collect privileges from role and add only the most elevated
            // TODO add

            //$this->user->updateUser()
            //$this->geofence->update()

        }

    }

}
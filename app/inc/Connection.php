<?php

namespace app\inc;

final class Connection
{
    /**
     * @author     Martin Høgh <mh@mapcentia.com>
     * @copyright  2013-2025 MapCentia ApS
     * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
     *
     */

    public function __construct(public ?string $host = null,
                                public ?string $user = null,
                                public ?string $password = null,
                                public ?string $database = null,
                                public ?string $port = null,
                                public ?string $schema = null,
    )
    {
    }
}
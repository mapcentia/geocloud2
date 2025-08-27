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
        $this->host = $host ?? \app\conf\Connection::$param['postgishost'];
        $this->port = $port ?? \app\conf\Connection::$param['postgisport'];
        $this->user = $user ?? \app\conf\Connection::$param['postgisuser'];
        $this->database = $database ?? \app\conf\Connection::$param['postgisdb'];
        $this->password = $password ?? \app\conf\Connection::$param['postgispw'];
        $this->schema = $schema ?? \app\conf\Connection::$param['postgisschema'] ?? null;
    }
}
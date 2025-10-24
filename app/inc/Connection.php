<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * This class is used to store the connection parameters for the database.
 * The parameters are read from the environment variables or from the configuration file.
 *
 * On initialization, the parameters can be overridden by passing them as arguments to the constructor.
 *
 * Objects of this class are used to inject into any other class that needs to access the database.
 *
 */

namespace app\inc;

final class Connection
{
    public function __construct(public ?string $host = null,
                                public ?string $user = null,
                                public ?string $password = null,
                                public ?string $database = null,
                                public ?string $port = null,
                                public ?string $schema = null,
                                public ?bool   $pgbouncer = null,
    )
    {
        \app\conf\Connection::$param['postgishost'] = \app\conf\Connection::$param['postgishost'] ?? getenv('POSTGRES_HOST');
        \app\conf\Connection::$param['postgisport'] = \app\conf\Connection::$param['postgisport'] ?? getenv('POSTGRES_PORT') ?? '5432';
        \app\conf\Connection::$param['postgisuser'] = \app\conf\Connection::$param['postgisuser'] ?? getenv('POSTGRES_USER');
        \app\conf\Connection::$param['postgisdb'] = \app\conf\Connection::$param['postgisdb'] ?? getenv('POSTGRES_DB');
        \app\conf\Connection::$param['postgispw'] = \app\conf\Connection::$param['postgispw'] ?? getenv('POSTGRES_PW');
        \app\conf\Connection::$param['pgbouncer'] = \app\conf\Connection::$param['pgbouncer'] ?? getenv('POSTGRES_PGBOUNCER') === "true";

        $this->host = $host ?? \app\conf\Connection::$param['postgishost'];
        $this->port = $port ?? \app\conf\Connection::$param['postgisport'];
        $this->user = $user ?? \app\conf\Connection::$param['postgisuser'];
        $this->database = $database ?? \app\conf\Connection::$param['postgisdb'];
        $this->password = $password ?? \app\conf\Connection::$param['postgispw'];
        $this->pgbouncer = $pgbouncer ?? \app\conf\Connection::$param['pgbouncer'];
        $this->schema = $schema ?? \app\conf\Connection::$param['postgisschema'] ?? 'public';
    }
}
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ows;

use app\inc\Connection;
use app\inc\Model;

final readonly class Context
{
    public function __construct(
        public Connection $connection,
        public string     $database,
        public string     $schema,
        public string     $user,
        public ?array     $userGroup,
        public bool       $parentUser,
        public bool       $trusted,
        public string     $host,
    ) {}

    public function model(): Model
    {
        return new Model(connection: $this->connection);
    }
}

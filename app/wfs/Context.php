<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs;

use app\inc\Connection;
use app\inc\Model;

final class Context
{
    public function __construct(
        public readonly Connection $connection,
        public readonly string $database,
        public readonly string $schema,
        public readonly string $user,
        public readonly bool   $parentUser,
        public readonly bool   $trusted,
        public readonly string $host,
        public readonly string $thePath,
        public readonly float  $startTime,
        public readonly ?int   $srs = null,
    ) {}

    public function model(): Model
    {
        return new Model($this->connection);
    }
}

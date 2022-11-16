<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use sad_spirit\pg_builder\nodes;
use sad_spirit\pg_builder\nodes\range\UpdateOrDeleteTarget;
use sad_spirit\pg_builder\BlankWalker;
use sad_spirit\pg_builder\nodes\range\RelationReference;


class TableWalkerRelation extends BlankWalker
{
    /**
     * @var array<string>
     */
    private array $relations = [];

    public function walkRelationReference(RelationReference $rangeItem): void
    {
        $this->relations[] = (string)$rangeItem->name;
    }

    public function walkUpdateOrDeleteTarget(UpdateOrDeleteTarget $target): void
    {
        $this->relations[] = $target->relation->schema . "." . $target->relation->relation;
    }

    public function walkInsertTarget(nodes\range\InsertTarget $target): void
    {
        $this->relations[] = $target->relation->schema . "." . $target->relation->relation;
    }

    /**
     * @return array<string>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }
}

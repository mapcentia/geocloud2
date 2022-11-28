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
     * @var array<array<string>>
     */
    private array $relations = ["all" => [], "insert" => [], "updateAndDelete" => []];

    public function walkRelationReference(RelationReference $rangeItem): void
    {
        $this->relations["all"][] = (string)$rangeItem->name;
    }

    public function walkUpdateOrDeleteTarget(UpdateOrDeleteTarget $target): void
    {
        $rel =($target->relation->schema ?? "public") . "." . $target->relation->relation;
        $this->relations["all"][] = $rel;
        $this->relations["updateAndDelete"][] = $rel;
    }

    public function walkInsertTarget(nodes\range\InsertTarget $target): void
    {
        $rel =($target->relation->schema ?? "public") . "." . $target->relation->relation;
        $this->relations["all"][] = $rel;
        $this->relations["insert"][] = $rel;
    }

    /**
     * @return array<array<string>>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }
}

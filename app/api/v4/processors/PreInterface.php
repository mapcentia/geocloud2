<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\processors;


use app\inc\Model;

interface PreInterface
{
    public function processAddTable(Model $model, array $body = []) : array;
    public function processAddUser(Model $model, array $body = []) : array;
    public function processImport(Model $model, array $result) : array;
}
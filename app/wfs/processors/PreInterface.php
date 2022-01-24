<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\wfs\processors;


/**
 * Interface PreInterface
 * @package app\conf\processors
 */
interface PreInterface
{
    /**
     * The main function called by the WFS prior to the single UPDATE transaction.
     * @param array<mixed> $arr
     * @param string $typeName
     * @return array<mixed>
     */
    public function processUpdate(array $arr, string $typeName) : array;

    /**
     * The main function called by the WFS prior to the single INSERT transaction.
     * @param array<mixed> $arr
     * @param string $typeName
     * @return array<mixed>
     */
    public function processInsert(array $arr, string $typeName) : array;

    /**
     * The main function called by the WFS prior to the single DELETE transaction.
     * @param array<mixed> $arr
     * @param string $typeName
     * @return array<mixed>
     */
    public function processDelete(array $arr, string $typeName) : array;
}
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
interface PostInterface
{
    /**
     * The main function called by the WFS after all INSERT, UPDATE and DELETE statements are run, but before the final COMMIT.
     * @return array<mixed>
     */
    public function process() : array;
}
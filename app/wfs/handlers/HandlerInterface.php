<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs\handlers;

use app\wfs\Request;
use app\wfs\output\GmlWriter;

interface HandlerInterface
{
    public function handle(Request $req, GmlWriter $writer): void;
}

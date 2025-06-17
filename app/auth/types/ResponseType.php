<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\auth\types;

enum ResponseType: string
{
    case TOKEN = 'token';
    case CODE = 'code';
    case REFRESH = 'refresh';
}

<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;


enum Scope: string
{
    case SUPER_USER_ONLY = 'superUserOnly';
    case SUB_USER_ALLOWED = 'subUserAllowed';
    case PUBLIC = 'public';
}
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

include '../header.php';
session_unset();
?>
<script>window.location = '<?php echo \app\conf\App::$param['userHostName'] ?>/user/login'</script>

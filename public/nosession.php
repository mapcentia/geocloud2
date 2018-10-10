<?PHP
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

$response["success"] = false;
$response["message"] = "Session time out. Refresh browser.";
header('Content-Type: application/json');
header("HTTP/1.0 401 Unauthorized");
echo json_encode($response);
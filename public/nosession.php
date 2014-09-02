<?PHP
$response["success"] = false;
$response["message"] = "Session time out. Refresh browser.";
header('Content-Type: application/json');
header("HTTP/1.0 401 Unauthorized");
echo json_encode($response);
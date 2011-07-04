<?php
switch($_REQUEST['type'])
{
	case "xml":
		$status = $serializer->Serialize($response);
		$xml=$serializer->getSerializedData();
		echo $xml;
	break;
	
	case "json":
		echo $json->encode($response);
	break;
	case "text":
		echo $response;
	break;
	
	default:
		$status = $serializer->Serialize($response);
		$xml=$serializer->getSerializedData();
		echo $xml;
	break;
}
?>

<?php
include '../../../../conf/main.php';
include 'libs/functions.php';
$postgisdb = "mobreg";

$postgisObject = new postgis();

$query="SELECT * FROM public.save_check WHERE irowid={$_REQUEST['id']}";
$result=$postgisObject->execQuery($query);
$rows = $postgisObject->fetchAll($result);
if(!count($rows)) {
	$query2="INSERT INTO public.save_check (irowid,ok) VALUES({$_REQUEST['id']},1)";
}
else {

	if ($rows[0]['ok']==0) {
		$query2="UPDATE public.save_check SET ok=1 WHERE irowid={$_REQUEST['id']}";
	}
	elseif ($rows[0]['ok']==1) {
		$query2="UPDATE public.save_check SET ok=0 WHERE irowid={$_REQUEST['id']}";
		
}
}
echo $query2;
$postgisObject->execQuery($query2);
	 

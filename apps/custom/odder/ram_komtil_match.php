<?php
ini_set("display_errors", "On");
error_reporting(3);
session_start();
include '../../../conf/main.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'libs/FirePHPCore/FirePHP.class.php';
include 'libs/FirePHPCore/fb.php';
include 'model/tables.php';
$postgisdb = $parts[3];
$postgisObject = new postgis();
?>


    	<table border="0" width="100%" height="200" cellpadding="0" cellspacing="0">
    	  <tbody><tr>
		    <td class="boxtopblueleft"><img src="/gifs/apps/holbaektemaplaner/dottrans.gif" width="5px" height="5px"></td>
    		<td class="boxmainblue"><img src="/gifs/apps/holbaektemaplaner/dottrans.gif" width="195px" height="1px"></td>
    		<td class="boxtopblueright"><img src="/gifs/apps/holbaektemaplaner/dottrans.gif" width="5px" height="5px"></td>
    	  </tr>
    	  <tr>
			<td class="boxmainblue"></td>
    	    <td class="boxbluecontent">
    	    <p class="h3">Staus</p>
    	    <div class="blueshade"></div>
    	    <p class="focusheader">
    	    

<?php
$query = "SELECT komtil_id,status FROM kommuneplan.kpplandk2_view WHERE planid='".urldecode($_REQUEST['planid'])."'";
$result = $postgisObject -> execQuery($query,"PG");
$row = pg_fetch_array($result);

echo ucfirst($row['status'])."<br /><br />";

if ($row['komtil_id']) {
	echo "Rammeomr&aring;det er oprettet ved:<br /><br />";
	$query = "SELECT plannavn,html FROM kommuneplan.komtildk2_join WHERE planid='".$row['komtil_id']."'";
	$result = $postgisObject -> execQuery($query,"PG");
	$num_results = pg_numrows($result);
	for ($i=0;$i<$num_results;$i++) {
		$row = pg_fetch_array($result);
		echo "<a href='".$row['html']."'>".$row['plannavn']."</a><br /> ";
	}
}
$query ="SELECT aendringer,plannavn,html FROM kommuneplan.komtildk2_join";
$result = $postgisObject -> execQuery($query,"PG");
$num_results = pg_numrows($result);
for ($i=0;$i<$num_results;$i++) {
	$row = pg_fetch_array($result);
	if ($row['aendringer']){

		$split = explode(",",$row['aendringer']);
		foreach ($split as $value){
			if ($value == $_REQUEST['planid']){
				echo "For dette område er der et tillæg i høring:<br/><br/><a href='".$row['html']."'>".$row['plannavn']."</a>";
			}
		}
	}
}
?>
</td>
			<td class="boxmainblue"></td>
    	  </tr>
    	   <tr>
		    <td class="boxbtmblueleft"></td>
    		<td class="boxmainblue"><img src="/gifs/apps/holbaektemaplaner/dottrans.gif" width="195px" height="1px"></td>
    		<td class="boxbtmblueright"></td>
    	  </tr>
    	</tbody></table>
	
<table border=1>
<tr>
	<td><a href='/apps/custom/save/list.php?formid=115770003400067&schema=rudersdal&orderby=adresse'>Adresse</a></td>
	<td><a href='/apps/custom/save/list.php?formid=115770003400067&schema=rudersdal&orderby=tilstand'>Tilstand</a></td>
	<td><a href='/apps/custom/save/list.php?formid=115770003400067&schema=rudersdal&orderby=bevaring'>Bevaring</a></td>
	<td><a href='/apps/custom/save/list.php?formid=115770003400067&schema=rudersdal&orderby=ok'>OK</td>
	<td>Link</a></td>
	</tr>
<?php
include("html_header.php");
include("controller/list_c.php");
foreach($rows as $row){
	echo "<tr>
	<td>{$row['adresse']}</td>
	<td>{$row['tilstand']}</td>
	<td>{$row['bevaring']}</td>
	<td>{$row['ok']}</td>
	<td><a target='_blank' href='http://beta.mygeocloud.cowi.webhouse.dk/apps/custom/save/show.php?id={$row['irowid']}&formid={$_REQUEST['formid']}&schema={$_REQUEST['schema']}'>{$row['irowid']}</a></td>
	</tr>";
}
?>
</table>
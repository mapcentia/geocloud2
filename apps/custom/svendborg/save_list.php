<table border=1>
<tr>
	<td>Adresse</td>
	<td>Tilstand</td>
	<td>Bevaring</td>
	<td>OK</td>
	<td>Link</td>
	</tr>
<?php
include("html_header.php");
include("controller/save_list_c.php");
foreach($rows as $row){
	echo "<tr>
	<td>{$row['adresse']}</td>
	<td>{$row['tilstand']}</td>
	<td>{$row['bevaring']}</td>
	<td>{$row['ok']}</td>
	<td><a target='_blank' href='/apps/custom/svendborg/show_save.php?id={$row['irowid']}&formid={$_REQUEST['formid']}&schema={$_REQUEST['schema']}'>{$row['irowid']}</a></td>
	</tr>";
}
?>
</table>
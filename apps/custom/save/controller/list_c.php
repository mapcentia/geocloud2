<?php
if (!$_REQUEST["orderby"]) {
	$_REQUEST["orderby"]="adresse";
}
$table = new table("{$_REQUEST['schema']}.tforms{$_REQUEST['formid']}_join");
$table->execQuery("set client_encoding='UTF8'","PDO");
$query="SELECT {$_REQUEST['schema']}.tforms{$_REQUEST['formid']}_join.*,public.save_check.ok FROM {$_REQUEST['schema']}.tforms{$_REQUEST['formid']}_join LEFT JOIN public.save_check ON public.save_check.irowid={$_REQUEST['schema']}.tforms{$_REQUEST['formid']}_join.irowid ORDER by {$_REQUEST['orderby']} LIMIT 100000000";

//echo $query;
$result = $table->execQuery($query);
$rows = $table->fetchAll($result,"assoc");

//print_r($rows);

<?php
$_REQUEST['schema']="svendborg";
$_REQUEST['formid']="115770004835770";
$table = new table("{$_REQUEST['schema']}.tforms{$_REQUEST['formid']}_join");
$table->execQuery("set client_encoding='UFT8'","PDO");
$query="SELECT {$_REQUEST['schema']}.tforms{$_REQUEST['formid']}_join.*,public.save_check.ok FROM {$_REQUEST['schema']}.tforms{$_REQUEST['formid']}_join LEFT JOIN public.save_check ON public.save_check.irowid={$_REQUEST['schema']}.tforms{$_REQUEST['formid']}_join.irowid ORDER by public.save_check.irowid, {$_REQUEST['schema']}.tforms{$_REQUEST['formid']}_join.adresse LIMIT 100000000";


$result = $table->execQuery($query);
$rows = $table->fetchAll($result,"assoc");

//print_r($rows);

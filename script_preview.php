<?php 
require_once 'script_funcs.php';

$file = $_GET['file'];
$result = ['defines' => [], 'procedures' => []];
recursiveParseScriptDefines($file, $result);
header('Content-Type: application/json; charset=utf-8;');
echo json_encode($result);
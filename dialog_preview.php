<?php 
require_once 'script_funcs.php';

$file = $_GET['file'];
$filename = ROOT_DIALOGS_PATH . '/' . $file;
$result = [];
if(file_exists($filename)){
	$dialog = parseDialogMsg(file_get_contents($filename));
	foreach($dialog as $msg){
		list($line, $id, $lip, $text) = $msg;
		$result[$id] = str_replace("\n", ' ', $text);
	}
}else{
	$result = false;
}
//recursiveParseScriptDefines($file, $result);
header('Content-Type: application/json; charset=utf-8;');
echo json_encode($result);
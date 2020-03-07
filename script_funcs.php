<?php
const ROOT_SCRIPTS_PATH = 'scripts_src/frp';
const ROOT_DIALOGS_PATH = 'dialogs/frp';

function recursiveParseScriptDefines($filename, &$result){
	if(file_exists(ROOT_SCRIPTS_PATH . '/' . $filename)){
		$content = file_get_contents(ROOT_SCRIPTS_PATH . '/' . $filename);
		$script_info = processScript($content);
		

		//echo '<pre>';
		//var_dump($script_info);
		//echo '</pre>';

		$cur_path = explode('/', $filename);
		array_pop($cur_path);
		$cur_path = join('/', $cur_path);
		$rep_path = realpath(ROOT_SCRIPTS_PATH);
		foreach(array_keys($script_info['includes']) as $include_file){
			$full_path = realpath(ROOT_SCRIPTS_PATH . '/' . $cur_path . '/' . $include_file);
			$full_path = str_replace($rep_path . '/', '', $full_path);
			recursiveParseScriptDefines($full_path, $result);
		}

		//unset($script_info['procedures']);
		//unset($script_info['includes']);
		//unset($script_info['lines_num']);
		//unset($script_info['msg_files']);
		//$result[$filename] = $script_info['defines'];
		$t = [];
		foreach($script_info['defines'] as $define_name => $define_val){
			if(strpos($define_name, '(') !== false || strpos($define_val['value'], "\n") !== false){
				//continue;
			}

			if(preg_match('@^([^\(]+)\(@', $define_name, $matches)){
				$define_name = $matches[1];
			}

			$t[$define_name] = [$filename, $define_val['line'], $define_val['value']];
		}
		$result['defines'] = array_merge($result['defines'], $t);

		$t = [];
		foreach($script_info['procedures'] as $procedure_name => $procedure_val){
			$t[$procedure_name] = [$filename, $procedure_val['procedure_body_line']];
		}
		$result['procedures'] = array_merge($result['procedures'], $t);
	}else{
		throw new Exception('Filename ' . $filename . ' does not exist. Cannot parse.');
	}
}

/**
 * Parse script text and return procedures, includes, defines, variables and msg_file references
 *
 * @param string $content Script text
 * @param boolean $dump Display level debug info
 * 
 * @return array Script info
 */
function processScript(string $content, bool $dump = false) : array {
	//$orcont = $content;
	$content = explode("\n", $content);
	
	$procedures = [];
	$includes = [];
	$defines = [];
	$variables = [];
	$msg_files = [];

	$multiline = false;
	$cur_define = false;
	$multiline_offset = false;
	$comment_opened = false;

	$level = 0;
	$procedure_open = false;
	

	if($dump){
		echo '<pre>';
	}
	foreach($content as $i => $line){

		if(preg_match('@^\s*/\*[^\*]*$@', $line, $matches)){
			$comment_opened = true;
		}elseif(preg_match('@^.*\*/[^\*]*$@', $line, $matches)){
			$comment_opened = false;
		}elseif(preg_match('@^\s*/\*.*\*/$@', $line, $matches)){
			continue;
		}elseif(preg_match('@^\s*//@', $line, $matches)){
			continue;
		}

		if(!$comment_opened){
			//echo "$i. $line\n";
			if($multiline){
				//$mline = trim($line);
				$mline = substr($line, $multiline_offset);
				if(substr($line, -1) === '\\'){
					$defines[$cur_define]['value'] .= "\n" . substr($mline, 0, -1);
				}else{
					$defines[$cur_define]['value'] .= "\n" . $mline;
					$cur_define = false;
					$multiline = false;
					$multiline_offset = false;
				}
			}else{
				if(preg_match('@^procedure (.+?);@', $line, $matches)){
					$procedures[$matches[1]] = [
						'prototype_line' => $i,
						'procedure_body' => false,
						'procedure_body_line' => false,
						'procedure_body_line_end' => false,
					];
				}elseif(preg_match('@^procedure (.+?) begin@', $line, $matches)){
					if($dump){
						echo str_repeat("\t", $level) . "<a href=\"#line_$i\">$i</a>|[$level] { : $line<br>";
					}
					$level++;

					$procedure_open = $matches[1];
					if(array_key_exists($matches[1], $procedures)){
						$procedures[$matches[1]]['procedure_body_line'] = $i;
					}else{
						$procedures[$matches[1]] = [
							'prototype_line' => false, 
							'procedure_body' => false,
							'procedure_body_line' => $i,
							'procedure_body_line_end' => false,
						];
					}
				}elseif(preg_match('@^procedure (.+)(begin){0}@', $line, $matches)){
					$nextline = $content[$i + 1];
					if($nextline !== 'begin'){
						echo "MISSING PROCEDURE BEGIN FOR LINE $i";
						//exit;
					}

					if($dump){
						echo str_repeat("\t", $level) . "<a href=\"#line_$i\">$i</a>|[$level] !{ : $line<br>";
					}
					//$level++;

					//var_dump($matches);
					//exit;

					$procedure_open = $matches[1];
					if(array_key_exists($matches[1], $procedures)){
						$procedures[$matches[1]]['procedure_body_line'] = $i;
					}else{
						$procedures[$matches[1]] = [
							'prototype_line' => false, 
							'procedure_body' => false,
							'procedure_body_line' => $i,
							'procedure_body_line_end' => false,
						];
					}
				}elseif(preg_match('@^#define\s*([\S]+)\s*\s*(//.+)?$@', $line, $matches)){
					//#define LVAR_Herebefore
					//array_shift($matches);
					
					$comment = '';
					if(count($matches) === 4){
						$comment = $matches[3];
					}
					$defines[$matches[1]] = [
						'line' => $i,
						'value' => '',
						'comment' => $comment
					];
				}elseif(preg_match('@^#define\s*(?<def_name>(\S+\([^\)]+\))|([\S]+))\s*(?<def_val>.+?)\s*(?:(?<cmt>(//.+)|(/\*.+))?|(?<nl>\\\))$@s', $line, $matches, PREG_OFFSET_CAPTURE)){
					//#define WEARING_MKII        (obj_pid(critter_inven_obj(dude_obj,INVEN_TYPE_WORN)) == PID_ADVANCED_POWER_ARMOR_MK2)
					//#define LVAR_Herebefore                 (4)
					//array_shift($matches);
					//var_dump($matches);
					//echo '<hr>';
					
					//Multiline define
					if(substr($matches[0][0], -1) === "\\"){
						//echo '<pre>';
						//var_dump($matches);
						//echo '</pre><hr>';
						$matches['nl'] = true;
						if($matches['def_val'][0] === '\\'){
							$matches['def_val'][0] = '';
							$matches['def_val'][1] = 0;
						
							$nextline = $content[$i + 1];
							$offset_found = false;
							for($j = 0; $j < strlen($nextline); $j++){
								if(substr($nextline, $j, 1) !== ' '){
									$offset_found = $j;
									break;
								}
							}
							if($offset_found){
								$matches['def_val'][1] = $offset_found;
							}
						}
						//var_dump($matches);
						//echo '<hr>';
					}

					$comment = '';
					if(array_key_exists('cmt', $matches) && $matches['cmt'] !== ''){
						$comment = $matches['cmt'][0];
					}

					if(array_key_exists('nl', $matches)){
						$cur_define = $matches['def_name'][0];
						$multiline = true;
						$multiline_offset = $matches['def_val'][1];
					}

					$value = $matches['def_val'][0];
					if(substr($value, 0, 1) === '(' && substr($value, -1, 1) === ')'){
						$value = substr($value, 1, -1);
					}
					
					$defines[$matches['def_name'][0]] = [
						'line' => $i,
						'value' => $value,
						'comment' => $comment
					];

				}elseif(preg_match('@^(?<scope>import|export)?\s*variable\s*(?<name>[^\s:]+)(?:\s*:=\s*(?<val>.+?))?;\s*(?<cmt>(//.+)|(/\*.+))?$@', $line, $matches)){
					//#define WEARING_MKII        (obj_pid(critter_inven_obj(dude_obj,INVEN_TYPE_WORN)) == PID_ADVANCED_POWER_ARMOR_MK2)
					//#define LVAR_Herebefore                 (4)
					//array_shift($matches);
					$scope = '';
					if(array_key_exists('scope', $matches) && $matches['scope'] !== ''){
						$scope = $matches['scope'];
					}
					$comment = '';
					if(array_key_exists('cmt', $matches) && $matches['cmt'] !== ''){
						$comment = $matches['cmt'];
					}
					$value = '';
					if(array_key_exists('val', $matches) && $matches['val'] !== ''){
						$value = $matches['val'];
					}
					$variables[$matches['name']] = [
						'line' => $i,
						'scope' => $scope,
						'value' => $value,
						'comment' => $comment
					];
				}elseif(preg_match('@^#include "(.+?)"@', $line, $matches)){
					//#include "../headers/define.h"
					//$inc_file = str_replace('../', '', $matches[1]);
					$inc_file = $matches[1];
					$includes[$inc_file] = $i;
				}elseif(preg_match('@\SSCRIPT_(.+?),@', $line, $matches)){
					//display_msg(message_str(SCRIPT_ABBEY, 100));
					$msg_file = strtolower($matches[1]);
					$msg_files[$msg_file] = [$matches[1], $i];
				}elseif(preg_match('@^(\s*)?(\*/\s*)?end.*begin\s*(?<cmt>(//.+)|(/\*.+))?$@', $line, $matches)){
					if($dump){
						echo str_repeat("\t", $level) . "<a href=\"#line_$i\">$i</a>|[$level] )( : $line<br>";
					}
				}elseif(preg_match('@^(\s*)?(\*/\s*)?end(\s+.*)?\s*(?<cmt>(//.+)|(/\*.+))?$@', $line, $matches)){
					$level--;
					if($level > 0){
						if($dump){
							echo str_repeat("\t", $level) . "<a href=\"#line_$i\">$i</a>|[$level] ) : $line<br>";
						}
					}else{
						if($dump){
							echo str_repeat("\t", $level) . "<a href=\"#line_$i\">$i</a>|[$level] } : $line<br>";
						}
					}
					if($procedure_open && $level === 0){
						$procedures[$procedure_open]['procedure_body_line_end'] = $i;
						
						$line_start = $procedures[$procedure_open]['procedure_body_line'];
						$line_end = $procedures[$procedure_open]['procedure_body_line_end'] + 1;
						
						$procedures[$procedure_open]['procedure_body'] = join("\n", array_slice($content, $line_start, $line_end - $line_start));

					}
				}elseif(preg_match('@^(\s*)?(\*/\s*)?.*begin\s*(?<cmt>(//.+)|(/\*.+))?$@', $line, $matches)){
					if($dump){
						echo str_repeat("\t", $level) . "<a href=\"#line_$i\">$i</a>|[$level] ( : $line<br>";
					}
					$level++;
				}
			}
		}
	}

	if($dump){
		echo '</pre>';
	}

	uasort($procedures, function($a, $b){ return $a['procedure_body_line'] <=> $b['procedure_body_line']; });
	asort($includes);
	asort($msg_files);
	uasort($defines, function($a, $b){ return $a['line'] <=> $b['line']; });
	uasort($variables, function($a, $b){ return $a['line'] <=> $b['line']; });
	
	
	//echo '<pre>';
	//echo $orcont . '<hr>';
	//var_dump($defines);
	//echo '</pre>';
	//exit;
	
	return [
		'lines_num' => count($content),
		'procedures' => $procedures,
		'includes' => $includes,
		'msg_files' => $msg_files,
		'defines' => $defines,
		'variables' => $variables,
	];
}

function parseDialogMsg($dialog){
	//id, lip, message
	$preg = '@{(\d+)}{(.*?)}{(.*?)}@is';
	if(preg_match_all($preg, $dialog, $matches, PREG_SET_ORDER)){
		return $matches;
	}
	
	echo '<p>Unable to parse file. Dumping...</p><pre>' . $dialog . '</pre>';

	return [];
}

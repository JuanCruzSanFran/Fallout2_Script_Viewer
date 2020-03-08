<?php
function readScriptsDir($root_dir){
	$scripts = [];

	$dir_it = new RecursiveDirectoryIterator($root_dir);
	$it = new \RecursiveIteratorIterator($dir_it);
	/**
	 * @var SplFileInfo $item
	 */
	foreach($it as $item){ 
		//if($item->isDir() || $item->getFilename() == '.DS_Store') continue;
		//if($item->isDir()) continue;
		if($item->getFilename() !== '.' && $item->getFilename() !== '..' && $item->getFilename() !== '.DS_Store'){
			$path = str_replace($root_dir, '', $item->getPath());
			if(!array_key_exists($path, $scripts)){
				$scripts[$path] = [];
			}
			$scripts[$path][] = str_replace($root_dir . $path . '/', '', $item->getPathname());
		}
	}

	ksort($scripts);

	return $scripts;
}
require_once 'script_funcs.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<link rel="icon" href="fallout.ico">
	<title>Script Parser</title>
	<style>
		::-webkit-scrollbar {
			height: 6px;
			width: 8px;
			background: #292929;
		}

		::-webkit-scrollbar-thumb {
			background: #444;
			-webkit-border-radius: 1ex;
			-webkit-box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.75);
		}

		::-webkit-scrollbar-corner {
			background: #000;
		}

		body {
			background-color: #222;
			color: #efefef;
			padding: 10px;
			/*
			overflow-x: hidden;
			*/
		}

		body.busy {
			cursor: wait;
		}

		a {
			color: orange;
			font-weight: bold;
			text-decoration: none;
		}
		a:hover {
			text-decoration: underline;
		}

		div.proc_body {
			display: flex;
		}

		pre {
			white-space: pre-wrap;
			font-family: monaco;
			font-size: 11px;
		}

		code.c {
			white-space: pre;
			font-family: inherit;
		}

		pre.inline_pre {
			margin: 0;
		}

		pre.inline_code {
			background-color: #333;
			display: inline-block;
			
			/*
			border: 1px solid #444;
			padding: 5px;
			*/
		}
		pre.inline_code code {
			padding-right: 10px;
		}

		pre.lines {
			float: left;
			padding: 0.5em;
			text-align: right;
			background-color: #111;
			pointer-events: none;
		}

		.text {
			font-family: 'JH_Fallout';
			font-size: 8px;
		}

		span.main {
			color: limegreen;
		}

		span.nomain {
			color: deepskyblue;
		}

		li.dir h3 {
			text-decoration: underline;
		}

		abbr {
			cursor: help;
			white-space: nowrap;
		}
		
		a.abbr {
			cursor: alias;
			border-bottom: 1px dotted;
		}

		a.abbr:hover {
			text-decoration: none;
		}
	</style>
</head>
<body>
	<?php
	$script_names = [];
	$script_list = explode("\r\n", file_get_contents(ROOT_SCRIPTS_PATH . '/scripts.lst'));
	foreach($script_list as $line){
		//eplkr.int       ; Script controlling the lockers holding the NPC's belongings (EPA)   # local_vars=5
		if(preg_match('@^(.+?)\.int\s*;\s*(.+?)\s*#\s*local_vars=(\d+)\s*$@', $line, $matches)){
			list(, $script_name, $script_desc, $local_vars) = $matches;
			$script_names[strtolower($script_name)] = [$script_desc, $local_vars];
		}else{
			echo 'UNABLE TO PARSE SCRIPT LIST LINE: ' . $line . '<br>';
		}
	}
	//echo '<pre>';
	//var_dump($script_names);
	//echo '</pre>';
	$msg_found = false;

	if(!array_key_exists('file', $_GET)){ ?>
		<ul>
		<?php foreach(readScriptsDir(ROOT_SCRIPTS_PATH) as $dir => $files){  sort($files); ?>
			<li class="dir"><h3><?php echo $dir; ?></h3>
				<ul class="files">
				<?php foreach($files as $file){ 
					$t = explode('/', $file);
					$t = end($t);
					$script_name = str_replace('.ssl', '', $t);
					if(array_key_exists($script_name, $script_names)){
						list($script_desc, $local_vars) = $script_names[$script_name];
						$desc = " &mdash; <em>$script_desc</em>";
					}else{
						$desc = '';
					}
				?>
					<li class="file"><a href="?file=<?php echo $dir . '/' . $file; ?>"><?php echo $file; ?></a><?php echo $desc; ?></li>
				<?php } ?>
				</ul>
			</li>
		<?php } ?>
		</ul>
	<?php 
	}else{ 
		$file = $_GET['file'];
		if(file_exists(ROOT_SCRIPTS_PATH . '/' . $file)){
			$contents = file_get_contents(ROOT_SCRIPTS_PATH . '/' . $file);

			$t = explode('/', $file);
			$t = end($t);
			if(substr($t, -4) === '.ssl'){
				$msg_file = str_replace('.ssl', '.msg', $t);
				
				$msg_found = false;
				if(file_exists(ROOT_DIALOGS_PATH . '/' . $msg_file)){
					//echo 'Found same-named dialog: '. $msg_file . '<br>';
					$msg_found = true;
				}else{
					$preg = '@#define NAME\s+SCRIPT_(.+)\b@';
					if(preg_match($preg, $contents, $matches)){
						$msg_file = $matches[1] . '.msg';
						if(file_exists(ROOT_DIALOGS_PATH . '/' . $msg_file)){
							//echo 'Found define named dialog: '. $msg_file . '<br>';
							$msg_found = true;
						}
					}else{
						//echo 'NO MSG FILE FOUND, NO SCRIPT NAME FOUND. WEEEEEIRD<br>';
					}
				}
			}

			$script_info = processScript($contents);
			
			$t = explode('/', $file);
			$t = end($t);
			$script_name = str_replace('.ssl', '', $t);
		?>
			<link rel="stylesheet" href="hljs/styles/gruvbox-dark.css">
			<script src="hljs/highlight.min.js"></script>
			<script src="script_defines.js"></script>
			<script>
				const script_filename = '<?php echo $file; ?>';
				
				let script_defines;
				let script_procedures;
				let dialogs;

				window.addEventListener('load', function(){
					parseScript();
				});

				function parseScript(){
					document.body.className = 'busy';

					<?php if($msg_found){ ?>
						const msg_file = '<?php echo $msg_file; ?>';
						const macros = [
							/(mstr)\((\d+)/gi,
							/(display_mstr)\((\d+)/gi,
							/(floater)\((\d+)/gi,
							/(goption)\((\d+)/gi,
							/(noption)\((\d+)/gi,
							/(boption)\((\d+)/gi,
							/(glowoption)\((\d+)/gi,
							/(nlowoption)\((\d+)/gi,
							/(blowoption)\((\d+)/gi,
							/(gmessage)\((\d+)/gi,
							/(nmessage)\((\d+)/gi,
							/(bmessage)\((\d+)/gi,
							/(reply)\((\d+)/gi,
						];
						//@TODO: Dialog procedures are just text nodes, might as well replace them
						loadData(msg_file, script_filename, (dialog, script) => {
							dialogs = dialog;
							for(let block of document.querySelectorAll('pre code')){
								let res = block.innerHTML;
								
								for(let reg of macros){
									res = res.replace(reg, '$1({%$2%}');
								}

								if(res != block.innerHTML){
									console.log('Dialog replacements made');
									block.innerHTML = res;
								}
							}

							renderScript(script);

							window.setTimeout(() => {
								for(let el of document.querySelectorAll('span.hljs-number')){
									let prev = el.previousSibling;
									let prevText = prev.textContent;
									let next = el.nextSibling;
									let nextText = next.textContent;
									if(prevText.indexOf('{%') !== -1 && nextText.indexOf('%}') !== -1){
										prev.textContent = prevText.replace('{%', '');
										next.textContent = nextText.replace('%}', '');

										let id = parseInt(el.textContent);
										if(dialog.hasOwnProperty(id)){
											let text = htmlspecialchars(dialog[id], 'ENT_QUOTES');
											el.innerHTML = '<abbr title="' + id + '">"' + text + '"</abbr>';
										}
									}
								}
							}, 0)
						});
					<?php }else{ ?>

						getDefines(script_filename, renderScript);
					<?php } ?>
				};


			</script>
			<a href="script_parse.php">&lt;&lt; Load another script</a><br>
			<a href="scripts_src/BIS_help.html" target="_blank">? Script docs</a><br>
			<h1><?php echo $file; ?></h1>
			<div id="buttons">
				<button onclick="highlight(); this.remove();">Highlight</button>
				<button onclick="parseScript(); this.remove();">Parse script & highlight</button>
			</div>
			<?php
			if(array_key_exists($script_name, $script_names)){
				list($script_desc, $local_vars) = $script_names[$script_name];
				echo "<h3>$script_desc</h3><tt>Local vars: $local_vars</tt><br>";
			}else{
				echo 'No named script<br>';
			}

			echo '<h1>Contents</h1>';
			echo '<ul>';
			if(count($script_info['includes'])){
				echo '<li><a href="#includes">Includes</a></li>';
			}
			if(count($script_info['msg_files'])){
				echo '<li><a href="#msg_files">MSG Files</a></li>';
			}
			if(count($script_info['procedures'])){
				echo '<li><a href="#procedures">Procedures</a></li>';
			}
			if(count($script_info['defines'])){
				echo '<li><a href="#defines">Defines</a></li>';
			}
			if(count($script_info['variables'])){
				echo '<li><a href="#variables">Variables</a></li>';
			}
			echo '<li><a href="#lines">Script body</a></li>';
			echo '</ul>';

			//echo '<pre>';
			//var_dump($script_info);
			//echo '</pre>';
			////////////////////////////////////////////////
			if(count($script_info['includes'])){
				echo '<h2 id="includes">Includes</h2>';
				echo '<ul>';
				$cur_path = explode('/', $_GET['file']);
				array_pop($cur_path);
				$cur_path = join('/', $cur_path);
				$rep_path = realpath(ROOT_SCRIPTS_PATH);
				
				foreach($script_info['includes'] as $include_file => $include_line){
					$full_path = realpath(ROOT_SCRIPTS_PATH . '/' . $cur_path . '/' . $include_file);
					$full_path = str_replace($rep_path . '/', '', $full_path);
					//if($inc_file)
					echo '<li>[<a href="#line_' . $include_line . '">#</a>] <a target="_blank" href="script_parse.php?file=' . $full_path . '">' . $include_file . '</a></li>';
				}
				echo '</ul>';
			}
			////////////////////////////////////////////////
			if(count($script_info['msg_files'])){
				echo '<h2 id="msg_files">MSG Files</h2>';
				echo '<ul>';
				foreach($script_info['msg_files'] as $msg_inc_file => $msg_info){
					list($msg_filename, $msg_line) = $msg_info;
					echo '<li>[<a href="#line_' . $msg_line . '">#</a>] <a target="_blank" href="dialog_parse.php?file=' . $msg_inc_file . '.msg">' . $msg_inc_file . '</a></li>';
				}
				echo '</ul>';
			}
			////////////////////////////////////////////////
			if(count($script_info['procedures'])){
				echo '<h2 id="procedures">Procedures</h2>';
				echo '<ul>';
				foreach($script_info['procedures'] as $procedure_name => $procedure_info){
					$prototype_line = $procedure_info['prototype_line'];
					$procedure_body_line = $procedure_info['procedure_body_line'];
					$procedure_body_line_end = $procedure_info['procedure_body_line_end'];
					$procedure_body = $procedure_info['procedure_body'];

					echo '<li>';
					if($prototype_line){
						echo '[<a href="#line_' . $prototype_line . '">#</a>] ';
					}
					if($procedure_body_line){
						if($procedure_body_line_end){
							echo '<a href="#line_' . $procedure_body_line . '">' . $procedure_name . '</a> (' . $procedure_body_line . ' - ' . $procedure_body_line_end . ')';
						}else{
							echo '<a href="#line_' . $procedure_body_line . '">' . $procedure_name . '</a>';
						}

						if($procedure_body){
							echo '<br>';
							echo '<div class="proc_body">';
							//$define_lines_count = substr_count($procedure_body, "\n") + 1;
							echo '<pre class="lines" style="margin-top: 10px;">';
							for($j = $procedure_body_line; $j <= $procedure_body_line_end; $j++){
								echo '<div>' . $j . '</div>';
							}
							echo '</pre>';
							//
							$html_procedure_body = htmlspecialchars($procedure_body, ENT_QUOTES);
							echo '<pre class="inline_code" style="width: 100%"><code class="c">' . $html_procedure_body . '</code></pre>';
							echo '</div>';
						}
					}else{
						echo $procedure_name;
					}
					echo '</li>';
					
				}
				echo '</ul>';
			}
			////////////////////////////////////////////////
			if(count($script_info['defines'])){
				echo '<h2 id="defines">Defines</h2>';
				echo '
				<table border="1">
					<thead>
						<tr>
							<th>Line</th>
							<th>Name</th>
							<th>Value</th>
							<th>Comment</th>
						</tr>
					</thead>
					<tbody>
				';
				foreach($script_info['defines'] as $define_name => $define_info){
					$define_line = $define_info['line'];
					$define_value = $define_info['value'];
					$define_comment = $define_info['comment'];
					$isMultiline = strpos($define_value, "\n") !== false;
					
					echo '<tr>';
					echo '	<td><a href="#line_' . $define_line . '">' . $define_line . '</a></td>';
					echo '	<td><pre class="inline_pre">' . htmlspecialchars($define_name, ENT_QUOTES) . '</pre></td>';
					if($isMultiline){
						if(substr($define_comment, -1) === '\\'){
							$define_comment = substr($define_comment, 0, -1);
						}
						echo '	<td>';
						$define_lines_count = $define_line + substr_count($define_value, "\n");
						echo '<pre class="inline_pre lines">';
						for($j = $define_line; $j <= $define_lines_count; $j++){
							echo '<div>' . $j . '</div>';
						}
						echo '</pre>';
						echo '<pre class="inline_pre"><code class="c">';
						echo htmlspecialchars(trim($define_value), ENT_QUOTES);
						echo '</code></pre>';
						echo '</td>';
					}else{
						echo '	<td><pre class="inline_pre">';
						echo htmlspecialchars($define_value, ENT_QUOTES);
						echo '</pre></td>';
					}
					//var_dump(htmlspecialchars($define_value, ENT_QUOTES));
					echo '	<td><pre class="inline_pre">';
					echo htmlspecialchars($define_comment, ENT_QUOTES);
					echo '</pre></td>';
					echo '</tr>';
					
				}
				echo '
					</tbody>
				</table>';
			}
			////////////////////////////////////////////////
			if(count($script_info['variables'])){
				echo '<h2 id="variables">Variables</h2>';
				echo '
				<table border="1">
					<thead>
						<tr>
							<th>Line</th>
							<th>Name</th>
							<th>Value</th>
							<th>Comment</th>
							<th>Scope</th>
						</tr>
					</thead>
					<tbody>
				';
				foreach($script_info['variables'] as $variable_name => $variable_info){
					$variable_line = $variable_info['line'];
					$var_scope = $variable_info['scope'];
					$variable_value = $variable_info['value'];
					$variable_comment = $variable_info['comment'];

					
					echo '<tr>';
					echo '	<td><a href="#line_' . $variable_line . '">' . $variable_line . '</a></td>';
					echo '	<td><pre class="inline_pre">' . htmlspecialchars($variable_name, ENT_QUOTES) . '</pre></td>';
					echo '	<td><pre class="inline_pre">' . htmlspecialchars($variable_value, ENT_QUOTES) . '</pre></td>';
					echo '	<td><pre class="inline_pre">' . htmlspecialchars($variable_comment, ENT_QUOTES) . '</pre></td>';
					echo '	<td>' . $var_scope . '</td>';
					echo '</tr>';
					
				}
				echo '
					</tbody>
				</table>';
			}
			////////////////////////////////////////////////
			
			echo '<br><br>';
			////////////////////////////////////////////////
			?>
			<pre id="lines" class="lines"><?php for($i = 0; $i < $script_info['lines_num']; $i++){
				echo '<div id="line_' . $i . '">' . $i . '</div>';
			}?></pre>
			<pre><code class="c"><?php echo $contents; ?></code></pre>
			<?php

				if($msg_found){
					$dialog = file_get_contents(ROOT_DIALOGS_PATH . '/' . $msg_file);
					$dialogs = parseDialogMsg($dialog);
				?>
					<hr>
					<table border="1">
					<thead>
					<tr>
						<th>ID</th>
						<th>Lip</th>
						<th>Text</th>
					</tr>
					</thead>
					<tbody>
					<?php foreach($dialogs as $line){ 
						list($line, $id, $lip, $text) = $line;
						?>
					<tr>
						<td><?php echo $id; ?></td>
						<td><?php echo $lip; ?></td>
						<td class="text"><?php echo $text; ?></td>
					</tr>
					<?php } ?>
					</tbody>
					</table>
				<?php
				}


		}else{
			echo 'File not found';
		}
	} ?>
</body>
</html>
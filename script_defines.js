async function loadData(msg_file, script_filename, callback){
	let response = await fetch('dialog_preview.php?file=' + msg_file)
	const dialog = await response.json();
	response = await fetch('script_preview.php?file=' + script_filename)
	const script = await response.json();

	callback(dialog, script);
}

function getDefines(script_filename, callback){
	fetch('script_preview.php?file=' + script_filename)
	.then(function(response) {
		return response.json();
	}).then(function(json) {
		callback(json);
	}).catch(function(ex) {
		console.log('parsing failed', ex);
	});
}

function highlight(){
	for(let block of document.querySelectorAll('pre code')){
		hljs.highlightBlock(block);
	}
}

function renderScript(script){
	script_defines = script.defines;
	script_procedures = script.procedures;
	highlight();
	
	window.setTimeout(() => {
		
		for(let block of document.querySelectorAll('pre code')){
			let res = block.innerHTML;
			for(let define_name in script_defines){
				let [define_file, define_line, define_val] = script_defines[define_name];
				if(define_file !== script_filename){
					const reg = new RegExp('\\b' + define_name + '(\\b|[\(\)])', 'g');
					//@TODO: Maybe check if there's already an abbr inside before replacement?
					let fixed_name = htmlspecialchars(define_name, 'ENT_QUOTES');
					let fixed_val = htmlspecialchars(define_val, 'ENT_QUOTES');
					res = res.replace(reg, '<a class="abbr" href="script_parse.php?file=' + define_file + '#line_' + define_line + '" target="_blank" title="' + fixed_val + '">' + fixed_name + '</a>');
				}
			}
			
			//debugger;
			for(let procedure_name in script_procedures){
				let [procedure_file, procedure_line] = script_procedures[procedure_name];
				const reg = new RegExp('\\b' + procedure_name + '(\\b|[\(\)])', 'g');
				
				//@TODO: Maybe check if there's already an abbr inside before replacement?
				let fixed_val = htmlspecialchars(procedure_name, 'ENT_QUOTES');
				if(procedure_file !== script_filename){
					res = res.replace(reg, '<a class="abbr" href="script_parse.php?file=' + procedure_file + '#line_' + procedure_line + '" target="_blank" title="' + procedure_file + ':' + procedure_line + '">' + fixed_val + '</a>');
				}else{
					res = res.replace(reg, '<a class="abbr" href="#line_' + procedure_line + '" title=":' + procedure_line + '">' + fixed_val + '</a>');
				}
			}
			
			if(res != block.innerHTML){
				console.log('Replacements made');
				block.innerHTML = res;
			}
		}
		document.body.className = '';
	}, 0);
}

function htmlspecialchars(string, quote_style, charset, double_encode) {
	//       discuss at: http://phpjs.org/functions/htmlspecialchars/
	//      original by: Mirek Slugen
	//      improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
	//      bugfixed by: Nathan
	//      bugfixed by: Arno
	//      bugfixed by: Brett Zamir (http://brett-zamir.me)
	//      bugfixed by: Brett Zamir (http://brett-zamir.me)
	//       revised by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
	//         input by: Ratheous
	//         input by: Mailfaker (http://www.weedem.fr/)
	//         input by: felix
	// reimplemented by: Brett Zamir (http://brett-zamir.me)
	//             note: charset argument not supported
	//        example 1: htmlspecialchars("<a href='test'>Test</a>", 'ENT_QUOTES');
	//        returns 1: '&lt;a href=&#039;test&#039;&gt;Test&lt;/a&gt;'
	//        example 2: htmlspecialchars("ab\"c'd", ['ENT_NOQUOTES', 'ENT_QUOTES']);
	//        returns 2: 'ab"c&#039;d'
	//        example 3: htmlspecialchars('my "&entity;" is still here', null, null, false);
	//        returns 3: 'my &quot;&entity;&quot; is still here'
  
	var optTemp = 0,
	  i = 0,
	  noquotes = false;
	if (typeof quote_style === 'undefined' || quote_style === null) {
	  quote_style = 2;
	}
	string = string.toString();
	if (double_encode !== false) {
	  // Put this first to avoid double-encoding
	  string = string.replace(/&/g, '&amp;');
	}
	string = string.replace(/</g, '&lt;')
	  .replace(/>/g, '&gt;');
  
	var OPTS = {
	  'ENT_NOQUOTES'          : 0,
	  'ENT_HTML_QUOTE_SINGLE' : 1,
	  'ENT_HTML_QUOTE_DOUBLE' : 2,
	  'ENT_COMPAT'            : 2,
	  'ENT_QUOTES'            : 3,
	  'ENT_IGNORE'            : 4
	};
	if (quote_style === 0) {
	  noquotes = true;
	}
	if (typeof quote_style !== 'number') {
	  // Allow for a single string or an array of string flags
	  quote_style = [].concat(quote_style);
	  for (i = 0; i < quote_style.length; i++) {
		// Resolve string input to bitwise e.g. 'ENT_IGNORE' becomes 4
		if (OPTS[quote_style[i]] === 0) {
		  noquotes = true;
		} else if (OPTS[quote_style[i]]) {
		  optTemp = optTemp | OPTS[quote_style[i]];
		}
	  }
	  quote_style = optTemp;
	}
	if (quote_style & OPTS.ENT_HTML_QUOTE_SINGLE) {
	  string = string.replace(/'/g, '&#039;');
	}
	if (!noquotes) {
	  string = string.replace(/"/g, '&quot;');
	}
  
	return string;
}
<?php

// LICENSE: Attribution-ShareAlike 3.0 Unported (CC BY-SA 3.0)

// config

define('PASTE_FOLDER', __DIR__);
define('LIST_AMOUNT', 20);
error_reporting(E_ALL|E_STRICT);

// helpers

function permalink($id = null) {
	$protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
	$uri = $protocol.$_SERVER['HTTP_HOST'].rtrim($_SERVER['PHP_SELF'], '/');
	$uri = preg_replace('~/index.php$~', null, $uri);
	return "$uri/$id";
}

function time_ago($timestamp) {
	$time = time() - $timestamp;

	$tokens = array (
		31536000 => 'year',
		2592000 => 'month',
		604800 => 'week',
		86400 => 'day',
		3600 => 'hour',
		60 => 'minute',
		1 => 'second'
	);

	foreach ($tokens as $unit => $text) {
		if ($time < $unit) continue;
		$units = floor($time / $unit);
		return $units.' '.$text.(($units>1)?'s':'').' ago';
	}
	return $time_ago.' ago';
}

// model logic

function check_prereq() {
	$path = realpath(PASTE_FOLDER);
	if(!is_writable($path)) {
		return "$path must be writable";
	}
	if(!is_executable($path)) {
		return "$path must be executable";
	}
	if(!is_dir($path)) {
		return "$path must be a directory";
	}
	return null;
}

function handle_post() {
	if(isset($_SERVER) && $_SERVER['REQUEST_METHOD'] != "POST") {
		return;
	}
	$body = file_get_contents('php://input');
	if(isset($_POST['body'])) {
		$body = $_POST['body'];
	}
	if($body) {
		return redirect(create_paste($body));
	}
	return redirect();
}

function get_search_results() {
	if(isset($_GET['search']) && $s = $_GET['search']) {
		exec("grep -c ".escapeshellarg($s)." ".PASTE_FOLDER."/* | grep -v index.php | egrep -v ':0$' | sort -nr -k2 -t:", $hits, $exit_code);
		// search all files but index.php in PASTE_FOLDER
		// exclude files not matching
		// sort by most hits descending
		$result = array();
		foreach($hits as $hit) {
			preg_match('~/(?P<hash>[a-z0-9]+):(?P<count>\d+)~', $hit, $parts);
			$result[] = "<li><a href='".permalink($parts['hash'])."'>".$parts['hash']."</a> (".$parts['count']." hits)</li>";
		}
		if(!$result) {
			return "<h3>No results found for <em>".htmlentities($s, ENT_QUOTES, 'UTF-8')."</em>";
		}
		$amount = count($result);
		return  "<h3>".$amount." result".($amount > 1 ? 's' : null)." found for <em>".htmlentities($s, ENT_QUOTES, 'UTF-8')."</em></h3><ol>".implode("\n", $result)."</ol>";
	}
	return null;
}

function create_paste($body) {
	$id = substr(md5($body), 0, 7);
	$filename = PASTE_FOLDER.'/'.$id;
	if(!file_exists($filename) && !file_put_contents($filename, $body)) {
		return false;
	}
	return $id;
}

function redirect($id) {
	$permalink = permalink($id);
	header("Location: ".$permalink);
	echo $permalink;
	exit(0);
}

function list_pastes($count = LIST_AMOUNT) {
	exec("ls -t ".PASTE_FOLDER." | grep -v index.php | head -n ".((int)$count), $files, $exit_code);
	$output = array();
	foreach($files as $file) {
		$output[$file] = filemtime(PASTE_FOLDER."/$file");
	}
	return $output;
}

// view logic

function render_index($search_result = array()) {
	$pastes = list_pastes();
	if(!$pastes) {
		$last = "<h2>Nothing dumped yet</h2>";
	} else {
		$last = "<h2>Recently dumped</h2><ul>";
		foreach($pastes as $file => $time) {
			$last .= "<li><a href='".permalink($file)."'>$file</a> ".time_ago($time)."</li>";
		}
		$last .= "</ul>";
	}
	$second_col = "<div class='col'>
		<form action='' method='get'>
			<label>Search: <input name='search' /></label>
			<input type='submit' name='' value='Submit' />
		</form>";
	if($search_result) {
		$second_col .= $search_result;
	}
	$second_col ."</div>";
	$self = permalink();
	echo <<<POLICE
<!doctype>
<html>
<head>
<style type="text/css">
body {
	background: #ededed;
	padding: 1em;
	font: 1em/1.2 sans-serif;
}
#dumper {
	font-family: monospace;
	width: 100%;
}

#footer {
	font-size: smaller;
	text-align: center;
}

.col {
	float: left;
	width: 40%;
}

var {
	background: #111;
	color: grey;
	font-family: monospace;
	font-style: normal;
	line-height: 1.5;
	margin: 0.3em;
	padding: 0.2em;
}

dd+dt {
	margin-top: 2em;
}

dd {
	margin-left: 0;
}

</style>
</head>
<body>
	<h1>Taking a dump</h1>
	<div id="container">
		<div class="col">
		$last
		</div>
		$second_col
		<div style="clear: both"></div>
	</div>
	<form action="" method="post">
		<textarea id="dumper" cols="20" rows="10" name="body"></textarea>
		<p><input type="submit" name="" value="Dump" /></p>
	</form>
	<h2>Help/hacks</h2>
	<dl>
		<dt>Dump from CLI</dt>
		<dd><var>curl $self --data-binary @&lt;filename&gt;</var></dd>
		<dd><var>curl $self --data-binary "This is my paste"</var></dd>

		<dt>Dump from STDIN</dt>
		<dd><var>ls /tmp | curl $self --data-binary @-</var></dd>

		<dt>Be efficient</dt>
		<dd><var>curl $self --data-binary "This is my paste" | xargs xdg-open</var></dd>
		<dd><var>curl $self --data-binary "This is my paste" | xclip</var></dd>

		<dt>From within vim</dt>
		<dd><var>:w !curl $self --data-binary @-</var></dd>
	</dl>
	<h3>vimconfig to send buffer to paste</h3>
	<pre><code>cnoremap tad call Tad()<CR>
function! Tad(...)
        w !curl $self --data-binary @-
endfunction</code></pre>
	<p>Call it with <var>:tad</var> from within vim.</p>
	<div id="footer">
<a rel="license" href="http://creativecommons.org/licenses/by-sa/3.0/deed.en_GB"><img alt="Creative Commons Licence" style="border-width:0" src="http://i.creativecommons.org/l/by-sa/3.0/80x15.png" /></a><br />This work is licensed under a <a rel="license" href="http://creativecommons.org/licenses/by-sa/3.0/deed.en_GB">Creative Commons Attribution-ShareAlike 3.0 Unported License</a>.
	</div>
</body>
</html>
POLICE;
}

// controller logic
if($req = check_prereq()) {
	header("HTTP/1.0 500 Wrong permissions");
	echo $req;
	exit(1);
}

handle_post();
render_index(get_search_results());

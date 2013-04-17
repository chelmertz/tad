<?php

// config

define('PASTE_FOLDER', __DIR__);
define('LIST_AMOUNT', 20);
error_reporting(E_ALL|E_STRICT);

// helpers

function permalink($id = null) {
	$protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
	$uri = $protocol.$_SERVER['HTTP_HOST'].rtrim($_SERVER['REQUEST_URI'], '/');
	$uri = preg_replace('~/index.php$~', null, $uri);
	return "$uri/$id";
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
	$body = file_get_contents('php://input');
	if(!empty($_POST['body'])) {
		$body = $_POST['body'];
	}
	if($body) {
		redirect_to_paste(create_paste($body));
	}
}

function create_paste($body) {
	$id = substr(md5($body), 0, 7);
	$filename = PASTE_FOLDER.'/'.$id;
	if(!file_exists($filename) && !file_put_contents($filename, $body)) {
		return false;
	}
	return $id;
}

function redirect_to_paste($id) {
	$permalink = permalink($id);
	header("Location: ".$permalink);
	echo $permalink;
	exit(0);
}

function get_last($count = LIST_AMOUNT) {
	exec("ls -t ".PASTE_FOLDER." | grep -v index.php | head -n ".((int)$count), $output, $exit_code);
	return $output;
}

// view logic

function render_paste($id) {
	header("Content-type: text-plain");
	echo file_get_contents(PASTE_FOLDER.'/'.$id);
	exit(0);
}

function render_index() {
	$last = get_last();
	if(!$last) {
		$last = "<h2>Nothing dumped yet</h2>";
	} else {
		$last = "<h2>Recently dumped</h2><ul><li>".implode("</li><li>", array_map(function($id) { return "<a href='./$id'>$id</a>"; }, $last))."</li></ul>";
	}
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
		<div class="col">
			<h2>Help</h2>
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
		</div>
		<div style="clear: both"></div>
	</div>
	<form action="" method="post">
		<textarea id="dumper" cols="20" rows="10" name="body"></textarea>
		<p><input type="submit" name="" value="Dump" /></p>
	</form>
	<h2>Further hacks</h2>
	<h3>vimconfig to send buffer to paste</h3>
	<pre><code>cnoremap tad call Tad()<CR>
function! Tad(...)
        w !curl $self --data-binary @-
endfunction</code></pre>
	<p>Call it with <var>:tad</var> from within vim.</p>
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
if(isset($_GET['id']) && preg_match('~[a-z0-9]+~i', $_GET['id']) && file_exists(PASTE_FOLDER.'/'.$_GET['id'])) {
	render_paste($_GET['id']);
}
render_index();

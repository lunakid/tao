<?php

//======================================================================
function p($str) {
	global $output;
	$output .= $str;
}

//---------------------------------------------------------------------
// Should use file_put_contents() now:
function my_write_file($fname, &$bindata) {
	$f = @fopen($fname, 'w+b');
	if ($f) {
		fwrite($f, $bindata);
		fclose($f);
		return true;	//!! *cough*...
	} else {
		DBG("my_write_file: failed to write '$fname'");
		return false;
	}
}

function load_file($file, $default = '') {
	return (is_file($file)
		? trim(file_get_contents($file))
		: $default
	);
}

/*
function &my_read_file($fname, $return_error = false) {
	$f = @fopen($fname, 'rb');
	if ($f) {
		$bindata = &fread($f, filesize($fname));
		fclose($f);
		return unmagicquotes($bindata);//!! THIS IS A BUG HERE, POSSIBLY!!
	} else {
		DBG("my_read_file: failed to read '$fname'");
		return $return_error ? '?my_read_file ERROR?' : false;
	} 
}
*/

// canonical date+time
function timestamp() {
	return date('Y-m-d H.i.s');
}

function unmagicquotes(&$str) {
	if (get_magic_quotes_gpc())
		return stripslashes($str);
	else
		return $str;
}

function err($msg) {
	echo "<div class='error-message'>$msg</div>";
}

function DBG($msg, $force_output = false) {
	if (defined('DEBUG') || $force_output) {
		echo 'DEBUG: ' . $msg . '<br>';
	}
}

?>

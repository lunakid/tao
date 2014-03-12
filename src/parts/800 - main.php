<?php
//======================================================================
function main() {
	global $cfg, $app, $output;

	if (!defined('PHP_VERSION_ID')) // > 5.2.7
		if (function_exists('set_magic_quotes_runtime')) set_magic_quotes_runtime(0);

	date_default_timezone_set($cfg['timeonze']);

	session_start();

//echo $_SERVER['REQUEST_URI'];

	// Command-line processing...
	// ...

	// Config. processing...
	// $cfg = &load_and_fix_config();

	// DB => db_root_path
	$dbdir =   isset($_POST['db']) ? trim($_POST['db']) 
		: (isset($_GET ['db']) ? trim($_GET ['db']) : '');

//echo '<pre>';print_r($_SESSION);echo '</pre>';
//echo '<pre>';print_r($dbdir);echo '</pre>';
	
	if (!$dbdir && isset($_SESSION['db_root']))
		$dbdir = $_SESSION['db_root'];

	if (!$dbdir)
		$dbdir = dirname(__FILE__) .'/'. $cfg['datadir'] .'/';

	// OK, whatever it is, save it to the session
	$_SESSION['db_root'] = $dbdir;
//echo "<pre>USING DB at: [$dbdir]</pre>";

	$db = new ODB($dbdir);
	$app['db'] = &$db;
	/* This must be split: check to ODB, error message stays here...*/
	if ($db->dbversion < MIN_DBVERSION) {
		err('Sorry, ' . PRODUCT_UINAME . ' ' . PRODUCT_VERSION . 
		' requires at least ' . MIN_DBVERSION . 
		" database (yours is: $db->dbversion).");
		exit(-1);
	}
	
	// Last used item (for highlighting etc.)
	$focused_id = isset($_SESSION['focused_id']) ? $_SESSION['focused_id'] : '';
	// What to do? (Note: use "Open" for just showing stuff!)
	$cmd = isset($_POST['cmd']) ? $_POST['cmd'] 
		: (isset($_GET['cmd']) ? $_GET['cmd'] : '');
	
	// With what? (Relevant only for *some* commands.)
	$id = isset($_POST['id']) ? $_POST['id'] 
			: (isset($_GET['id']) ? $_GET['id'] : '');
		$id = trim($id);
	$subject = isset($_POST['subject']) ? $_POST['subject'] : '';
		$subject = trim(unmagicquotes($subject));
	$priority = isset($_POST['priority']) ? $_POST['priority'] : '';
		$priority = trim($priority);
	$_SESSION['context'] = isset($_POST['context']) ? $_POST['context'] 
			: (isset($_GET['context']) ? $_GET['context'] : $_SESSION['context']);
	$weblink = isset($_POST['weblink']) ? $_POST['weblink'] 
			: (isset($_GET['weblink']) ? $_GET['weblink'] : '');

	// What to display? (Default is the list view.)
	$page = isset($_POST['page']) ? $_POST['page'] 
			: (isset($_GET['page']) ? $_GET['page'] : '');
		$page = trim($page);

/*
echo "id: $id <br>";
echo "cmd: $cmd <br>";
echo "ctx: $_SESSION[context] <br>";
echo "new state: ".$_POST['statechg']." <br>";
*/

	$chgset_cfg = &$cfg;
	$chgset_cfg['db'] = $db;
	$list = new ChangeSet($chgset_cfg);

	$list->select_context($_SESSION['context']); // (empty context --> NOOP)
	$list->focus_on($focused_id);    // (empty id --> NOOP)
	
	if ($cmd) switch ($cmd) {
		case '':
			break;
	
		case 'New':
			$list->new_entry($subject);
			break;
	
		case 'Save':
			$attrs = array();
			$attrs['id'] = $id;
			$attrs['subject'] = $subject;
			$attrs['priority'] = $priority;
			if (isset($_POST['weblink'])) {
				$attrs['weblink'] = $_POST['weblink'];
				DBG("weblink IS SET TO: '".$attrs['weblink']."'");
			}
			if (isset($_POST['body'])) {
				$attrs['body'] = unmagicquotes($_POST['body']);
				DBG("BODY IS SET TO: '".$attrs['body']."'");
			}

			$list->update_entry($id, $attrs);
			break;
	
		case '!':
			$list->activate($id);
			break;
	
		case '-':
			$list->increase_priority($id);
			break;
	
		case '+':
			$list->decrease_priority($id);
			break;
	
		case 'OpenLink':
			$page = 'weblink';
			// See switch($page) below...
			break;
		
		case 'Show':
		case 'Open':
			// See switch($page) below...
			break;
	
		case 'Export':
			$page = "export";
			break;

		default:
			err ("Command '$cmd' is NOT implemented!");
	}
	if (isset($_POST['statechg'])) {
		DBG("STATE CHANGE: from $list->current_context to ".$_POST['statechg']);
		$list->set_entry_state($id, $_POST['statechg']);
	}

	// Which page type to show actually?	
	switch ($page) {
	
		case 'entry':
			$list->show_entry_details($id);
			print_entry_page($output);
			break;
	
		case 'weblink':
			$list->focus_on($id);
			//header("Location: $weblink"); // This doesn't redirect for some reason (no headers sent yet!) :-o
			echo "<script> location='$weblink' </script>";
			exit(0);

		case 'export':
			$export = new Exporter($list);
//			if (isset($_GET['as'])) switch ($_GET['as']) {
//			case 'text':
				echo '<pre>',$export->text(),'</pre>';
				break;
//			}
			break;
		
		default: // Show the issue list by default

			$list->show_title();
			$list->show_toolbar();
			$list->cfg['show_summary'] && $list->show_summary();
			$list->show();
			$list->cfg['show_summary'] && $list->show_summary();
			$list->show_toolbar();

			print_list_page($list, $output);

			break;
	}
	
	if ($list->focused_id != -1 && !empty($list->focused_id)) {
		$_SESSION['focused_id'] = $list->focused_id;
	}
}


//
// Page templates...
//
function print_list_page($list, &$output) {
	global $style;
	print <<<__END
<!DOCTYPE html>
<html><head>
<!--link rel="stylesheet" type="text/css" href="style.css" /-->
<style>$style</style>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<!-- EMBEDDED NOW (see page bottom): script type="text/javascript" src="jquery.formobserver-sz.js"></script -->
<script language="JavaScript" type="text/javascript"><!--
function mysubmit(form, subm_name, subm_val)
{
	var e = document.createElement('input');
		e.type="hidden";
		e.name=subm_name;
		e.value=subm_val;
	form.appendChild(e);
	form.submit();
}
--></script> 
</head><body>
__END;
	print($output);
	print_banner();
	print <<<__END
<script>
$(document).on( "dblclick", "table.entry", function() {
	var id = $(this).data("id")
	url = "?page=entry&cmd=Open&id=" + id.toString() + "&context=$list->current_context"
	window.open(url, '_blank')
});
</script>
__END;
	print('</body></html>');
}


function print_entry_page(&$output) {
	global $style;
	print <<<__END
<!DOCTYPE html>
<html><head>
<!--link rel="stylesheet" type="text/css" href="style.css" /-->
<style>$style</style>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script type="text/javascript" src="jquery.formobserver.js"></script>
<script language="JavaScript" type="text/javascript"><!--
function mysubmit(form, subm_name, subm_val)
{
	var e = document.createElement('input');
		e.type="hidden";
		e.name=subm_name;
		e.value=subm_val;
	form.appendChild(e);
	form.submit();
}

--></script> 
</head><body>
__END;
	print($output);
	print_banner();
	print <<<__END
<script>
$('form.entry').submit(function(){
	$(this).FormObserve_save(); // http://code.google.com/p/jquery-form-observe/
});
</script>
__END;
	print('</body></html>');
}

function details_page_show_close() {
	p('<hr><input type="button" value="Close" onClick="window.close()"></p>');
	p('<script>');
	p('$(document).keydown(function(e) { if (e.keyCode == 27) window.close() });');
	p('$(document).ready(  function()  { $("form.entry").FormObserve() });');
	p('</script>');

}

function print_banner() {
	global $app;
	print('<p style="text-align:right;"><i><small>('.PRODUCT_UINAME .' '. PRODUCT_VERSION);
	print(", DB version: ".$app['db']->dbversion.")</small></i>");
}


//--------------------------
main();

?>

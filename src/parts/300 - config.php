<?php
//======================================================================
//define('DEBUG', 1);
//======================================================================

define('FLEXMAN_DBVERSION_FILE', '.__DBVERSION');
$cfg['datadir'] = 'data';	// path "offset" from the script dir to db_root
$cfg['statedir'] = 'CONTEXT/';
//!!define('FULL_CONTEXT', '.ALL');          // Items always belong to this one until REALLY purged.
//!!define('DEFAULT_CONTEXT', FULL_CONTEXT); // Starting point of state transitions...

$cfg['default_context'] = 'DEFAULT'; // Start with these items...
$cfg['show_summary'] = true;
$cfg['timeonze'] = 'Europe/Budapest';


// Note: context dir names are the same as the internal context names!

define('NEXTID_FILE', '.nextid');
define('ITERATION_FILE', '.iteration');
define('TITLE_FILE', '.title');

define('HIGHEST_PRIORITY', 0);
define('LOWEST_PRIORITY', 255);
define('DEFAULT_PRIORITY', 50);
define('SCRIPT_SELF', $_SERVER['PHP_SELF']);

?>

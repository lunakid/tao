<?php
error_reporting(E_ALL);
//======================================================================
define('PRODUCT_NAME', 'Tao');
define('PRODUCT_UINAME', 'Tao');
define('PRODUCT_VERSION', '0.287');
define('MIN_DBVERSION', '0.25');
?>
<?php 
/*
TODO:
	! Deleting an item from the details page should go back to the list!

HISTORY:

  0.287 - 2014-03-11
	- Change history is now tracked by the change database under 
	  tao/changes (and in the GIT commit history).
	- Added a simplistic build (= glue) srcipt (and phase...).
	- Fixed self URL.
	- Several small refactoring and minor UI changes.

  0.286 - 2014-03-11
	- Fix explicit DB change by (GET) request.
	- Added some padding to the subject lines in the list.
	- Embedded the form observer javascript to avoid loosing data on a 
	  mistaken submit due to the script not having been loaded for any 
	  reason.
	- Added err() calls instead of echos.

  0.285 - 2014-03-10
	- Added check to skip file_get_contents() if no 'title'.
	- Removed '&' before 2 file_get_contents() calls.
	- Renamed print_logo to print_banner.
	- Added date_default_timezone_set() to silence warnings from PHP 5.1 up.
	- Not using short PHP tag now.
	- Added error_reporting(E_ALL).
	- set_magic_quotes_runtime() is only called if supported.
	- Renamed 'cache_syncwrite' to 'cache_writethrough'
	- Fixed 'get property of non-object' errors (line 265/266).
	- Minor styling of the issue list. (Body margins added, ID field widened.)
	- Using <script> location=... redirecting, as header(loc.) does nothing
	  for some reason (perhaps only on Node.js?)
	- Added DOCTYPE "html" (which means HTML5 now).
	- Added jQuery 1.11.1 (via Google CDN).
	- Added JS doubleclick-open to issues summary lines.
	- Renamed "iteration" to "phase" to be more straightforward to a wider
	  audience.
	- Fixed Notice: Undefined variable: newstate_label on line 666
	- Changed label "Cancelled" to "Deleted" in the test DB (now being consistent
	  with the button labels, and also with the context dir names...).
	- Added Esc-close to the details page. (No check for changes yet! :-o )
	- Add check for changes and prevent Esc-close on the details page.
	- Added http://code.google.com/p/jquery-form-observe/ for form input 
	  change tracking.
	- Add CREATED timestamp to new items.

  0.284-pre2

	- Added lots of missing isset()s.

	- Upgraded to use file_get_contents (requires PHP 4.3.0!)

	  *** The effect of the now missing unmagicquote() call ***
	  *** is UNCHECKED yet!                                 ***

  	PENDING:
  	
  	- See: http://project/todo/current/changes/

	- Caching problem (seems to be IE-related):

		1. The body of most recently added item STILL missing 
		   from the "Details" page till a couple of seconds / minutes

		2. After adding a new item and then immediately restarting
		   IE6, the most recently added item is missing from the list.
		   for a couple of seconds (minutes)?
		   OTOH, Mozilla does this right.

  0.283

    	This History added.
  	Merged 0.283 back to the main branch.

  	PENDING:

  	That caching problem:

		1. The body of most recently added item STILL missing 
		   from the "Details" page till a couple of seconds / minutes

		2. After adding a new item and then immediately restarting
		   IE6, the most recently added item is missing from the list.
		   for a couple of seconds (minutes)?
		   OTOH, Mozilla does this right.
  		

  0.282a
  
	1. This version is to isolate the following bug: 

		1. body of most recently added item missing from the
		details

			--> It gets written, but does not get
			read back. It does not happen consistently.
			Supposedly an ODB property caching problem.
		
			No, it's a session caching problem! (Timestamp
			tests revealed this.)

		2. then (after restarting the browser) the whole 
		most recently added item missing from the list

			--> this part seems to be a simple
			browser caching problem

	2. Some comments added/fixed.
*/
?>
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
<?php

class ODB {
	var $db_root;	// *WITH* a trailing slash
	var $dbversion;
	var $title;
	
	function ODB($db_root) { // trailing slash is *REQUIRED*!
		if (!is_dir($db_root)) {
			err("Sorry, not a dir: '$db_root' (CURRENT DIR: " . getcwd() . ").");
			return;
		}
		if ($db_root{strlen($db_root)-1} != '/')
			$db_root .= '/';

		$this->db_root = $db_root;
		
		$this->dbversion = trim(file_get_contents($this->db_root.FLEXMAN_DBVERSION_FILE));
		if (is_file($this->db_root.'.title'))
			$this->title = trim(file_get_contents($this->db_root.'.title'));
		if (!$this->title)
			$this->title = "[UNTITLED]";
	}
}

class ODB_AttrCachedVal {
	var $cached_value;
	var $unsaved;
	
	function ODB_AttrCachedVal($value, $unsaved = true) {
		$this->cached_value = $value;
		$this->unsaved = true;
	}
}

class ODB_Obj {
	var $dbid;	// object ID within the DB
	var $attr;	// ['name'] -> ODB_Attr
	// Flags
	var $cache_writethrough;

	function ODB_Obj($dbid, $autosync = false) {
		$this->dbid = $dbid;
		$this->autosync = $autosync;
		$this->attr = array();
	}

	function set($name, $value) {
		$this->attr[$name] = new ODB_AttrCachedVal($value);
		if ($this->cache_writethrough) {
			$this->flush_cached_attr($name);
		}
	}

	// Returns $default on fetch error...
	function get($name, $default = '') {
		if (!isset($this->attr[$name]) /*|| !$this->attr[$name]->cached_value*/) {
			DBG("fetching '$name' into the cache...");
			$this->cache_fetch_attr($name);
		}
		if (!isset($this->attr[$name]) /*|| !$this->attr[$name]->cached_value*/) {
			// Or keep it unset so a subsequent get would retry?
			$this->attr[$name] = new ODB_AttrCachedVal($default);
		}
		DBG("'$name' is now: '" . $this->attr[$name]->cached_value . "'...");
		return $this->attr[$name]->cached_value;
	}

	function flush_cached_attr($name) {
		$dbslot = $this->dbid . '/' . $name;
		if (my_write_file($dbslot, $this->attr[$name]->cached_value)) {
			DBG("'$name' flushed from cache.");
			$this->attr[$name]->unsaved = false;
		}
	}

	function cache_fetch_attr($name) {
		$dbslot = $this->dbid . '/' . $name;
		$value = @file_get_contents($dbslot);
		if ($value === FALSE) {
			DBG("'$name' could not be fetched into the cache.");
			return null;
		} else {
			DBG("'$name' fetched to cache as '$value'.");
			$this->attr[$name] = new ODB_AttrCachedVal($value);
			DBG("$this->attr[$name]->cached_value == \''.$this->attr[$name]->cached_value.");
		}
	}

	function save_new() {
		if (!file_exists($this->dbid)) {
			return $this->save();
		} else {
			return false;
		}
	}
	
	function save() {
		if (!file_exists($this->dbid)) {
			mkdir($this->dbid);
		}
		foreach($this->attr as $name => $val) {
			my_write_file($this->dbid . '/' . $name, trim($val->cached_value));
		}
		return true;	//!! *cough*...
	}

}

class Entry extends ODB_Obj {
	var $id;
	
	// Attributes:
	//	created [date read from metadata, currently!]
	//	last_activated
	//	subject
	//	priority (!! to be renamed to "imoprtance")
	//	body
	//	weblink [optional]

	function Entry($dbid, $id) {
		// Base class init...
		$this->ODB_Obj($dbid);
		// Local instance init...
		$this->id = $id;
	}

	function update($attrs) {
		foreach($attrs as $attrname => $val) {
			$this->set($attrname, $val);
		}
	}

	function &load($dbobj, $id, $load_only_these_attrs = null) {
		$o = new Entry($dbobj, $id);
		if ($load_only_these_attrs) {
			foreach($load_only_these_attrs as $attrname) {
				$o->get($attrname);
			}		
		} else {
			//!! HACKING-TIME...
			$o->get('last_activated');
			$o->get('subject');
			$o->get('priority');
			$o->get('weblink');
		}
		return $o;
	}

	function show(&$ctxlist, $selected) {
		$bgcolor = $selected ? 
			'fff8e0' : 
			(bin2hex(chr(LOWEST_PRIORITY - $this->get('priority'))) . 'e0e0');
		
		p("\n<a name=\"$this->id\" />");
		p("<table class=\"entry"
			. ($selected? " selected" : '')
			. "\" data-id=\"$this->id\" style=\"background: #$bgcolor;\"><tr>");
		
		p('<td class="entry-id">');
		p('<a href="' . $ctxlist->nextpage_url(
			'&page=entry&cmd=Open&id='.$this->id.
			'&context='.$ctxlist->current_context, $this->id)
			. '" target="_blank">'."#$this->id</a>");
		p("\n".'<form class="entry" action="' 
			. $ctxlist->nextpage_url('', $this->id).'" target="_blank" method="post">');
		p('<input type="hidden" name="id" value="'.$this->id.'" />');
		p('<input type="hidden" name="context" value="'.$ctxlist->current_context.'" />');
		p('<input type="hidden" name="page" value="entry" />');
/*
		p('<input class="entrybutton" type="button" value="#'.$this->id.'"'.
			" onClick=\"javascript:mysubmit(form, 'cmd', 'Open');\">");
*/		
		if ($this->get('weblink')) {
			p('<input type="hidden" name="weblink" value="'.$this->get('weblink').'" />');
			p('<input class="entrybutton" type="button" value="\\/\\/"'.
			" onClick=\"javascript:mysubmit(form, 'cmd', 'OpenLink');\">");
		}
		p('</form>');
		p('</td>');
		
		p('<td class="entry-data">');
		p("\n".'<form name="entry" class="entry" action="'. $ctxlist->nextpage_url('', $this->id) .'" method="post">');
		p('<input type="hidden" name="id" value="'.$this->id.'" />');
		p('<input type="hidden" name="context" value="'.$ctxlist->current_context.'" />');
		p('<input class="entrysubj" type="text" name="subject" value="'.htmlspecialchars($this->get('subject')).'">');
		p('<input class="entry" type="text" size="1" maxlength="3" name="priority" value="'.$this->get('priority').'">');
		p('<input class="entrybutton" type="submit" name="cmd" value="Save" default>');
		p('<input class="entrybutton" type="submit" name="cmd" value="!">');
/*
		$disabled = $this->get('priority') == HIGHEST_PRIORITY ? 'disabled' : '';
		p("<input $disabled".' class="entrybutton" type="submit" name="cmd" value="-">');
		$disabled = $this->get('priority') == LOWEST_PRIORITY ? 'disabled' : '';
		p("<input $disabled".' class="entrybutton" type="submit" name="cmd" value="+">');
*/		
		foreach($ctxlist->allowed_actions as $action => $result) {
			p('<input class="entry-setstate" type="button" value="'.$action.'"'.
				" onClick=\"javascript:mysubmit(form, 'statechg', '$result');\">");
		}
		p('</form></td></tr></table>');
	}

	function show_all(&$ctxlist) {
		$bgcolor = /* $selected ? 
			'fff8ec' : */
			(bin2hex(chr(LOWEST_PRIORITY - $this->get('priority'))) . 'e0e0');

		p("\n".'<form class="entry" action="'. $ctxlist->nextpage_url('', $this->id) .'" method="post">');
		p('<input type="hidden" name="page" value="entry" />');
		p('<input type="hidden" name="context" value="'.$ctxlist->current_context.'" />');

		p('<table class="entry" style="background: #'.$bgcolor.'; width:100%; border: 1px solid black;"><tr>');

		p('<tr><td class="entry-attrnam">ID:</td>');
		p('<td class="entry-attrval">');
		p($this->id);
		p('<input type="hidden" name="id" value="'.$this->id.'"  class="entry-attrval" />');
		p('</td></tr>');

		p('<tr><td class="entry-attrnam">Created:</td>');
		p('<td class="entry-attrval">');
		p($this->get('created'));
		p('</td></tr>');

		p('<tr><td class="entry-attrnam">Subject:</td>');
		p('<td class="entry-attrval">');
		p('<input type="text" name="subject" value="'.htmlspecialchars($this->get('subject')).'" class="entry-attrval" />');
		p('</td></tr>');

		p('<tr><td class="entry-attrnam">Importance:</td>');
		p('<td class="entry-attrval">');
		p('<input type="text" name="priority" value="'.$this->get('priority').'" size="3" maxlength="3" class="entry-attrval" />');
		p('</td></tr>');

		p('<tr><td class="entry-attrnam">Associated Web URL:</td>');
		p('<td class="entry-attrval">');
		p('<input type="text" name="weblink" value="'.$this->get('weblink').'" class="entry-attrval" />');
		p('</td></tr>');

		p('<tr><td class="entry-attrnam">Body:</td>');
		p('<td class="entry-attrval">');
		p('<textarea name="body" rows="10" class="entry-attrval" >');
		p(htmlspecialchars($this->get('body')));
		p('</textarea>');
		p('</td></tr>');

		p('<tr><td class="entry-attrnam">');
		p('Change state/context:');
		p('</td>');
		p('<td class="entry-attrval">');
		foreach($ctxlist->allowed_actions as $action => $result) {
			p('<input class="setstate" type="button" value="'.$action.'"'.
				" onClick=\"javascript:mysubmit(form, 'statechg', '$result');\">");
		}
		p('</td></tr>');
		p('<tr><td class="submit">');
		p('<input class="submit" type="submit" name="cmd" value="Save" default>');
		p('</td></tr>');
		p('</table>');
		p('</form>');
	}

	// Event handlers...
	function on_get_focus() {
	}

	function on_activate() {
		$this->set('last_activated', time());
		$this->save();	//!! UGH...
	}
}


function cmp_priority ($a, $b) {
	if ($a->get('priority') == $b->get('priority')) {
		return $b->id - $a->id; // higher ID goes to lower index
	} else {
		return $a->get('priority') - $b->get('priority'); // lower pri. to lower index
	}
}		

function cmp_last_activated ($a, $b) {
	return $b->get('last_activated') - $a->get('last_activated');
}		

/*
Display items of a selected state + handle various operations...
*/
class ChangeSet {
	var $entries;
	var $cfg;
	var $db;		// the DB manager stuff (pretty pathetic currently...)
	var $db_root;		// *WITH* a trailing slash!
	var $title;		// copied from ODB->title, for now...
	var $user_contexts;	// list of (app-defined) contexts
	var $current_context;	// the currently selected context...
	var $current_dir;	// ...and the dir corresponding to it
	var $allowed_actions;	// possible targets from this context (state)
	var $focused_id;	// $id the last "touched" item; can be none (-1)
	var $iteration;		// the current iteration cycle (or "phase")

	function ChangeSet(&$cfg) { // trailing slash is *REQUIRED*!
		$this->cfg = $cfg;
		$this->db = $cfg['db'];
		$this->db_root = $this->db->db_root;
		$this->title = $this->db->title;

		// Check/fix the basic dir layout...
		//!!...

		// Get the iteration (phase) counter...
		$f = $this->db_root . ITERATION_FILE;
		$this->iteration = is_readable($f) ? trim(file_get_contents($f)) : '';
		
		// Get the list of custom contexts...
		// Also build a map of context labels and internal context names.
		$contexts_dir = $this->db_root . $this->cfg['statedir'];
		$d = dir($contexts_dir);
		$this->user_contexts = array();
		while (false !== ($direntry = $d->read())) {
			// Get Context name...
			if ($direntry{0} == '.') continue;
			$ctx_name = $direntry;
			array_push($this->user_contexts, $ctx_name);

			// Get Context label...
			$ctx_label = trim(file_get_contents($contexts_dir
				.'/'.$ctx_name.'/.label'));
			if (!$ctx_label) {
				$ctx_label = $ctx_name;
			}
			$this->ctxname_from_label[$ctx_label] = $ctx_name;
			$this->ctxlabel_from_name[$ctx_name] = $ctx_label;
		}
		$d->close();
		
		$this->select_context($this->cfg['default_context']);
	}

	function select_context(&$context) {
		if (!$context || $this->current_context == $context)
			return;
			
		//!! DO WE REALLY NEED TO COPY THE context??
		$this->current_context = $context;

		$this->current_dir = $this->db_root 
				. $this->cfg['statedir']
				. $this->current_context;

		// Determine, what operations are allowed (according to
		// the state machine) on the items of the current context...
		$this->allowed_actions = array();
		$targets_dir = $this->current_dir . '/.NEXT';
		$d = @dir($targets_dir);
		if ($d) {
			while (false !== ($direntry = $d->read())) {
				if ($direntry{0} == '.') continue;
				$action = $direntry;
				$target_state = &file_get_contents($targets_dir.'/'.$direntry);
				//!!check...
				$this->allowed_actions[$action] = $target_state;
			}
			$d->close();
		}
		
		$this->reload();
	}

	function reload() {
		$this->entries = array();
		$d = dir($this->current_dir);

		//!! Just a hack! comparing the full canonical
		//!! paths would be the correct thing...
		$scriptfile = basename(__FILE__);

		while (false !== ($direntry = $d->read())) {
			if ($direntry{0} == '.' || $direntry == $scriptfile)
				continue;

//!!??			$direntry;

			array_push($this->entries, 
				Entry::load($this->current_dir .'/'. $direntry,
						$direntry));
		}
		$d->close();

		$this->sort();
	}

	function sort($key = 'last_activated') {
		$cmp = "cmp_$key";
		usort($this->entries, $cmp);
	}

	function get_next_id() {
		$nextidfile = $this->db_root . NEXTID_FILE;
		$nextid = &file_get_contents($nextidfile);
		return $nextid ? $nextid : 1;
	}

	function set_next_id($id) {
		$nextidfile = $this->db_root . NEXTID_FILE;
		my_write_file($nextidfile, $id);
	
		if ($this->get_next_id() != $id) {
			err ("Uhh, error saving the next ID!\n" .
				"Fix it manually ASAP, otherwise the next new " .
				"entry may overwrite the last one!'"
			);
			exit(-1);
		}
	}

	function index_of($id) {
		foreach ($this->entries as $i => $dummy) {
			if ($this->entries[$i]->id == $id) {
				return $i;
			}
		}
		return -1;
	}

	function new_entry($subject) {
		if (!$subject) {
			return;
		}
		$id = $this->get_next_id();
		$dbobj = $this->current_dir/*!!This must be changed ASAP!!*/ . '/' . $id;
		$entry = new Entry($dbobj, $id);
		$entry->get('created', timestamp());
		$entry->set('subject', $subject);
		$entry->set('priority', DEFAULT_PRIORITY);
		
		// Add physically...
		if (!$entry->save_new()) {
			err ("Couldn't add '$subject'! (Already exists?)");
			return;
		}

		$this->add_metadata($id, "CREATED as '$subject'");
		
		$this->set_next_id($id + 1);

		// Add to the memory-array...
		array_unshift($this->entries, $entry);
		$this->sort();
		
		$this->activate($id);
	}


	function set_entry_state($id, $newstate) {
		if (-1 != ($i = $this->index_of($id))) {
			//$newstate = $this->ctxname_from_label[$newstate_label];
			DBG("$newstate");
			$newstate_dir = $this->db_root . $this->cfg['statedir'].$newstate;
			if (!is_dir($newstate_dir)) {
				err ("Undefined state: '$newstate' (no dir: '$newstate_dir')!");
				return;
			}
			$fname_old = $this->current_dir 
				.'/'. $id;
			$fname_new = $this->db_root
				. $this->cfg['statedir'].$newstate
				.'/'. $id;
			if (file_exists($fname_new)) {
				err ("This item is already in that context!");
				return;
			}

			$this->add_metadata($id, "MOVED: $this->current_context --> $newstate");
			
			// Do the physical move...
			DBG("rename: '$fname_old' to '$fname_new'");
			if (rename($fname_old, $fname_new)) {
			
				// Also delete from the default memory array...
				array_splice($this->entries, $i, 1);
			}
		} else {
			DBG("ITEM NOT FOUND!");
		}
	}

	function update_entry($id, $attrs) {
		if (-1 == ($i = $this->index_of($id))) {
			DBG("ITEM NOT FOUND!");
			return;
		}
		$this->entries[$i]->update($attrs);

		$this->add_metadata($id, "UPDATED");

		$this->entries[$i]->save();
		$this->sort();

		$this->focus_on($id);

	}


	function add_metadata($id, $info) {
		$logline = date("Y-m-d H:i:s") . 
			($this->iteration ? " [iteration: $this->iteration] " : ' ') .
			$info;
		$fname = $this->current_dir .'/'. $id;
		$f = fopen($fname . '/.history', 'a+b');
		if ($f) {
			fputs($f, "$logline\n");
			fclose($f);
		}
	}
			
	function show_entry_details($id) {
		if (-1 != ($i = $this->index_of($id))) {
			p("<h4>Details of #$id:</h4>");
			$this->entries[$i]->show_all($this);
		
			$this->focus_on($id);
		} else {
			//!! Now we'd be able to just close the window, in a better world....
			err ("Could not find #$id.");
		}
	}

	function activate($id) {
		if (-1 == ($i = $this->index_of($id))) {
			return;
		}
		$this->entries[$i]->on_activate();
		$this->entries[$i]->save();
//		$this->add_metadata($id, "ACTIVATED");
		
		$this->sort();
		
		$this->focus_on($id);
	}
	
	function set_importance($id, $priority) {
		if (-1 != ($i = $this->index_of($id))) {
			if ($priority < HIGHEST_PRIORITY ||
			    $priority > LOWEST_PRIORITY) { // sorry about the +/- thing!...
			    // invalid p.
			    return;
			}
			$this->entries[$i]->set('priority', $priority);
			$this->entries[$i]->save();
			$this->sort();
			
			$this->focus_on($id);
		}
	}
	
	function increase_importance($id) {
		if (-1 != ($i = $this->index_of($id))) {
			$priority = $this->entries[$i]->get('priority');
			$this->set_priority($id, $priority - 1); // - means HIGHER!
		}
	}

	function decrease_importance($id) {
		if (-1 != ($i = $this->index_of($id))) {
			$priority = $this->entries[$i]->get('priority');
			$this->set_priority($id, $priority + 1); // + means LOWER!
		}
	}

	function focus_on($id) {
		if (-1 != ($i = $this->index_of($id))) {
			$this->focused_id = $id;
			$this->entries[$i]->on_get_focus();
		}
	}
	
	function show_title() {
		$title = '';
		if (is_file($this->current_dir .'/' . '.title'))
			$title = file_get_contents($this->current_dir . '/' . '.title');
		if (!$title) {
			$title = ''.
				$this->ctxlabel_from_name[$this->current_context]
				.':';
		}
		p("<h4>$title</h4>");
	}

	function show() {

		// Show the list...
		$cnt = count($this->entries);
		for ($i = 0; $i < $cnt; ++$i) {
			$selected = ($this->entries[$i]->id == $this->focused_id);
			$this->entries[$i]->show($this, $selected);
		}
	}	

	function show_toolbar() {
		p('<div class="toolbar">');
		$this->show_new('TOOLBAR');
		$this->show_ctx_selector('TOOLBAR');
		p('</div>');
	}

	function show_summary() {
		$cnt = count($this->entries);
		p('<table class="bottomline"><tr>');
		p("<td class=\"bottomline\">$cnt item(s)</td>");
		p("<td class=\"bottomline\">$this->title</td>");
		p("<td class=\"bottomline\">Phase: #$this->iteration</td>");
		p('</tr></table>');
	}
	
	function show_new($mode = '') {
		$css_class = ($mode == 'TOOLBAR' ? 'toolbar-mode' : '');
		//!! Note: this check should be changed: proper parmission
		//!! settings should tell if adding new items in a given context
		//!! is allowed or not!
		if ($this->current_context == $this->cfg['default_context']) {
			p('
<form name="add_new" action="'.$this->nextpage_url()."\" class=\"$css_class\"".' method="post">
<input type="submit" value="Add:">
<input type="text" name="subject" class="entry-new">
<input type="hidden" name="context" value="' . $this->current_context . '" />
<input type="hidden" name="cmd" value="New">
</form>');
		}
	}
	
	
	function show_ctx_selector($mode = '') {
		$css_class = ($mode == 'TOOLBAR' ? 'toolbar-mode' : '');
		p("\n<form name=\"context_filter\" class=\"$css_class\" action=\"".$this->nextpage_url().'" method="post">');
		p('<input type="hidden" name="page" value="list" />');
		p('<input type="hidden" name="cmd" value="Show">');
		p('<input type="submit" value="Show:" />');

		p('<select name="context" onChange="this.form.submit();">');
		$ctx = $this->current_context;
		p("<option value=\"$ctx\">" .
			$this->ctxlabel_from_name[$ctx] .
			'</option>');
		foreach ($this->user_contexts as $ctx) {
			if ($ctx == $this->current_context)
				continue;
			p("<option value=\"$ctx\">" .
				$this->ctxlabel_from_name[$ctx] .
				'</option>');
		}
		p('</select>');

		p('</form>');
	}
	
	function nextpage_url($urltail = '', $anchor = '') {
		$url = SCRIPT_SELF .'?'. strip_tags(SID) . $urltail;
		
		if ($anchor) {
			return $url . "#$anchor";
		} else {
			return $url . $this->focused_id ?  
				"#$this->focused_id" : '';
		}
	}
	
}


//======================================================================
function p($str) {
	global $output;
	$output .= $str;
}

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


function print_entry_page($context, &$output) {
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

function print_banner() {
	global $app;
	print('<p style="text-align:right;"><i><small>('.PRODUCT_UINAME .' '. PRODUCT_VERSION);
	print(", DB version: ".$app['db']->dbversion.")</small></i>");
}

function details_page_show_close() {
	p('<p><input type="button" value="Close" onClick="window.close()"></p>');
	p('<script>');
	p('$(document).keydown(function(e) { if (e.keyCode == 27) window.close() });');
	p('$(document).ready(  function()  { $("form.entry").FormObserve() });');
	p('</script>');

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
<?php
//======================================================================
$style = <<<__END
body {
	font-size: 10pt;
	margin: 1em 3em;
}
h4 {	margin-bottom: 0.5ex; }
td {
	font-size: 10pt;
	vertical-align: top;	
	padding: 1ex;
}
td.sidebar {
	width: 20%; 
	border-left: 1px solid black; 
}
input {	font-size: 9pt; }
ul.menu {
	list-style-type: none;
	margin-left: 0;
}
li.menu {
	width: 95%;
	background: #e0e0e0;
	margin: 2px;
	padding: 2px;
}
.entry {
	font-size: 8pt;
	margin:0; 
	padding:0;
}
.selected { 
/*!!DEBUG:*/
	background: red;
	padding-top: 4px;
	padding-bottom: 4px;
}
.entrysubj {
	font-size: 8pt;
	margin:0; 
	padding: 1px 2px;
	width: 70%;
}
table.entry {
	width:100%; 
	border: 1px solid black;
}
td.entry-id {
	font-size: 8pt;
	margin: 0; 
	padding: 0;
	width: 5ex;
	text-align: center;
	vertical-align: middle;
}
td.entry-data {
	font-size: 8pt;
	margin:0; 
	padding:0;
	border-left: 1px solid black;
}
form.entry {
	width:100%;
}
input.entrybutton {
	font-size: 6pt;
}
input.entry-setstate {
	background: #bcccc0;
	font-size: 6pt;
	margin:0;
	padding:0;
}
table.bottomline {
	background: #e0e0e0;
	width: 100%; /* same as table.entry */
	border: 1px solid black;
	margin:0;
}
td.bottomline {
	margin:0;
	padding:0 1ex 0 1ex;
	font-size: 8pt; /* Mozilla requires this here, not in 'table' */
	text-align: center;
}
input.setstate {
	background: #bcccc0;
}
td.entry-attrnam { width: 30%; }
input.entry-attrval { width: 100%; }
textarea.entry-attrval { width: 100%; }
input.entry-new { width: 70%; }

div.toolbar {
	width: 100%;
}
form.toolbar-mode {
	display: inline-block;
}
form[name=add_new] {
	display: inline-block;
	width: 100%;
}
form[name=add_new].toolbar-mode {
	width: 70%;
}
form[name=context_filter] {
}
__END;
?>
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
	$context = isset($_POST['context']) ? $_POST['context'] 
			: (isset($_GET['context']) ? $_GET['context'] : '');
	$weblink = isset($_POST['weblink']) ? $_POST['weblink'] 
			: (isset($_GET['weblink']) ? $_GET['weblink'] : '');

	// What to display? (Default is the list view.)
	$page = isset($_POST['page']) ? $_POST['page'] 
			: (isset($_GET['page']) ? $_GET['page'] : '');
		$page = trim($page);

/*
echo "id: $id <br>";
echo "cmd: $cmd <br>";
echo "ctx: $context <br>";
echo "new state: ".$_POST['statechg']." <br>";
*/

	$chgset_cfg = &$cfg;
	$chgset_cfg['db'] = $db;
	$list = new ChangeSet($chgset_cfg);

	$list->select_context($context); // (empty context --> NOOP)
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
//echo "time:" . time();
			$list->show_entry_details($id);
			p('<hr>');
			details_page_show_close();

			print_entry_page($list->current_context, $output);

			break;
	
		case 'weblink':
			$list->focus_on($id);
			//header("Location: $weblink"); // This doesn't redirect for some reason (no headers sent yet!) :-o
			echo "<script> location='$weblink' </script>";
			exit(0);
		
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

main();

?>
<script>
/**
 *  jquery.popupt
 *  (c) 2008 Semooh (http://semooh.jp/)
 *
 *  Dual licensed under the MIT (MIT-LICENSE.txt)
 *  and GPL (GPL-LICENSE.txt) licenses.
 *
 **/
(function($){
  $.fn.extend({
    FormObserve: function(opt){
      opt = $.extend({
        changeClass: "changed",
        filterExp: "",
        msg: "Unsaved changes will be lost."
      }, opt || {});

      var fs = $(this);
      fs.each(function(){
        this.reset();
        var f = $(this);
        var is = f.find(':input');
        f.FormObserve_save();
        setInterval(function(){
          is.each(function(){
            var node = $(this);
            var def = $.data(node.get(0), 'FormObserve_Def');
            if(node.FormObserve_ifVal() == def){
              if(opt.changeClass) node.removeClass(opt.changeClass);
            }else{
              if(opt.changeClass) node.addClass(opt.changeClass);
            }
          });
        }, 1);
      });

      function beforeunload(e){
        var changed = false;
        fs.each(function(){
          if($(this).find(':input').FormObserve_isChanged()){
            changed = true;
            return false;
          }
        });
        if(changed){
          e = e || window.event;
          e.returnValue = opt.msg;
        }
      }
      if(window.attachEvent){
          window.attachEvent('onbeforeunload', beforeunload);
      }else if(window.addEventListener){
          window.addEventListener('beforeunload', beforeunload, true);
      }
    },
    FormObserve_save: function(){
      var node = $(this);
      if(node.is('form')){
        node.find(':input').each(function(){
          $(this).FormObserve_save();
        });
      } else if(node.is(':input')){
        $.data(node.get(0), 'FormObserve_Def', node.FormObserve_ifVal());
      }
    },
    FormObserve_isChanged: function(){
      var changed = false;
      this.each(function() {
        var node = $(this);
        if(node.eq(':input')){
          var def = $.data(node.get(0), 'FormObserve_Def');
          if(typeof def != 'undefined' && def != node.FormObserve_ifVal()){
            changed = true;
            return false;
          }
        }
      });
      return changed;
    },
    FormObserve_ifVal: function(){
      var node = $(this.get(0));
      if(node.is(':radio,:checkbox')){
        var r = node.attr('checked');
      }else if(node.is(':input')){
        var r = node.val();
      }
      return r;
    }
  });
})(jQuery);
</script>
<?php

//
// Items of a selected state (context) 
// (Display & various other operations)
//
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
		$this->iteration = load_file($f);
		
		// Get the list of custom contexts...
		// Also build a map of context labels and internal context names.
		$contexts_dir = $this->db_root . $this->cfg['statedir'];
		$d = dir($contexts_dir);
		$this->user_contexts = array();
		while (false !== ($direntry = $d->read())) {
			// Get Context name...
			if ($direntry{0} == '.') {
				if ($direntry == '.ALL') {
					 // add as a virtual item (will be handled accordingly later!)
				}
				else continue;
			}
			$ctx_name = $direntry;
			array_push($this->user_contexts, $ctx_name);

			// Get Context label...
			$ctx_label = load_file("$contexts_dir/$ctx_name/.label");
			if (!$ctx_label) {
				$ctx_label = $ctx_name;
			}
			$this->ctxname_from_label[$ctx_label] = $ctx_name;
			$this->ctxlabel_from_name[$ctx_name] = $ctx_label;
		}
		$d->close();
		
		$this->select_context($this->cfg['default_context']);
	}

	function select_context($context) {
		if (!$context || $this->current_context == $context)
			return;
			
		if ($context{0} == '.') {
			err ("Virtual context filters not yet supported, sorry!");
		}

		//!! DO WE REALLY NEED TO STORE context??
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
				$target_state = load_file("$targets_dir/$direntry");
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
		$nextid = load_file($nextidfile);
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

			details_page_show_close();
		
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
			$title = load_file("$this->current_dir/.title");
		if (!$title) {
			$title = ''.
				$this->ctxlabel_from_name[$this->current_context]
				.':';
		}
		p("<h4>$title</h4>");
	}

	function show() {
		// Show the items belonging to the current context...
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
		$this->show_export('text', 'TOOLBAR');
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

	function show_export($type, $mode = '') {
		$css_class = ($mode == 'TOOLBAR' ? 'toolbar-mode' : '');
		p("\n<form name=\"export\" class=\"$css_class\" action=\"\" method=\"get\">
			 <input type=\"hidden\" name=\"cmd\" value=\"Export\">
			 <input type=\"hidden\" name=\"as\" value=\"text\">
			 <input type=\"submit\" value=\"as text\"></form>");
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

?>

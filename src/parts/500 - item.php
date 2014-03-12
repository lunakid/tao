<?php

class Entry extends ODB_Obj {
	var $id;
	
	// Attributes:
	//	created [date read from metadata, currently!]
	//	last_activated
	//	subject
	//	priority (!! to be renamed to "imoprtance")
	//	body
	//	weblink [optional]

	function __construct($dbid, $id) {
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

	static function load($dbobj, $id, $load_only_these_attrs = null) {
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

?>

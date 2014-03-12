<?php

class Exporter {
	
	var $list;
	var $items;
//!!	var $contexts;

	function __construct($list) {
		$this->list = $list;
		$this->items = $list->entries;
	}

	function text($flags = 0) {

		$out = $this->list->ctxlabel_from_name[$this->list->current_context]
			. "\n";

		$cnt = count($this->items);
		for ($i = 0; $i < $cnt; ++$i) {
			$e = $this->items[$i];

			$out .= "[" . $e->id . "]\t"; // NOT: $e->get('id')
			$out .= $e->get('subject');
			$out .= "\n";
		}	

		return $out;
	}
}
?>

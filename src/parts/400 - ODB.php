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
		
		$this->dbversion = load_file($this->db_root.FLEXMAN_DBVERSION_FILE);
		if (is_file($this->db_root.'.title'))
			$this->title = load_file($this->db_root.'.title');
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
		$value = load_file($dbslot);
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

?>

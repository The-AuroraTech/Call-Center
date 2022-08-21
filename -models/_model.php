<?php

abstract class _Model {
	var $table;
	var $record; // single record from DB
	var $records; // multiple records from DB. used with readAll
	var $sql;
	var $defaultData; // for??

	function __construct() {
		// DB Related
		$this->table = '';
		$this->uniqueField = 'id'; // this should be populated from the table
		$this->defaultFields = '*';
		$this->fields = '';
		$this->join = '';
		$this->where = array();
		$this->group = '';
		$this->order = '';
		$this->limit = '';

		$this->record = array();
		$this->records = array();

		$this->defaultData = array();
	}

	// override this with a function that returns autoform compatible field definitions
	function getFormFields() {
		return array();
	}
	function getFormFieldTemplates() {
		return array();
	}

	function getRecord() {
		return $this->record;
	}
	function getRecords() {
		return $this->records;
	}

	function getRecordForEdit() {
		return $this->getRecord();
	}

	// for miscellaneous overriding
	// probably for preparing a record for editing
	function getRecord2() {
		return $this->record;
	}

	private function sql() {
		return 'select ' .
	       		(strlen($this->fields) ? $this->fields : $this->defaultFields) .
		       	' from ' .
		       	$this->table .
		       	(strlen($this->join) ? ' ' . $this->join: '') .
			(sizeof($this->where) ? ' where ' . implode(' and ', $this->where) : '') .
		       	(strlen($this->group) ? ' group by ' . $this->group : '') .
		       	(strlen($this->order) ? ' order by ' . $this->order : '') .
		       	(strlen($this->limit) ? ' limit ' . $this->limit : '');
	}

	function load($id = 0) {
		global $db;
		if(!strlen($id))
			$id = 0;
		$record = $db->fetchRow('select * from ' . $this->table . ' where ' . $this->uniqueField . '=' . $id);
		if($record)
			$this->record = $record;
		return $this->record;
	}
	
	function read($where = '', $order = '', $limit = '') {
		global $db;
		$this->preRead();
		if(strlen($where))
			$this->where[] = $where;
		if(strlen($order))
			$this->order = $order;
		if(strlen($limit))
			$this->limit = $limit;

		$records = $db->fetch($this->sql());
		//var_dump($records);
		if($records) {
			$i = 0;
			foreach($records as $r) {
				$this->records[] = $this->prepareRecord($r, $i); // pass record index, for shits
				$i++;
			}
			reset($this->records);
			$this->record = $this->records[0];
		}
	}

	// overridable
	function preRead() {
	}
	function postRead() {
	}

	function prepareRecord($record) {
		return $record;
	}

	/*
	Functions for walking through the array

	function next() {
		next($this->records);
		return $this->current();
	}
	function previous() {
		prev($this->records);
		return $this->current();
	}
	function current() {
		$this->record = current($this->records);
		return $this->record;
	}
	* */



	function preSave() {
	}

	/*
	Return record in order to use new id?
	*/
	function save() {
		global $db;

		$this->preSave();

		$edit  = isset($this->record[$this->uniqueField]);
		if($edit)
			$where = $this->uniqueField . '=' . $this->record[$this->uniqueField];
		else
			$where = '';

		$data = $this->record;
		unset($data[$this->uniqueField]);

		if(!$edit || !$db->update($data, $this->table, $where)) {
			$this->record[$this->uniqueField] = $db->insert($data, $this->table);
		}
		$this->postSave();
		return $this->record[$this->uniqueField];
	}
	function postSave() {
	}
	// checked by the code that would normally call save() blindly
	function doSave() {
		return true;
	}

	function delete() {
		global $db;
		if($this->uniqueField)
			$db->execute('delete from ' . $this->table . ' where ' . $this->uniqueField . '=' . $this->record['id']);
	}

	function getID() {
		if(isset($this->record[$this->uniqueField]))
			return $this->record[$this->uniqueField];
		else
			return 0;
	}
	// merge data with this->record
	function merge($data) {
		$this->record = array_merge($this->record, $this->defaultData,  $data);
	}
}
?>

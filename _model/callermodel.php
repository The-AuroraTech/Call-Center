<?php
class callerModel extends _Model {
	function __construct() {
		global $db;
		parent::__construct();

		$this->table = 'callers';
		//$this->join = 'left join postal_codes on (callers.postal_code = postal_codes.postal_code_id)';
		$this->order = 'lastName,firstName';
		//$this->defaultData = array('type' => $type);
	}

	function recent() {
		global $db;
		$this->order = '';
		return $db->fetch('select * from callers');
	}

	function prepareRecord($record) {
		global $db;
		if(!strlen($record['state']) && strlen($record['postal_code'])) {
			$record = array_merge($record, $db->fetchRow('select city,state from postal_codes where postal_code_id = ' . $record['postal_code']));
		}
		return $record;
	}

	function preSave() {
		$this->record['name'] = $this->record['firstName'] . ' ' . $this->record['lastName'];
	}

	/*
	function afterSave() {
		global $db, $wiki;
		$flexFields = array(
			'email'
		);
		// since each metadata field is now stored in its own row, loop through the fields and update the rows
		foreach($flexFields as $name) {
			$where = 'id = ' . $this->record['id'] . ' and field = "' . $name . '"';
			$value = array('id' => $this->record['id'], 'field' => $name, 'value' => stripslashes(trim($this->record[$name])));

			if(!$db->update($value, 'callers_flexData', $where)) {
				$db->insert($value, 'callers_flexData');
			}
		}
	}
	*/

	function getFormFields() {
		return array(
			'target' => array('type' => 'hidden', 'value' => 'edit'), // used to track whether the form was submitted
			'firstName' => array('label' => 'First Name'),
			'lastName' => array('label' => 'Last Name'),
			'email' => array('label' => 'Email', 'validation' => 'email'),
			'phone' => array('label' => 'Phone', 'validation' => 'phone'),
			'phone_extension' => array('label' => 'Phone Extension', 'validation' => 'numeric'),
			'address1' => array('label' => 'Address 1', 'validation' => 'required'),
			'address2' => array('label' => 'Address 2'),
			'postal_code' => array('label' => 'Postal Code', 'validation' => 'numeric', 'html' => 'onkeyup="zipInput(this)"'),
			'city' => array('label' => 'City'),
			'state' => array('type' => 'select#', 'label' => 'State', 'options' => statesArray(), 'validation'=>'alphabet'),
			'submit' => array('type'=>'submit', 'value' => 'Save')
		);
	}
}
?>

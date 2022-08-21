<?php
class callerSearchModel extends callerModel {
	function __construct() {
		global $db;
		parent::__construct();

		$this->table = 'callers';
		//$this->where[] = 'flags=1';
		$this->order = 'lastName,firstName';
		//$this->defaultData = array('type' => $type);
	}

	function getFormFields() {
		return array(
			'target' => array('type' => 'hidden', 'value' => 'search'), // used to track whether the form was submitted
			'firstName' => array('label' => 'First Name'),
			'lastName' => array('label' => 'Last Name'),
			'email' => array('label' => 'Email'),
			'phone' => array('label' => 'Phone'),
			'phone_extension' => array('label' => 'Phone Extension', 'validation' => 'numeric'),
			'submit' => array('type'=>'submit', 'value' => 'Search')
		);
	}
}
?>

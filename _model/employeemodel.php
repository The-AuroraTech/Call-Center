<?php
class employeeModel extends _Model {
	function __construct() {
		global $db;
		parent::__construct();

		$this->table = 'employees';
	}

	function getRecord2() {
		$this->record['roles'] = explode(',', $this->record['roles']);
		$this->record['password'] = '';
		return $this->record;
	}

	function preSave() {
		$this->record['roles'] = implode(',', $this->record['roles']);
		if(strlen($this->record['password']))
			$this->record['password'] = md5($this->record['password']);
		else // don't save an empty password
			unset($this->record['password']);
	}

	function getFormFields() {
		return array(
			'target' => array('type' => 'hidden', 'value' => 'edit'),
			'firstName' => array('label' => 'First Name'),
			'lastName' => array('label' => 'Last Name'),
			'email' => array('label' => 'Email'),
			'password' => array('type' => 'password', 'label' => 'Password'),
			'roles' => array('type' => 'checkbox', 'label' => 'Roles', 'options' => array('Standard','Administrator')),

			'submit' => array('type' => 'submit', 'name' => 'submit', 'value' => 'Save'),
		);
	}
}
?>

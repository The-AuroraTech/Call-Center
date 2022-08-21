<?php
class callSearchModel extends callModel {
	function __construct() {
		parent::__construct();

		$this->callStatus = array(1=>'Solved', 2=>'Not Solved');
		$this->callSubject = array(1=>'Sales', 2=>'Support', 3=>'Other');
	}

	function doSave() {
		return false;
	}

	/*
	function getFormFields() {
		global $db;
		// make use of callModel class's fields so we don't have to duplicate them
		$parentFields = parent::getFormFields();
		$fields = array();
		$fields[] = array('type' => 'multiselect#', 'name' => 'employee_id', 'label' => 'Employee(s)', 'options' => $db->fetchKeyValue('select id,firstname,lastName from employees order by lastName,firstName'));

		foreach($parentFields as $name=>$field) {
			switch($field['type']) {
				case 'select':
					$field['type'] = 'multiselect';
					break;
				case 'select#':
					$field['type'] = 'multiselect#';
					break;
			}
			unset($field['validation']);
			$fields[$name] = $field;

		}
		unset($fields['back']);
		unset($fields['save']);
		unset($fields['submit']['template']);
		$fields['submit']['value'] = 'Search';
		return $fields;
	}
	* */

	function getFormFields() {
		global $db;

		$rows = $db->fetchKeyValue('select id, (firstName || " " || lastName) as name from callers');
		$sz = sizeof($rows);
		$caller1 = array_slice($rows, 0, $sz/2, true);
		$caller2 = array_slice($rows, $sz/2, 0, true);

		$firstCall = $db->fetchCell('select startDateTime from calls order by startDateTime limit 1');
		$lastCall = $db->fetchCell('select startDateTime from calls order by startDateTime desc limit 1');
		$years = array(0=>'Year');
		if($firstCall && $lastCall) {
			$i = date('Y', strtotime($firstCall));
			$j = date('Y', strtotime($lastCall));
			for(;$i <= $j; $i++) {
				$years[$i] = $i;
			}

		} else {
			$i = date('Y');
			$years[$i] = $i;
		}
		$months = array('0' => 'Month');
		$j = strtotime('January 1, 2007'); 
		for($i = 0; $i < 12; $i++) {
			$months[ $i + 1 ] = date('F', strtotime('+' . $i . ' months', $j));
		}
		$days = array('0' => 'Day');
		for($i = 1; $i < 32; $i++) {
			$days[$i] = $i;
		}

		return array(
			'employee_id' => array('type' => 'checkbox#', 'label' => 'Employee(s)', 'options' => $db->fetchKeyValue('select id,(firstName || " " || lastName) as name from employees order by lastName,firstName')),

			'caller_id' => array('type' => 'multiselect#', 'label' => 'Caller', 'options' => $rows ),
			//'caller2' => array('type' => 'multiselect#', 'name' => 'callers', 'label' => 'Caller', 'options' => array()),

			'startYear' => array('type' => 'select#', 'label' => 'Start Date', 'options' => $years, 'template' => '<tr><td align="right">LABEL</td><td>FIELD'),
			'startMonth' => array('type' => 'select#', 'options' => $months, 'template' => 'FIELD'),
			'startDay' => array('type' => 'select#', 'options' => $days, 'template' => 'FIELD</td></tr>'),
			'endYear' => array('type' => 'select#', 'label' => 'End Date', 'options' => $years, 'template' => '<tr><td align="right">LABEL</td><td>FIELD'),
			'endMonth' => array('type' => 'select#', 'options' => $months, 'template' => 'FIELD'),
			'endDay' => array('type' => 'select#', 'options' => $days, 'template' => 'FIELD</td></tr>'),

			'subject' => array('type' => 'checkbox#', 'label' => 'Subject', 'options' => $this->callSubject),

			'status' => array('type' => 'checkbox#', 'label' => 'Outcome', 'html' => 'size="6"', 'options' => $this->callStatus),
			'notes' => array('type' => 'mediumtext', 'label' => 'Notes'),

			'submit' => array('type' => 'submit', 'value' => 'Search'),

		);
	}
}
?>

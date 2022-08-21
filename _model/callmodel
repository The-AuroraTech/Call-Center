<?php
class callModel extends _Model {
	function __construct() {
		global $db;
		parent::__construct();

		$this->table = 'calls';
		//$this->where[] = 'flags=1';
		$this->order = 'startDateTime desc';
		//$this->defaultData = array('type' => $type);

		$this->callStatus = array(1=>'Solved', 2=>'Not Solved');
		$this->callSubject = array(1=>'Sales', 2=>'Support', 3=>'Other');
	}

	function recent() {
		global $db;
		$this->order = '';
		return $db->fetch('select * from calls');
	}

	function prepareRecord($record, $index) {
		global $db;

		$contentField = $db->fetch('select field,value from calls_flexData where id =' . $record['id']);
		if($contentField) {
			foreach($contentField as $cf) {
				$f = $cf['field'];
				$record[$f] = $cf['value'];
			}
		}

		$row = $db->fetchRow('select firstName,lastName from callers where id = ' . $record['caller_id']);
		$record['caller'] = $row['firstName'] . ' ' . $row['lastName'];
		$row = $db->fetchRow('select firstName,lastName from employees where id = ' . $record['employee_id']);
		$record['employee'] = $row['firstName'] . ' ' . $row['lastName'];

		$record['subject'] = $this->callSubject[ $record['subject'] ];
		$record['status'] = $this->callStatus[ $record['status'] ];

		$s = $record['secondsDuration'];
		$record['duration'] = intval($s/3600) . 'h ' . intval($s/60) . 'm ' . ($s%60) . 's';

		$record['i'] = $index +1;
		return $record;
	}

	function preSave() {
		$this->record['employee_id'] = $_SESSION['id'];
		$this->record['caller_id'] = $_SESSION['newCall']['caller'];
		$this->record['startDateTime'] = $_SESSION['newCall']['startDateTime'];
		$this->record['secondsDuration'] = time() - strtotime($_SESSION['newCall']['startDateTime']);
	}

	function postSave() {
		global $db, $wiki;
		$flexFields = array(
			'status',
			'other'
		);
		// since each metadata field is now stored in its own row, loop through the fields and update the rows
		foreach($flexFields as $name) {
			$where = 'id = ' . $this->record['id'] . ' and field = "' . $name . '"';
			$value = array('id' => $this->record['id'], 'field' => $name, 'value' => stripslashes(trim($this->record[$name])));

			if(!$db->update($value, 'calls_flexData', $where)) {
				$db->insert($value, 'calls_flexData');
			}
		}

		$db->update(array('lastCallDateTime' => date('Y-m-d H:i:s')), 'callers', 'id=' . $this->record['caller_id']);
	}

	function getFormFields() {
		return array(
			'target' => array('type' => 'hidden', 'value' => 'edit'),
			'handler' => array('type' => 'hidden', 'value' => ''),
			'subject' => array('type' => 'radio#', 'label' => 'Subject', 'options' => $this->callSubject, 'validation' => 'required'),

			'status' => array('type' => 'radio#', 'label' => 'Outcome', 'html' => 'size="6"', 'options' => $this->callStatus, 'validation' => 'required'),
			'notes' => array('type' => 'mediumtext', 'label' => 'Notes', 'validation' => 'required'),

			'back' => array('type' => 'submit', 'value' => 'Back', 'template' => '<tr><td colspan="3" align="center">FIELD&nbsp;&nbsp;'),
			'save' => array('type' => 'submit', 'value' => 'Save for Later', 'template' => 'FIELD&nbsp;&nbsp;'),
			'submit' => array('type' => 'submit', 'value' => 'End Call', 'template' => 'FIELD</td></tr>'),

		);
	}
}
?>

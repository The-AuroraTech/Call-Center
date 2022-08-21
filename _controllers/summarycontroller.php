<?php
class summaryController extends _ControllerLinked {

	function execute($action) {

		$this->data['action'] = $action;
		if(isset($_SESSION[$action]['caller'])) {
			$caller = new callerModel();
			$caller->load($_SESSION[$action]['caller']);
			$this->data['caller'] = $caller->getRecord();
		}

		$this->data['template'] = 'summary';

		$_SESSION[$action] = array();
		$_SESSION[$action . '_history'] = array();
		return false;
	}
}
?>

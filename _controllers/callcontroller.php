<?php
class callController extends _ControllerLinked {

	function initialize() {
		$this->model = new callModel(); // used to build edit form
		$this->data = array();
	}

	/*
	*
	* */
	function execute($action) {
		$af = new autoform();
		$af->setTemplates($this->model->getFormFieldTemplates());
		$af->addFields($this->model->getFormFields());
		
		$showCallForm = true;		
		
		if(!empty($_POST)) {
			$_POST['handler'] = $_SESSION['id'];
			
			$af->fillValues($_POST);
			
			if($af->validate()) {
				$this->model->merge($af->values());
				$this->model->save();
				return true;
			}

		}
		
		if($showCallForm) {
			$this->data['callForm'] = $af->html();
		}

		if(!isset($_SESSION[$action]['startDateTime']))
			$_SESSION[$action]['startDateTime'] = date('Y-m-d H:i:s');

		$this->data['action'] = $action;
		$this->data['template'] = 'call';

		// used to show which caller we're creating a call for
		$caller = new callerModel();
		$caller->load($_SESSION[$action]['caller']);
		$this->data['caller'] = $caller->getRecord();

		// used to show previous calls from this caller
		$calls = new callModel();
		$calls->read('caller_id = ' . $_SESSION[$action]['caller']);
		$this->data['calls'] = $calls->getRecords();

		$this->data['hideCaller'] = true;
		$this->data['hideDuration'] = true;
		return false;
	}

	function beforeEdit() {
		$class = get_class($this);
		if(isset($_SESSION[$class . '_form']))
			return $_SESSION[$class . '_form'];
	}
	function failedValidation($data) {
		$_SESSION[get_class($this) . '_form'] = $data;
	}
	function afterSave() {
		unset($_SESSION[get_class($this) . '_form']);
	}
}
?>

<?php
class employeeController extends _ControllerLinked {

	function initialize() {
		$this->model = new employeeModel;
		$this->data = array();
	}

	function execute($action) {
		$this->data['action'] = $action;
		$this->data['template'] = 'employee';

		// only Administrators past this point
		if(!in_array('Administrator', $_SESSION['roles'])) {
			header('Location: /');
			exit;
		}

		if(method_exists($this, $action)) {
			return $this->$action($action);
		}
		return false;
	}

	function editEmployee($action) {
		$af  = new autoform();
		$af->addFields($this->model->getFormFields());
		$af->setTemplates($this->model->getFormFieldTemplates());
		switch($_POST['target']) {
			case 'edit':
				$this->model->load($_SESSION[$action]['id']);
				$af->fillValues($_POST);
				if($af->validate()) {
					$this->model->merge($af->getValues());
					$this->model->save();
				}
				break;

			case 'select':
				$_SESSION[$action]['id'] = $_POST['id'];
				$this->model->load($_POST['id']);
				$data = $this->model->getRecord2();
				unset($data['password']);
				$af->fillValues($data);
				break;

			default:
				// this will be used for resuming an edit in the future
				$_SESSION[$action]['employee'] = $_POST['id'];
				$_POST = array();
				$callers = new employeeModel();
				$callers->read();
				$this->data['employees'] = $callers->getRecords(); // shouldn't populate this once selected a caller to edit
				//$this->data['edit'] = $this->form(array($_SESSION[$action]['employee']));
				break;
		}
		$this->data['edit'] = $af->html();

	}

	function newEmployee($action) {
		if(!empty($_POST)) {
			$id = $this->form();
			if(is_numeric($id)) {
				return true;
			} else {
				$this->data['edit'] = $id;
			}
		} else
			$this->data['edit'] = $this->form(array($_SESSION[$action]['employee']));
		return false;
	}
}
?>

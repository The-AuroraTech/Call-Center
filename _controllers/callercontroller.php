<?php
class callerController extends _ControllerLinked {
	var $target;

	function initialize() {
		$this->model = new callerModel;
		$this->data = array();
	}

	function execute($action) {
		$this->data['action'] = $action;
		$this->data['template'] = 'caller';

		if(method_exists($this, $action)) {
			return $this->$action($action);
		}
		return false;
	}

	/*
	* The callerController link in the newCall chain
	* */
	function newCall($action) {
		$value = $this->editCaller($action);
		if($this->target == 'select')
			return true;
		else
			return $value;
	}

	function editCaller($action) {
		$callerModel = new callerModel();
		$callerSearchModel = new callerSearchModel();
		
		$callerEditForm = new autoform();
		$callerEditForm->addFields( $callerModel->getFormFields() );
		
		$callerSearchForm = new autoform();
		$callerSearchForm->addFields( $callerSearchModel->getFormFields() );
		
		$showEdit = true;
		$showTenRecent = true;

		if(!empty($_POST)) {
			// keep track of the target so we can reuse editCaller() logic from newCall()
			// lets us know when to return true and go to next 
			$this->target = $_POST['target'];
			switch($_POST['target']) {
				case 'search':
					$data = array_filter($_POST);
					unset($data['target']);
					unset($data['submit']);
					$callerSearchForm->fillValues($data);
					$where = array();
					foreach($data as $key=>$value) {
						$where[] = $key . " LIKE '%" . $value . "%'";
					}
					$callerSearchModel->read(implode(' and ', $where));
					$temp = $callerSearchModel->getRecords();
					$this->data['callerSearchResults'] = $callerSearchModel->getRecords();
					$_POST = array();
					$showEdit = false;
					break;

				case 'delete':
					/*
					$this->model->load($_POST['id']);
					$this->model->delete();
					* */
					break;

				case 'edit':
					$callerEditForm->fillValues($_POST);
					if($callerEditForm->validate()) {
						$data = $callerEditForm->values();
						$data['id'] = $_SESSION[$action]['caller']; 
						$callerModel->merge( $data );

						$_SESSION[$action]['caller'] = $callerModel->save();
						$_POST = array(); // clear so future links in chain don't attempt to process
						return true;
					}
					break;

				case 'select':
					$_SESSION[$action]['caller'] = $_POST['id'];
					$callerModel->read('id=' . $_POST['id']);
					$callerEditForm->fillValues( $callerModel->getRecord() );
					$this->data['edit'] = true;
					
					$showTenRecent = false;
					$_POST = array();					
					break;
			}
		}
		
		if($showEdit) {
			$this->data['form'] = $callerEditForm->html(); //array($_SESSION[$action]['caller']));
			$this->data['addEdit'] = true;
		}

		if($showTenRecent) {
			$callers = new callerModel();
			$callers->read('','lastCallDateTime desc','10'); // order by lastCallDateTime
			$this->data['callers'] = $callers->getRecords();
		}
		
		$this->data['searchForm'] = $callerSearchForm->html();
		return false;
	}

	function newCaller($action) {
		return $this->editCaller($action);
	}

	function select($action) {
		$callers = new callersModel();
		$callers->read();
		$this->data['listing'] = $callers->getRecords();
	}
}
?>

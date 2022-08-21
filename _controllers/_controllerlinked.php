<?php

abstract class _ControllerLinked extends chainLink {
	var $model;
	var $data;

	function __construct($object = null) {
		// create an instance of your model in the constructor to be used for edit?
		parent::__construct($object);
	}

	/*
	Default method
	*/
	function index() {
	}

	/*
	function add($parameters) {
		return $this->edit($parameters);
	}
	*/

	/*
	 * Used to generate a form field
	 * Most likely to edit a record
	 */
	/*
	function form($parameters = array()) {
		// process submit
			// read old data
			// replace with new
			// save object
			// use templator to show done
		// else
			// read record
			// use templator

		global $session; //, $formats;

		$data = array();
		$record = array();
		if(isset($parameters[0]))
			$this->model->load($parameters[0]);
		$fields = $this->model->getFormFields();
		$templates = $this->model->getFormFieldTemplates();

		$af = new autoform();
		$af->setTemplate('_start', '<form action="' . $this->editURL() . '" method="post" enctype="multipart/form-data"><table>'); // removes error printing at top of form
		$af->addFields($fields);
		$af->setTemplates($templates);

		if(!empty($_POST)) {
			$af->fillValues($_POST);
			if($af->validate() && $this->model->doSave()) {
				$formData = $af->values();

				$this->model->merge($formData);
				$this->model->save();

				$this->afterSave($formData);
				return $this->model->getID();
			} else
				$this->failedValidation( $af->getValues() );

		} else {
			$record = $this->model->getRecord2();
			$record = $this->preForm($record);
			$af->fillValues($record);
		}
		return $af->html();
	}
	*/

	/*
	 * Use this to prepare a record for the edit form
	 *
	 */
	/*
	function preForm($record) {
		return $record;
	}

	function failedValidation($record) {
	}

	function afterSave($record) {
	}
	*/

	function editURL() {
		return $_SERVER['REQUEST_URI'];
	}

	function output($parameters = array()) {
		global $templator;
		// call appropriate method and return the HTML
		$records = array();
		$template = 'index';
		if(sizeof($parameters)) {
			$method = $parameters[0];
			if(method_exists($this, $method)) {
				//strip off method
				array_shift($parameters);
				$records = $this->$method($parameters);
				$template = $method;
			} else
				$records = $this->index($parameters);
		} else
			$records = $this->index($parameters);

		if(!$records || !sizeof($records))
			return $this->noRecords($parameters);
		else {
			// plug in template calling stuff here
			// strip out Controller from class name
			$t = substr(get_class($this), 0, -10) . ucfirst($template) . '.html';
			//$templator->assign($records);
			//return $templator->output($t);
			return $records;
		}
	}

	function noRecords($parameters = array()) {
		return 'No records';
	}
}
?>

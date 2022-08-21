<?php

abstract class _Controller {
	var $model;

	function __construct() {
		// create an instance of your model in the constructor to be used for edit?
	}

	/*
	Default method
	*/
	function index() {
	}

	function add($parameters) {
		return $this->edit($parameters);
	}

	function edit($parameters = array()) {
		//exit;

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
			$this->model->read($this->model->uniqueField . '=' . $parameters[0]);

		$fields = $this->model->editFields();
		$templates = $this->model->editTemplates();

		//$fields['submit'] = array('type' => 'submit', 'value' => 'Submit');
		//$af->setTemplate('_start', '<form action="' . $this->editURL() . '" method="post" enctype="multipart/form-data"><table>');

		$af = new autoform();
		$af->addFields($fields);
		$af->setTemplates($templates);

		if(!empty($_POST)) {
			$af->fillValues($_POST);
			if($af->validate()) {
				$formData = $af->values();

				$this->model->merge($formData);
				$this->model->beforeSave();
				$this->model->save();
				$this->model->afterSave();
				header('Location: /');
				exit;
				//return $this->model->read('id=' . $parameters[1]);
			} else
				$this->failedValidate( $af->getValues() );

		} else {
			$this->model->prepareForEdit();
			$record = $this->model->asArray();
			$record = $this->beforeEdit($record);
			$af->fillValues($record);
		}
		//$data['form'] = $af->html();
		return $af->html(); //array('form' => $af->html());
	}

	function beforeEdit($record) {
		return $record;
	}

	function failedValidation($record) {
		return;
	}

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

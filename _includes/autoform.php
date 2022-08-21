<?php
/*
PHP Autoform 0.6.4 ...because hand-coding validation for form fields is soooo 90s.
Copyright (c) Alan Szlosek 2005-2007
http://www.greaterscope.net/project/Autoform

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA

See README and LICENSE for more information
*/

class autoform {
	var $templates;
	var $definition; // holds the fields definition array used to build the form fields
	var $names; // array with field names as the key with the value being an array of the field's index in autoform::definition
	var $arrayFields;

	// some frequently used arrays of field types
	var $types_select;
	var $types_selectSingle;
	var $types_selectMultiple;

	function autoform() {
		$this->clearFields();
		$this->templates = $this->defaultTemplates();
		$this->validationPatterns = $this->defaultValidationPatterns();
		
		$this->names = array();
		$this->arrayFields = array();

		$this->types_select = array('multiselect','multiselect#');
		$this->types_selectSingle = array('select','select#');
		$this->types_selectMultiple = array('multiselect','multiselect#');
		$this->types_arrayIncapable = array_merge($this->types_select, array('radio','radio#','submit'));
	}

	/*
	 * Allows one to set validation patterns
	 * */
	function setValidationPattern($name, $pattern, $message, $messagePlural) {
		$this->validationPatterns[$name] = array('pattern' => $pattern, 'message' => $message, 'messagePlural' => $messagePlural);
	}
	// alias for backward-compatibility
	function addValidation($name, $pattern, $message) {
		$this->setValidationPattern($name, $pattern, $message);
	}

	/*
	 * Pass in a template array and the specified templates will be used to construct the html when html() is called
	 * */
	function setTemplates($templates = array()) {
		foreach($templates as $name=>$value)
			$this->setTemplate($name, $value);
	}

	function setTemplate($name, $value) {
		$this->templates[$name] = $value;
	}

	/*
	 * Loops over an array of NAME=>DEFINITION pairs, adding them to the form definition
	 * */
	function addFields($definition) {
		foreach($definition as $name=>$def)
			$this->addField($name, $def);
	}

	/*
	 * Clear all definition definitions from the autoform object
	 * Useful when using the same autoform object to create two forms
	 * */
	function clearFields() {
		$this->definition = $this->names = $this->arrayFields = array();
	}

	/*
	 * Used to add a single definition named $name with attributes specified by $definition
	 * For valid field types, see defaultTemplates()
	 * */
	function addField($name, $definition) {
		// don't allow [ or ] in field name
		if(!preg_match('/^[a-zA-Z]+[a-zA-Z0-9_\[\]-]*$/', $name))
			die('Invalid character in field name: ' . $name);

		// default to text definition type
		if(!isset($definition['type']))
			$definition['type'] = 'text';

		if(!isset($definition['name']))
			$definition['name'] = $name;

		/*
		if(substr($definition['type'],0,5) != 'radio' && !in_array($name, $this->arrayFields))
			$this->arrayFields[] = $name;
		*/

		// default to using definition name if label was not provided
		if(!isset($definition['label']))
			$definition['label'] = $definition['name'];

		// convert the validation string into an array of validation patterns
		if(isset($definition['validation'])) {
			$definition['validationPatterns'] = array_map('trim', explode(',', $definition['validation']));
		} else
			$definition['validationPatterns'] = array();

		if(isset($definition['value']))
			$definition['_value'] = $definition['value'];
		else
			$definition['_value'] = null;

		// set up checkbox states
		if($definition['type'] == 'checkbox' || $definition['type'] == 'checkbox#') {
			if(!isset($definition['options'])) {
				$definition['options'] = array(1=>'');
				$definition['type'] .= '#'; // make it use 1 as the actual value of the checkbox
			} else { // probably meant this to be an array?
				if(!is_array($definition['options']))
					die('Please pass the value and label for the ' . $definition['label'] . ' field as KEY=>VALUE pairs to the options element.');
				if(sizeof($definition['options']) > 1)
					$this->arrayFields[] = $name;
			}
		}

		// fields that don't have an array of options set, make options false
		if(!isset($definition['options']))
			$definition['options'] = false;
		elseif(!is_array($definition['options']))
			die('The options element of the ' . $name . ' field definition should be an array or it should not be set.');

		// for select, multiselect, radio, checkbox make sure select option values are the same as their labels
		if(in_array($definition['type'], array('select','multiselect','radio','checkbox')) && sizeof($definition['options'])) {
			// can't use array_combine for this because it's only present in PHP5
			$opts = array();
			foreach($definition['options'] as $opt)
				$opts[ $opt ] = $opt;
			$definition['options'] = $opts;
		}

		// strip from all!
		if(substr($definition['type'], -1) == '#') {
			$definition['type'] = substr($definition['type'], 0, -1);
		}

		// there are really two types of arrays: explicit and forced
		if(in_array($definition['type'], $this->types_selectMultiple)) {
			if(!in_array($name, $this->arrayFields))
				$this->arrayFields[] = $name;
		}
		
		// SET DEFAULTS
		// used for inject HTML attributes into the field tag
		if(!isset($definition['html']))
			$definition['html'] = '';

		// default to empty string for the description
		if(!isset($definition['description']))
			$definition['description'] = '';

		if($definition['type'] == 'file' && !isset($definition['mode']))
			$definition['mode'] = 0755; // this might need to be a string

		$definition['errors'] = array();

		$i = sizeof($this->definition); // get index of field to be added to definitions
		$this->definition[] = $definition;
	
		// keep track of occurrences of field name. more than 1 will make the field an array
		// whether the field array is indexed or not, use only the name
		$tmp = explode('[', $name);
		$n = $tmp[0];
		if(!isset($this->names[$n]))
			$this->names[$n] = array();
		else
			$this->arrayFields[] = $n;
		$this->names[$n][] = $i;

		return $definition;
	}

	/*
	 * Assign values to the form fields from the specified associative array
	 * If you have a text definition called Name, passing array('Name' => 'Leto') to fillValues() will cause 'Leto' to be displayed in the box
	 * This value is then used by validate() to perform validation
	 * */
	function fillValues($data) {
		if(isset($data['__stripped_slashes'])) { // don't strip if they've been stripped
			$stripSlashes = false;
		} else { // strip if magic_quotes is on
			$stripSlashes = (get_magic_quotes_gpc() ? true : false);
		}

		// populate fields with their submitted values
		//foreach($this->definition as $i=>$definition) {
		foreach($data as $name=>$value) {
			//$name = $definition['name'];
			if(array_key_exists($name, $this->names)) { // if the field has a value present in the names array
				if(is_array($value) || sizeof($this->names[ $name ]) > 1) { // take care of fields that are arrays
					if($stripSlashes)
						$tmp = array_map('stripcslashes', $value);
					else
						$tmp = $data[$name];

					foreach($this->names[ $name ] as $i) {
						$definition = $this->definition[$i];
						// the following ensures that if the field has options, the values lie within
						if(is_array($definition['options'])) {
							$_value = array();
							foreach($tmp as $v) {
								if(isset($definition['options'][$v]))
									$_value[] = $v;
							}
							$this->definition[$i]['_value'] = $_value;

						} else // field doesn't have options
							$this->definition[$i]['_value'] = array_shift($tmp);
					}

				} else { // standard, single value/option field
					$tmp = ($stripSlashes ? stripcslashes($value) : $value);

					$i = $this->names[ $name ][0];
					if(!is_int($i))
						continue;
					$definition = $this->definition[$i];
					if(is_array($definition['options'])) {
						if(isset($definition['options'][$tmp]))
							$this->definition[$i]['_value'] = $tmp;
					} else
						$this->definition[$i]['_value'] = $tmp;
				}
					
			} else { // field doesn't have a new value specified
				if(in_array($name, $this->arrayFields)) {
					$this->values[$name] = array();
				}
			}
		}
	}

	function getFirstEmptyField($name) {
		$sz = sizeof($this->definition);
		for($i = 0; $i < $sz; $i++) {
			$d = $this->definition[$i];
			if($d['name'] == $name && sizeof($d['_value']) == 0)
		       		return $i;
		}
	}

	/*
	 * Clears all value elements of the definition fields
	 * */
	function clearValues() {
		$this->values = array();
	}

	/*
	 * Returns true if all fields validate, false if one or more fields fail
	 * */
	function validate() {
		$validationPatterns = $this->validationPatterns;
		// keeps track of which fields (by name) we've validated
		// helps eliminate duplicate validation error message when validating fields that are arrays
		// there may be a few flaws with this, such as when dealing with empty strings, but those belong to a bugfix release
		//$validatedFields = array();
		foreach($this->definition as $i=>$definition) {
			$name = $definition['name'];
			$fieldType = $definition['type'];

			if($fieldType != 'file' && ($fieldType == 'label' || sizeof($definition['validationPatterns']) == 0)) // skip labels
				continue;

			if($fieldType != 'file') {
				if(!is_array($definition['_value'])) // act as if we have an array of values for validation simplicity
					$values = array($definition['_value']);
				else { // array
					$values = $definition['_value'];
					if(sizeof($values) == 0) // we need something to test
						$values = array('');
				}

				foreach($definition['validationPatterns'] as $type) {
					if(!isset($validationPatterns[$type])) // invalid validation pattern
						die('Invalid Validation Pattern (' . $type . ') specified for the field named ' . $definition['name']);
					$passes = false;
					foreach($values as $value) {
						if(preg_match($validationPatterns[ $type ]['pattern'], $value))
							$passes = true;
					}
					if(!$passes) {
						$this->errors[ $type ][] = $definition['label'];
						$this->definition[ $i ]['errors'][] = $validationPatterns[ $type ]['message'];
					}
				}

			} else { // field is a file
				$errors = false;
				foreach($definition['validationPatterns'] as $type) {
					if(isset($this->validationPatterns[$type]) && !preg_match($this->validationPatterns[ $type ]['pattern'], $_FILES[$name]['name'])) {
						$this->errors[ $type ][] = $definition['label'];
						$this->definition[ $i ]['errors'][] = $validationPatterns[ $type ]['message'];
						$errors = true;
					}
				}
				if(!$errors && isset($_FILES[$name]) && is_uploaded_file($_FILES[$name]['tmp_name'])) {
					// if $definition['destination'] is a relative path, and exists within current working directory ($_SERVER['DOCUMENT_ROOT']) then we don't need to prepend the docroot to it
					$destination = (!is_dir($definition['destination']) ? $_SERVER['DOCUMENT_ROOT'] : '') . $definition['destination'];

					if(is_dir($destination))
						$destination .= $_FILES[$name]['name'];
					else {
						$parts = pathinfo($_FILES[$name]['name']);
						$destination .= '.' . $parts['extension'];
					}

					if(move_uploaded_file($_FILES[$name]['tmp_name'], $destination)) {
						$this->values[$name] = $destination;
						chmod($destination, $definition['mode']);
					}
					// do need some sort of error if move_uploaded_file failes
				} else
					unset($this->values[$name]);
			}
			//$validatedFields[] = $name; // keep track of this field so we don't try to validate it again
		}
		$this->failedValidation = $this->hasErrors();
		return !$this->failedValidation;
	}
	
	function injectError($name, $error) {
		foreach($this->definition as $i=>$field) {
			if($field['name'] == $name)
				$this->definition[$i]['errors'][] = $error;
		}
	}

	/*
	 * Used by validate() to check whether we had any validation errors.
	 * */
	function hasErrors() {
		foreach($this->validationPatterns as $name=>$details) {
			if(isset($this->errors[$name]) && sizeof($this->errors[$name]))
				return true;
		}
		return false;
	}

	/*
	 * Returns error messages for our hardcoded validation types.
	 * Used when a layout template is not passed to html()
	 * */
	function getErrors() {
		$output = '';
		foreach($this->validationPatterns as $key=>$value) {
			if(sizeof($this->errors[$key])) {
				$output .= '<b>' . $value['messagePlural'] . '</b><ul>';
				foreach($this->errors[$key] as $e)
					$output .= '<li>' . $e . '</li>';
				$output .= '</ul>';
			}
		}
		return $output;
	}
	
	/*
	 * Returns the HTML for all of the fields in the definition array.
	 * */
	function render($layout = null) {
		$replace = array($this->templates['start'], $this->templates['end']);
		if($layout) {
			$tokens = array('START','END');
			foreach($this->definition as $definition) {
				$name = strtoupper($definition['name']);
				$tokens = array_merge($tokens, array("$name.NAME", "$name.LABEL", "$name.FIELD", "$name.DESCRIPTION", "$name.ERROR"));
				$replace = array_merge($replace, $this->buildFieldTuple($definition));
			}
			$output = str_replace($tokens, $replace, $layout);

		} else {
			$tokens = array('NAME', 'LABEL','FIELD','DESCRIPTION', 'ERROR'); // will use these to replace tokens in the field templates.
			$output = str_replace('ERRORS', $this->getErrors(), $this->templates['start']);
			foreach($this->definition as $definition) {
				$template = $this->definitionTemplate($definition);
				$replace = $this->buildFieldTuple($definition);
				$output .= str_replace($tokens, $replace, $template); // do the replacement with the template
			}
			$output .= $this->templates['end'];
		}
		return $output;
	}
	// kept for backwards compatibility
	// should use render() instead of html()
	public function html($layout = null) {
		return $this->render($layout);
	}
	/*
	 * Pass this a file name (and path if necessary) and the form will be rendered using it
	 * */
	public function renderFile($template) {
		return $this->render(file_get_contents($template));
	}

	/*
	 * Gets the template string for the specified definition
	 * */
	function definitionTemplate($definition) {
		if(isset($definition['template'])) // check if a template is been manually provided in the field definition
			$template = $definition['template'];
		elseif(isset($definition['templateName'])) // check if a template name has been specified in the field definition
			$template = $this->templates[ $definition['templateName'] ];
		elseif(isset($this->templates[ $definition['type'] ]) && strlen($this->templates[ $definition['type'] ])) // check if there is a non-empty template for the field type
			$template = $this->templates[ $definition['type'] ];
		else // fall back to using the "_default" template
			$template = $this->templates['default'];
		return $template;
	}

	/*
	 * Generate a 4-element array (containing label, field, description, error) for a single field from it's definition
	 * Called from html()
	 * */
	function buildFieldTuple($definition) {
		$field = null; // will hold html for field

		$type = $definition['type'];
		$name = $definition['name'];
		$options = $definition['options'];
		$fieldName = $name;
		if(in_array($name, $this->arrayFields)) {
			$array = true;
			$fieldName = $name . '[]';
		} else
			$array = false;
		$value = $definition['_value'];

		if(!$array) {
			if(!is_array($options)) { // most common: field is not an array and has no options
				switch($definition['type']) {
					case 'hidden':
					case 'smalltext':
					case 'text':
					case 'password':
					case 'file':
					case 'button':
					case 'submit':
						if($type == 'smalltext') {
							$type = 'text';
							$definition['html'] .= ' size="5"';
						}
						$field = '<input type="' . $type . '" name="' . $fieldName . '" id="' . $fieldName . '" ' . (strlen($definition['html']) ? $definition['html'] : '') . ' value="' . $value . '" />';
						break;
					case 'textarea':
						$field = '<textarea name="' . $fieldName . '" id="' . $fieldName . '" ' . (strlen($definition['html']) ? $definition['html'] : '') . '>' . $value . '</textarea>';
						break;
					case 'mediumtext':
						$field = '<textarea name="' . $fieldName . '" id="' . $fieldName . '" cols="35" rows="5" ' . (strlen($definition['html']) ? $definition['html'] : '') . '>' . $value . '</textarea>';
						break;
					case 'largetext':
						$field = '<textarea name="' . $fieldName . '" id="' . $fieldName . '" cols="35" rows="15" ' . (strlen($definition['html']) ? $definition['html'] : '') . '>' . $value . '</textarea>';
						break;
					case 'label':
						$field = $value;
						break;
					default:
						die('Invalid field definition: ' . $name . '. Not array. No options.');
						break;
				}

			} else { // field is not array, yet has options
				switch($definition['type']) {
					case 'checkbox':
					case 'checkbox#':
						foreach($options as $optValue=>$optLabel) {
							$field .= '<input type="' . $type . '" name="' . $fieldName . '" id="' . $fieldName . $optValue . '" ' . (strlen($definition['html']) ? $definition['html'] : '') . ' value="' . $optValue . '"';
							if($optValue == $value)
								$field .= ' checked="checked"';
							$field .= ' /> ' .  str_replace(array('NAME','LABEL'), array($name . $optValue, $optLabel), $this->templates['_optionLabel']);
						}
						break;
					case 'radio':
					case 'radio#':
						$field = '';
						// radios should always be arrays, so we don't need to check
						foreach($options as $optValue=>$optLabel) {
							$field .= '<input type="radio" name="' . $fieldName . '" id="' . $fieldName . $optValue . '" ' . (strlen($definition['html']) ? $definition['html'] : '') . ' value="' . $optValue . '"';
							if(is_array($value)) { // value should be an array, no matter if there's only 1 element
								if(in_array($optValue, $value))
									$field .= ' checked="checked"';
							} else {
								if($optValue == $value)
									$field .= ' checked="checked"';
							}
							$field .= ' /> ' . str_replace(array('NAME','LABEL'), array($name . $optValue, $optLabel), $this->templates['_optionLabel']);
						}
						break;
					case 'select':
					case 'select#':
						$field = '<select name="' . $fieldName . '" ' . (strlen($definition['html']) ? $definition['html'] : '') . ' >';
						foreach($options as $optValue=>$optLabel) {
							$field .= '<option value="' . $optValue . '"';
							if($value == $optValue)
								$field .= ' selected="selected"';
							$field .= '>' . $optLabel . '</option>';
						}
						$field .= '</select>';
						break;
					default:
						die('Invalid field definition: ' . $name . '. Not array. Has options.');
						break;
				}
			}

		} else { // field is array
			if(!is_array($options)) { // field is an array without options
				switch($definition['type']) {
					case 'hidden':
					case 'smalltext':
					case 'text':
					case 'password':
					case 'button':
						if($type == 'smalltext')
							$type = 'text';

						$field = '<input type="' . $type . '" name="' . $fieldName . '" id="' . $fieldName . '" ' . (strlen($definition['html']) ? $definition['html'] : '') . ' value="' . $value . '" />';
						break;
					case 'textarea':
						$field = '<textarea name="' . $fieldName . '" id="' . $fieldName . '" ' . (strlen($definition['html']) ? $definition['html'] : '') . '>' . $value . '</textarea>';
						break;
					case 'mediumtext':
						$field = '<textarea name="' . $fieldName . '" id="' . $fieldName . '" cols="35" rows="5" ' . (strlen($definition['html']) ? $definition['html'] : '') . '>' . $value . '</textarea>';
						break;
					case 'largetext':
						$field = '<textarea name="' . $fieldName . '" id="' . $fieldName . '" cols="35" rows="15" ' . (strlen($definition['html']) ? $definition['html'] : '') . '>' . $value . '</textarea>';
						break;
					default:
						die('Invalid field definition: ' . $name . '. Is array. No options.');
						break;
				}

			} else { // field is an array and has options
				if(!is_array($value)) // suppress Warnings
					$value = array();
				switch($definition['type']) {
					case 'checkbox':
					case 'checkbox#':
						foreach($options as $optValue=>$optLabel) {
							$field .= '<input type="' . $type . '" name="' . $fieldName . '" id="' . $name . $optValue . '" ' . (strlen($definition['html']) ? $definition['html'] : '') . ' value="' . $optValue . '"';
							if(in_array($optValue, $value))
								$field .= ' checked="checked"';
							$field .= ' /> ' . str_replace(array('NAME','LABEL'), array($name . $optValue, $optLabel), $this->templates['_optionLabel']);
						}
						break;
					case 'select':
					case 'select#':
						$field = '<select name="' . $fieldName . '" ' . (strlen($definition['html']) ? $definition['html'] : '') . ' >';
						foreach($options as $optValue=>$optLabel) {
							$field .= '<option value="' . $optValue . '"';
							if($value == $optValue)
								$field .= ' selected="selected"';
							$field .= '>' . $optLabel . '</option>';
						}
						$field .= '</select>';
						break;
					case 'multiselect':
					case 'multiselect#':
						$field = '<select name="' . $fieldName . '" ' . (strlen($definition['html']) ? $definition['html'] : '') . ' multiple="multiple">';
						foreach($options as $optValue=>$optLabel) {
							$field .= '<option value="' . $optValue . '"';
							if(in_array($optValue, $value))
								$field .= ' selected="selected"';
							$field .= '>' . $optLabel . '</option>';
						}
						$field .= '</select>';
						break;
					default:
						die('Invalid field definition: ' . $name . '. Is array. Has options.');
						break;
				}
			}
		}
		$label = $definition['label'] . (in_array('required', $definition['validationPatterns']) ? $this->templates['required'] : ''); // prepare the field's label

		// include errors
		if(sizeof($definition['errors']))
			$error = str_replace('ERROR', array_shift($definition['errors']), $this->templates['fieldError']);
		else
			$error = '';

		return array($name, $label, $field, $definition['description'], $error); // prepare the values that will replace the tokens
	}

	/*
	 * Returns key=>value array
	 * This probably shouldn't return an element for checkboxes that haven't been selected ... or perhaps it should return an empty string
	 * */
	function getValues() {
		$data = array();
		foreach($this->names as $name=>$indices) {
			$i = $indices[0];
			if($this->definition[$i]['type'] == 'label')
				continue;
			if(sizeof($indices) > 1) {
				$data[$name] = array();
				foreach($indices as $i) {
					$data[$name][] = $this->definition[$i]['_value'];
				}
			} else {
				$data[$name] = $this->definition[$i]['_value'];
				/*
		if($definition['type'] != 'checkbox' || strlen($this->values[$name]))
			$value = $this->values[$name];
				 */
			}
		}

		return $data;
	}

	function values() {
		return $this->getValues();
	}
	function getValue($definition) {
		$name = $definition['name'];
		$value = '';
		if($definition['type'] == 'label')
			return $value;

		if($definition['type'] != 'checkbox' || strlen($definition['_value']))
			$value = $definition['_value'];
		return $value;
	}

	// DEFAULTS
	function defaultValidationPatterns() {
		return array(
			'required' => array(
				'pattern' =>'/\S+/',
				'messagePlural' => 'These fields are required:',
				'message' => 'The following field is required'
			), // this should remain the only field that won't allow an empty string
			'email' => array(
				'pattern' => '/^(|[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*@([-_a-zA-Z0-9]?([._-]?[a-zA-Z0-9]+))+\.[a-zA-Z]{2,})$/',
				'messagePlural' => 'These fields require a valid email address:',
				'message' => 'The following field requires a valid email address'
			),
			'zip' => array(
				'pattern' => '/^(|[0-9]{5}|[0-9]{5}-[0-5]{4})$/',
				'messagePlural' => 'These fields require a valid US postal code:',
				'message' => 'The following field requires a valid US postal code'
			),

			'phone' => array(
				'pattern' => '/^(|([\(\). -]*[0-9]{1}[\(\). -]*){10,})$/',
				'messagePlural' => 'These fields require a valid 10-digit phone number:',
				'message' => 'The following field requires a valid 10-digit phone number'
			),

			'alphabet' => array(
				'pattern' => '/^(|[a-zA-Z]+)$/',
				'messagePlural' => 'These fields only allow letters of the alphabet:',
				'message' => 'The following field only allows letters of the alphabet'
			),
			'password' => array(
				'pattern' => '/^(|[a-zA-Z0-9_].{4,})$/',
				'messagePlural' => 'These fields require a password longer than 3 characters:',
				'message' => 'The following field requires a password longer than 3 characters'
			),
			'numeric' => array(
				'pattern' => '/^(|[0-9]+)$/',
				'messagePlural' => 'These fields expect a numeric value:',
				'message' => 'The following field expects a numeric value'
			),

			'imageFile' => array(
				'pattern' => '/(jpg|jpeg|gif|png|bmp|tiff|tif)$/',
				'messagePlural' => 'These fields require an image file:',
				'message' => 'The following field requires an image file'
			)
		);
	}

	/*
	 * Returns default HTML templates for our definition types
	 * */
	function defaultTemplates() {
		/*
		If you want indented table tags, make use of these
		$nlTab = "\n\t";
		$nlTabTab = "\n\t\t";
		*/
		// don't really need to set all these blank templates. they're here for placeholders
		return array(
			'text' => '',
			'textarea' => '',
			'mediumtext' => '',
			'largetext' => '',
			'password' => '',
			'checkbox' => '',
			'checkbox#' => '',
			'radio' => '',
			'radio#' => '',
			'select' => '',
			'select#' => '',
			'multiselect' => '',
			'multiselect#' => '',
			'file' => '',
			'label' => '',
			'submit' => '<tr><td colspan="3" align="center">FIELD</td></tr>',
			'hidden' => 'FIELD',

			// non-definition, specialty templates
			'default' => 'ERROR<tr valign="top"><td align="right"><label for="NAME">LABEL</label></td><td>FIELD</td><td>DESCRIPTION</td></tr>' . "\n",
			'_optionLabel' => '<label for="NAME">LABEL</label>',
			'start' => '<form action="' . $_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '') . '" method="post" enctype="multipart/form-data"><table>' . "\n",
			
			// the following is the old start template that printing the errors at the top of the form. oldskool.
			//'start' => 'ERRORS<form action="" method="post" enctype="multipart/form-data"><table>' . "\n",
			
			'end' => '</table></form>',
			'errors' => "\n\t" . '<tr><td colspan="3">FIELD</td></tr>',
			'fieldError' => "\n\t" . '<tr><td colspan="3" align="center" style="color:red">ERROR</td></tr>', // single error for single field
			'required' => '<span class="required">*</span>'
		);
	}
}
?>

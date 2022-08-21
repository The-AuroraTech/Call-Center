<?php
/*
Vemplator 0.5 - Making MVC (Model/Vemplator/Controller) a reality
Copyright (C) 2007  Alan Szlosek

See LICENSE for license information.
*/

class vemplator {
	private $baseDirectory; // optional base path for template and compile directories
	private $compileDirectory; // directory to cache template files in. defaults to /tmp/
	private $templateDirectories; // array of directories to look for templates. can be relative to the baseDirectory
	private $data; // a stdClass object to hold the data passed to the template
	private $variableSyntax;
	private $outputModifiers;
	//private $variables;

	/**
	 * Notable actions:
	 * 	Sets the baseDirectory to the web server's document root
	 * 	Sets default compile path to /tmp/HTTPHOST
	 */
	function __construct() {
		$this->baseDirectory = $this->appendSeparator($_SERVER['DOCUMENT_ROOT']); // default to document root
		$this->compileDirectory = '/tmp/' . $_SERVER['HTTP_HOST'] . '/';
		$this->data = new stdClass;
		$this->variableSyntax = '\w+(\[\w+\]|\[\'\w+\'\]|\.\w+)+|\w+';
		$this->outputModifiers = array();
	}

	/*
	 * Makes sure folders have a trailing slash
	 */
	private function appendSeparator($path) {
		$path = trim($path);
		if(substr($path, strlen($path)-1, 1) != DIRECTORY_SEPARATOR)
			$path .= DIRECTORY_SEPARATOR;
		return $path;
	}

	public function compilePath($path) {
	       	// prepend with base path if specified path doesn't start with a slash
		$temp = $this->appendSeparator($path);
		$this->compileDirectory = ($temp{0} != '/' ? $this->baseDirectory . $temp : $temp);
	}

	public function outputModifier($name, $callback) {
		$this->outputModifiers[$name] = $callback;
	}
	private function callOM($name, $value) {
		$om = $this->outputModifiers[$name];
		return call_user_func($om,$value);
	}

	/**
	 * Assign a template variable.
	 * This can be a single key and value pair, or an associate array of key=>value pairs
	 */
	public function assign($key, $value = '') {
		if(is_array($key)) {
			foreach($key as $n=>$v)
				$this->data->$n = $v;
		} elseif(is_object($key)) {
			foreach(get_object_vars($key) as $n=>$v)
				$this->data->$n = $v;
		} else {
			$this->data->$key = $value;
		}
	}
	/**
	 * Alias for assign()
	 */
	public function set($key, $value = '') {
		$this->assign($key, $value);
	}

	/**
	 * Resets all previously-set template variables
	 */
	public function clear() {
		$this->data = new stdClass;
	}

	/**
	 * In charge of fetching and rendering the specified template
	 */
	public function output($template) {
		if(!is_array($template))
			$template = explode('|',$template);
		// go through and prepend template and compile directories with baseDirectory if needed
		$out = '';
		$foundTemplate = false;
		foreach($template as $t) {
			foreach(explode(PATH_SEPARATOR, get_include_path()) as $path) {
			//foreach($this->templateDirectories as $templateDirectory) {
				//$path = ($templateDirectory{0} != '/' ? $this->baseDirectory . $templateDirectory : $templateDirectory);
				$path = $this->appendSeparator($path);
				if(file_exists($path . $t)) {
					$out .= $this->bufferedOutput($path, $t);
					$foundTemplate = true;
					break; // found the template, so don't check any more directories
				}
			}
		}
		if(!$foundTemplate)
			die('Template (' . $t . ') not found in ' . $path);
		return $out;
	}

	/**
	 * Fetches the specified template, finding it in the specified path ... but only after trying to compile it first
	 */
	private function bufferedOutput($path, $template) {
		$this->compile($path, $template);

		ob_start();
		include($this->compileDirectory . $template . '.php');
		$out = ob_get_clean();
		return $out;
	}

	/**
	 * Compiles the template to PHP code and saves to file ... but only if the template has been updated since the last caching
	 * Uses Regular Expressions to identify template syntax
	 * Passes each match on to the callback for conversion to PHP
	 */
	private function compile($path, $template) {
		// moved from constructor
		if(!file_exists($this->compileDirectory))
			mkdir($this->compileDirectory);

		$templateFile = $path . $template;
		$compiledFile = $this->compileDirectory . $template . '.php';

		// don't spend time compiling if nothing has changed
		if(file_exists($compiledFile) && filemtime($compiledFile) >= filemtime($templateFile))
			return;

		$from = array(
			'/\{(if|switch):(' . $this->variableSyntax . ')\}/',
			'/\{(case):(' . $this->variableSyntax . '|[\']?[a-zA-Z0-9_.-]+[\']?)\}/', // match a variable or other constant ... more work should probably be done here
			'/\{(foreach):(' . $this->variableSyntax . '),(\w+)\}/',
			'/\{(foreach):(' . $this->variableSyntax . '),(\w+),(\w+)\}/',
			'/\{(include):(.*)\}/',
			'/\{(' . $this->variableSyntax . ')(:(' . implode('|', array_keys($this->outputModifiers)) . '))?\}/',
			'/\{(else|end|endswitch):\}/'
		);

		$lines = file($templateFile);
		$newLines = array();
		foreach($lines as $line)  {
			$newLine = preg_replace_callback($from, array(&$this, 'syntaxCallback'), $line); // calls syntaxCallback for each pattern match in $from
			$newLines[] = $newLine;
		}
		$f = fopen($compiledFile, 'w');
		fwrite($f, implode('',$newLines));
		fclose($f);
	}

	/**
	 * This is where most of the work occurs during compilation
	 * Takes a syntax match and converts it to proper PHP code
	 */
	private function syntaxCallback($matches) {
		$from = array('/\./', '/\[([a-zA-Z_]+)\]/', '/,/');
		$to = array('->', '[\$this->data->$1]', '$this->data->');

		$string = '<?php ';
		switch($matches[1]) { // check for a template statement
			case 'if':
			case 'switch':
				$string .= $matches[1] . '($this->data->' . preg_replace($from, $to, $matches[2]) . ') { ' . ($matches[1] == 'switch' ? 'default: ' : '');
				break;
			case 'foreach':
				$string .= 'foreach($this->data->' . preg_replace($from, $to, $matches[2]) . ' as ';
				$string .= '$this->data->' . preg_replace($from, $to, $matches[4]);
				if(isset($matches[5])) // prepares the $value portion of foreach($var as $key=>$value)
					$string .= '=>$this->data->' . preg_replace($from, $to, $matches[5]);
				$string .= ') { ';
				break;
			case 'end':
			case 'endswitch':
				$string .= '}';
				break;
			case 'else':
				$string .= '} else {';
				break;
			case 'case':
				if(preg_match('/^' . $this->variableSyntax . '$/', $matches[2])) // deal with variables in the case statement
					$string .= 'break; ' . $matches[1] . ' $this->data->' . preg_replace($from, $to, $matches[2]) . ':';
				else
					$string .= 'break; ' . $matches[1] . ' ' . $matches[2] . ':';
				break;
			case 'include':
				$string .= 'echo $this->output("' . $matches[2] . '");';
				break;
			default:
				if(sizeof($matches) == 5) {
					$string .= 'echo $this->callOM(\'' . $matches[4] . '\', $this->data->' . preg_replace($from, $to, $matches[1]) . ');';
				} else
					$string .= 'echo $this->data->' . preg_replace($from, $to, $matches[1]) . ';';
				break;
		}
		$string .= ' ?>';
		return $string;
	}
}
?>

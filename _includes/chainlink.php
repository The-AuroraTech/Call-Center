<?php
/*
chain of operation
chain of delegaion
operation chaining
responsibility chaining


Flow

1. Create chain
2. Pass action to walkChain() of start of chain
3. Each link will check if it has been encountered previously
4. If yes, call next link
5. If no, call a method
*/
abstract class chainLink {
	var $next;
	var $methodsForAction; // holds the acceptable methods that can be called per action specified at the start of the chain
	var $data; // holding place for data to be returned from walkChain

	function __construct($object = null) {
		$this->next = $object;
		$this->data = array();
		$this->initialize();
	}

	function initialize() {
	}

	function walkChain($action = '', $parameters = array()) {
		// check if first in chain, as determined by SESSION variable
		$class = get_class($this);
		$next = false;

		$sessionKey = $action . '_history';

		if(!isset($_SESSION[$sessionKey])) {
			$_SESSION[$sessionKey] = array();
		}
		/*
		if(isset($parameters['back'])) {
			$_SESSION[$sessionKey] = array_slice($_SESSION[$sessionKey], 0, sizeof($_SESSION[$sessionKey]) - $parameters['back']);
			// clear get/post
		}
		*/

		$sz = sizeof($_SESSION[$sessionKey]);
		if(in_array($class, $_SESSION[$sessionKey]) == true) { // if we've executed before, go to next link in chain
			//echo 'Skipping ' . $class . '<br />';
			$next = true;

			/*
			Place this inside logging stack test to allow one extra empty run before starting over
			*/
			if(!$this->next) { // last link in chain, start over on next page visit?
				$_SESSION[$sessionKey] = array();
				$next = false;
			}

		} else { // haven't executed before, so execute current link
			//echo 'Running ' . $class;
			if($this->execute($action)) { // only note that we've executed this class if execute() returns true
				//echo ' ... success';
				$_SESSION[$sessionKey][] = $class;
				$_SESSION[$sessionKey] = array_unique($_SESSION[$sessionKey]);
				$next = true;
			}// else
				//echo ' ... failure';
			//echo '<br />';

		}
		if($next && $this->next)
			$this->data = $this->next->walkChain($action);

		/*
		Place reset code here to start over immediately after running last link's execute() successfully
		*/
		return $this->data;
	}

	function execute($action) {
		return false;
	}
}
?>

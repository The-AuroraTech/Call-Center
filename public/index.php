<?php
require('../_includes/functions.php');
require('../_controllers/_Controller.php');
require('../_controllers/_ControllerLinked.php');
require('../_models/_Model.php');
require('../_includes/dbFacile.php');
require('../_includes/vemplator.php');

session_start();

require('../configuration.php');

$task = $_GET['task'];

if(isset($_GET['logout'])) {
	$_SESSION = array();
}

if(!empty($_POST) && isset($_POST['reset'])) {
	$sessionKey = $_POST['reset'];
	$_SESSION[$sessionKey] = array();
	$_SESSION[$sessionKey . '_history'] = array();
	$_SESSION[$sessionKey . '_form'] = array();
	$_POST = array();
}

if(!$_SESSION['loggedIn']) {// force login
	if(!empty($_POST) && isset($_POST['email']) && isset($_POST['password'])) {
		$row = $db->fetchRow("select id,roles,firstName,lastName from employees where email='" . $_POST['email'] . "' and password = '" . md5($_POST['password']) . "'");
		if($row) {
			$_SESSION['id'] = $row['id'];
			$_SESSION['loggedIn'] = true;
			$_SESSION['roles'] = explode(',', $row['roles']);
			$_SESSION['dateTime'] = date('Y-m-d H:i:s');
			$_SESSION['name'] = $row['firstName'] . ' ' . $row['lastName'];
		}
		$_POST = array();
	} else
		$task = 'home';
}

$templator = new vemplator();

switch($task) {
	case 'newCall':
		$templator->assign('task', 'New Call');
		$object = new callerController(new callController(new summaryController()));
		break;

	case 'editCaller':
	case 'newCaller':
		$templator->assign('task', 'Add/Edit Caller');
		$object = new callerController(new summaryController());
		break;

	case 'editEmployee':
	case 'newEmployee':
		$templator->assign('task', 'Add/Edit Employee');
		$object = new employeeController(new summaryController());
		break;

	case 'recentCalls':
	case 'searchCalls':
		$object = new callsController();
		break;

	default:
		$object = new homeController();
		break;
}

if(!isset($_SESSION[$task])) {
	$_SESSION[$task] = array(); // clean task data
	$_SESSION[$task . '_history'] = array(); // clean history stack
} else {
	// back is an integer of
	$history = $_SESSION[$task . '_history'];
	if(isset($_GET['back'])) {
		$_SESSION[$task . '_history'] = array_slice($history, 0, sizeof($history) - $_GET['back']);
		$_POST = $_GET = array();
	}
	if(isset($_POST['back'])) {
		$_SESSION[$task . '_history'] = array_slice($history, 0, sizeof($history) - 1);
		$_POST = $_GET = array();
	}

}



/*
if(!empty($_GET))
	$data = $object->walkChain($task, $_GET);
else
*/
	$data = $object->walkChain($task);


if($_SESSION['loggedIn']) {
	foreach($_SESSION['roles'] as $role)
		$templator->assign('is' . $role, true);
}

$templator->assign($data);
$templator->assign('loggedIn', $_SESSION['loggedIn']);
$templator->assign('url', (!isset($_GET['logout'])? $_SERVER['REQUEST_URI'] : '/?task=login')); // controller sets autoform start tag with script name

echo $templator->output($data['template'] . '.html');

/*
$GLOBALS['mainTemplator'] = new vemplator;
$GLOBALS['formats'] = array(
	'date' => 'D, j M Y H:i',
	'date#' => 'Y-m-d',
	// T (0)
	'zoneOffset' => 0 //(-3600 * 5) // off by 5 hours
);

$GLOBALS['templator']->basePath($_SERVER['DOCUMENT_ROOT']);
$GLOBALS['mainTemplator']->basePath($_SERVER['DOCUMENT_ROOT']);
*/

/*
$object = null;
if(in_array($_GET['section'], array('calls','callers','reports'))) {
	if(file_exists('_controllers/' . $section . 'Controller.php')) {
		$object = newSingleton($section . 'Controller');
	} else {
		exit;
	}
} else {
	exit;
}
*/


//echo end($_SESSION[ $_GET['task'] ]);

//var_dump($_SESSION);

/*
$data = $object->output($pathArray);

$mainTemplator->assign('section', $section);
$mainTemplator->assign('sectionPlural', $section . 's');

echo $mainTemplator->output($object->templateName);
*/
?>

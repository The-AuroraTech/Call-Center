<?php
class homeController extends _ControllerLinked {
	function execute() {
		global $db;
		if(isset($_SESSION['newCall_history']) && sizeof($_SESSION['newCall_history']))
			$this->data['newCall'] = true;
		$this->data['template'] = 'home';

		if($_SESSION['id']) {
			$this->data['name'] = $_SESSION['name'];
			$this->data['loginTime'] = $_SESSION['dateTime'];

			$seconds = time() - strtotime($_SESSION['dateTime']);
			$this->data['sinceLogin'] = intval($seconds/3600) . 'h ' . intval($seconds/60) . 'm ' . ($seconds%60) . 's';

			$calls = $db->fetchColumn('select secondsDuration from calls where employee_id = ' . $_SESSION['id'] . " and startDateTime like '" . date('Y-m-d') . "%'");
			if(!$calls)
				$calls = array();
			$sz = sizeof($calls);
			$this->data['callsToday'] = $sz;

			$sum = array_sum($calls);
			if($sum)
				$seconds = intval($sum / $sz);
			else
				$seconds = 0;
			$this->data['averageCallLength'] = intval($seconds/3600) . 'h ' . intval($seconds/60) . 'm ' . ($seconds %60). 's';
			}
		return false;
	}
}
?>

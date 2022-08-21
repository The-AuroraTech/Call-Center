<?php
function newSingleton($name) {
	if(!isset($GLOBALS[$name])) {
		if(substr($name, -5) == 'Model')
			require($_SERVER['DOCUMENT_ROOT'] . '/../_models/' . $name . '.php');
		elseif(substr($name, -10) == 'Controller')
			require($_SERVER['DOCUMENT_ROOT'] . '/../_controllers/' . $name . '.php');
		else
			require($_SERVER['DOCUMENT_ROOT'] . '/../_includes/' . $name . '.php');
		$GLOBALS[$name] = new $name;
	}
	return $GLOBALS[$name];
}

function __autoload($name) {
	if(substr($name, -5) == 'Model')
		require($_SERVER['DOCUMENT_ROOT'] . '/../_models/' . $name . '.php');
	elseif(substr($name, -10) == 'Controller')
		require($_SERVER['DOCUMENT_ROOT'] . '/../_controllers/' . $name . '.php');
	else
		require($_SERVER['DOCUMENT_ROOT'] . '/../_includes/' . $name . '.php');
}

function statesArray() {
	return $states = array('' => 'Select', 'AL' => 'Alabama',  'AK' => 'Alaska',  'AZ' => 'Arizona',  'AR' => 'Arkansas',  'CA' => 'California',  'CO' => 'Colorado',  'CT' => 'Connecticut',  'DE' => 'Delaware',  'DC' => 'District of Columbia',  'FL' => 'Florida',  'GA' => 'Georgia',  'HA' => 'Hawaii',  'ID' => 'Idaho',  'IL' => 'Illinois',  'IN' => 'Indiana',  'IA' => 'Iowa',  'KS' => 'Kansas',  'KY' => 'Kentucky',  'LA' => 'Louisiana',  'ME' => 'Maine',  'MD' => 'Maryland',  'MA' => 'Massachusetts',  'MI' => 'Michigan',  'MN' => 'Minnesota',  'MS' => 'Mississippi',  'MO' => 'Missouri',  'MT' => 'ontana',  'NE' => 'Nebraska',  'NV' => 'Nevada',  'NH' => 'New Hampshire',  'NJ' => 'New Jersey',  'NM' => 'New Mexico',  'NY' => 'New York',  'NC' => 'North Carolina',  'ND' => 'North Dakota',  'OH' => 'Ohio',  'OK' => 'Oklahoma',  'OR' => 'Oregon',  'PA' => 'Pennsylvania',  'RI' => 'Rhode Island',  'SC' => 'South Carolina',  'SD' => 'South Dakota',  'TN' => 'Tennessee',  'TX' => 'Texas',  'UT' => 'Utah',  'VT' => 'Vermont',  'VA' => 'Virginia',  'WA' => 'Washington',  'WV' => 'West Virginia',  'WI' => 'Wisconsin',  'WY' => 'Wyoming');
}
?>

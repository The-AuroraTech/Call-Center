<?php
require('../../_includes/functions.php');
require('../../_includes/dbFacile.php');
require('../../configuration.php');

$row = $db->fetchRow('select * from postal_codes where postal_code_id=' . $_GET['zip']);
echo $row['city'] . '|' . $row['state'];
?>

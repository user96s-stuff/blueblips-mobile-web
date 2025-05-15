<?php
require_once('utils.php');

$client = getTwitterClient();
$client->logout();

// Redirect to login page
header("Location: login.php");
exit;
?> 
<?php
$db_user = "system";
$db_pass = "1017#Jaya";
$db_host = "localhost/XE"; 

$conn = oci_connect($db_user, $db_pass, $db_host);
if (!$conn) {
    $e = oci_error();
    die("Database Connection Failed: " . $e['message']);
}
?>
<?php
// Oracle Database Connection for Hospital Module
$db_user = "system";
$db_pass = "1017#Jaya";
$db_host = "localhost/XE"; 

$conn = @oci_connect($db_user, $db_pass, $db_host);
if (!$conn) {
    $e = oci_error();
    die("Database Connection Failed: " . ($e['message'] ?? 'Could not connect to Oracle database. Please check XAMPP and Oracle XE service.'));
}
?>

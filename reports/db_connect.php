<?php
// Central Oracle Database Connection for Reports
$db_user = "system";
$db_pass = "1017#Jaya";
$db_host = "localhost/XE"; 

$conn = @oci_connect($db_user, $db_pass, $db_host);
if (!$conn) {
    $e = oci_error();
    die("<div style='font-family:sans-serif; padding:20px; color:#b91c1c; background:#fee2e2; border-radius:8px;'>
        <h3>Oracle Database Connection Failed</h3>
        <p>" . htmlspecialchars($e['message']) . "</p>
    </div>");
}
?>

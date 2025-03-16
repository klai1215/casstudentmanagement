<?php
$servername = "localhost";
$db_username = "root";
$db_password = "clarealover12@JK";
$dbname = "sms";

// Create connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
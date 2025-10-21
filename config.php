<?php
session_start();

$host = "localhost";
$user = "root";
$password = "1234567890";
$dbname = "exam";
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

function sanitize_input($data) {

    return trim($data);
}
?>

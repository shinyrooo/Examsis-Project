<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "exam";
$conn = new mysqli($host, $user, $password, $dbname);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}
?>
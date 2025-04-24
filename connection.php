<?php
$conn = new mysqli("localhost", "root", "", "contact_db", 3308);
if ($conn->connect_error) {
    file_put_contents("debug_log.txt", "DB Conn Error: " . $conn->connect_error . "\n", FILE_APPEND);
    die("Connection failed: " . $conn->connect_error);
} else {
    file_put_contents("debug_log.txt", "DB Conn Success\n", FILE_APPEND);
}

$result = $conn->query("SELECT * FROM contacts");
if ($result) {
    file_put_contents("debug_log.txt", "DB Query Success\n", FILE_APPEND);
} else {
    file_put_contents("debug_log.txt", "DB Query Failed\n", FILE_APPEND);
}
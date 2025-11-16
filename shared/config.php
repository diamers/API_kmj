<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "kmjshowrooms";
$port = 3306;

header('Content-Type: application/json');

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "DB gagal: " . $conn->connect_error]));
}

define("BASE_URL", "http://localhost/API_kmj");

?>
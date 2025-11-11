<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "maverick_kmj";
$port = 3306;
header('Content-Type: application/json');

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die(json_encode(["success"=>false,"message"=>"DB gagal: " . $conn->connect_error]));
}

echo json_encode(["success"=>true,"message"=>"Koneksi berhasil"]);
?>

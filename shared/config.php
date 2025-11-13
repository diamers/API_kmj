<?php
$host = "127.0.0.1";
$user = "admin";
$pass = "1234";
$db   = "maverick_kmj";
$port = 8889;
header('Content-Type: application/json');

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die(json_encode(["success"=>false,"message"=>"DB gagal: " . $conn->connect_error]));
}

// echo json_encode(["success"=>true,"message"=>"Koneksi berhasil"]);
?>
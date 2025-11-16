<?php
$host = "127.0.0.1";
$user = "admin";
$pass = "1234";
$dbname = "maverick_kmj";
$port = 8889;

header('Content-Type: application/json');

$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "DB gagal: " . $conn->connect_error]));
}

define("BASE_URL", "http://localhost/api_kmj");

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die(json_encode(["success" => false, "message" => "PDO gagal: " . $e->getMessage()]));
}
?>

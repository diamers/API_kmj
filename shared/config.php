<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "maverick_kmj";
$port = 3306;

header('Content-Type: application/json');

$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "DB gagal: " . $conn->connect_error]));
}

define("BASE_URL", "http://10.10.185.25:80/API_kmj");

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
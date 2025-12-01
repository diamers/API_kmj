<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// kalau browser kirim preflight OPTIONS, balas kosong saja
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "kmjshowroom4";
$port = 3306;

header('Content-Type: application/json');

$conn = new mysqli($host, $user, $pass, $dbname, $port);


if ($conn->connect_error) {
    die(json_encode(["code" => 500, "message" => "DB gagal: " . $conn->connect_error]));
}

define("BASE_URL", "http://192.168.1.41:80/API_kmj");

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
    die(json_encode(["code" => 500, "message" => "PDO gagal: " . $e->getMessage()]));
}
?>
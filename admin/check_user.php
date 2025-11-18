<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";

$query = $conn->query("SELECT COUNT(*) as user_count FROM users");

if ($query) {
    $result = $query->fetch_assoc();

    echo json_encode([
        "code" => 200,
        "message" => "Data user berhasil diambil",
        "user_count" => (int)$result['user_count']
    ]);
} else {
    echo json_encode([
        "code" => 500,
        "message" => "Gagal mengambil data user"
    ]);
}
?>

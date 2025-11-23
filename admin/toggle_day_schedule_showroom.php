<?php
require __DIR__ . "/../shared/config.php";
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

$hari = $input['hari'] ?? null;
$is_active = $input['is_active'] ?? null;

if (!$hari || $is_active === null) {
    echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
    exit;
}

$stmt = $conn->prepare("UPDATE showroom_schedule SET is_active=? WHERE hari=?");
$stmt->bind_param("is", $is_active, $hari);

if ($stmt->execute()) {
    echo json_encode([
        "code" => 200,
        "message" => "Status jadwal hari berhasil diperbarui"
    ]);
} else {
    echo json_encode([
        "code" => 500,
        "message" => "Gagal memperbarui status hari"
    ]);
}

<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST; 
}

// Ambil nilai lama
$query = $conn->query("SELECT * FROM showroom_general LIMIT 1");
if ($query->num_rows == 0) {
    echo json_encode([
        "code" => 404,
        "message" => "Data setting general tidak ditemukan"
    ]);
    exit;
}

$current = $query->fetch_assoc();

// Update hanya field yang dikirim
$showroom_status = isset($data["showroom_status"]) ? (int)$data["showroom_status"] : $current["showroom_status"];
$jual_mobil = isset($data["jual_mobil"]) ? (int)$data["jual_mobil"] : $current["jual_mobil"];
$schedule_pelanggan = isset($data["schedule_pelanggan"]) ? (int)$data["schedule_pelanggan"] : $current["schedule_pelanggan"];

// Update database
$stmt = $conn->prepare("
    UPDATE showroom_general 
    SET showroom_status = ?, 
        jual_mobil = ?, 
        schedule_pelanggan = ?
    WHERE id_general = ?
");

$stmt->bind_param("iiii", $showroom_status, $jual_mobil, $schedule_pelanggan, $current["id_general"]);

if ($stmt->execute()) {
    echo json_encode([
        "code" => 200,
        "message" => "Setting general berhasil diperbarui",
        "updated" => [
            "showroom_status" => $showroom_status,
            "jual_mobil" => $jual_mobil,
            "schedule_pelanggan" => $schedule_pelanggan
        ]
    ]);
} else {
    echo json_encode([
        "code" => 500,
        "message" => "Gagal memperbarui data"
    ]);
}

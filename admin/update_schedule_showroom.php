<?php
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

$id = $input['id_schedule'] ?? null;
$hari = $input['hari'] ?? null;
$slot_index = $input['slot_index'] ?? null;
$jam_buka = $input['jam_buka'] ?? null;
$jam_tutup = $input['jam_tutup'] ?? null;
$is_active = $input['is_active'] ?? 1;

if (!$id) {
    echo json_encode(["success" => false, "message" => "ID tidak ditemukan"]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE showroom_schedule 
    SET hari=?, slot_index=?, jam_buka=?, jam_tutup=?, is_active=? 
    WHERE id_schedule=?
");
$stmt->bind_param("sissii", $hari, $slot_index, $jam_buka, $jam_tutup, $is_active, $id);

if ($stmt->execute()) {
    echo json_encode(["code" => 200, "message" => "Slot jadwal diupdate"]);
} else {
    echo json_encode(["code" => 500, "message" => "Gagal update"]);
}

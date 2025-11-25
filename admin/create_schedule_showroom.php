<?php
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

$hari = $input['hari'] ?? null;
$slot_index = $input['slot_index'] ?? null;
$jam_buka = $input['jam_buka'] ?? null;
$jam_tutup = $input['jam_tutup'] ?? null;
$is_active = $input['is_active'] ?? 1;

if (!$hari || !$slot_index || !$jam_buka || !$jam_tutup) {
    http_response_code(400);
    echo json_encode(["code" => 400, "message" => "Data tidak lengkap"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO showroom_schedule (hari, slot_index, jam_buka, jam_tutup, is_active) VALUES (?,?,?,?,?)");
$stmt->bind_param("sissi", $hari, $slot_index, $jam_buka, $jam_tutup, $is_active);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode(["code" => 201, "message" => "Slot jadwal berhasil ditambahkan"]);
} else {
    http_response_code(500);
    echo json_encode(["code" => 500, "message" => "Gagal menambah slot"]);
}

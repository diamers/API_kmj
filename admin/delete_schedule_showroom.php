<?php
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');

$id = $_GET['id_schedule'] ?? null;

if (!$id) {
    echo json_encode(["code" => 400, "message" => "ID wajib diisi"]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM showroom_schedule WHERE id_schedule=?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(["code" => 200, "message" => "Slot jadwal berhasil dihapus"]);
} else {
    echo json_encode(["code" => 500, "message" => "Gagal hapus"]);
}

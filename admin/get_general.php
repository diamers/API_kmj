<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";

// Ambil baris pertama (karena biasanya cuma 1 baris data)
$query = $conn->query("SELECT * FROM showroom_general LIMIT 1");

if ($query->num_rows > 0) {
    $data = $query->fetch_assoc();
    echo json_encode([
        "code" => 200,
        "message" => "Data setting general berhasil diambil",
        "data" => [
            "id_general" => (int)$data["id_general"],
            "showroom_status" => (int)$data["showroom_status"],
            "jual_mobil" => (int)$data["jual_mobil"],
            "schedule_pelanggan" => (int)$data["schedule_pelanggan"]
        ]
    ]);
} else {
    echo json_encode([
        "code" => 404,
        "message" => "Data tidak ditemukan"
    ]);
}

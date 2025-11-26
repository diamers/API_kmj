<?php
require __DIR__ . "/../shared/config.php";
header("Content-Type: application/json");

// Hanya izinkan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "kode" => 405,
        "success" => false,
        "message" => "Metode tidak diizinkan (gunakan POST)"
    ]);
    exit;
}

// Ambil JSON body
$input = json_decode(file_get_contents("php://input"), true);

// Validasi
if (!isset($input["kode_user"]) || !isset($input["status"])) {
    http_response_code(400);
    echo json_encode([
        "kode" => 400,
        "success" => false,
        "message" => "Parameter tidak lengkap (butuh kode_user & status)"
    ]);
    exit;
}

$kode_user = $input["kode_user"];
$status    = (int)$input["status"]; // 1 = aktif, 0 = nonaktif

// Query update
$stmt = $conn->prepare("UPDATE users SET status = ? WHERE kode_user = ?");
$stmt->bind_param("is", $status, $kode_user);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "kode" => 500,
        "success" => false,
        "message" => "Gagal mengupdate status user",
        "error" => $stmt->error
    ]);
    exit;
}

// Cek apakah ada baris yang berubah
if ($stmt->affected_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "kode" => 404,
        "success" => false,
        "message" => "User tidak ditemukan"
    ]);
    exit;
}

$stmt->close();

// Sukses
http_response_code(200);
echo json_encode([
    "kode" => 200,
    "success" => true,
    "message" => "Status user berhasil diperbarui"
]);

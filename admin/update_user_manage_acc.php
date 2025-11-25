<?php
require __DIR__ . "/../shared/config.php";
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['kode_user'])) {
    http_response_code(400);
    echo json_encode([
        'kode' => 400,
        'success' => false,
        'message' => 'kode_user tidak boleh kosong'
    ]);
    exit;
}

$kode = $input['kode_user'];

$stmt = $conn->prepare("SELECT kode_user FROM users WHERE kode_user = ?");
$stmt->bind_param('s', $kode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'kode' => 404,
        'success' => false,
        'message' => 'User tidak ditemukan'
    ]);
    exit;
}

$full_name = $input['full_name'] ?? null;
$no_telp   = $input['no_telp'] ?? null;
$alamat    = $input['alamat'] ?? null;

$sql = "UPDATE users SET full_name=?, no_telp=?, alamat=?, updated_at=NOW() WHERE kode_user=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $full_name, $no_telp, $alamat, $kode);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode([
        'kode' => 201,
        'success' => true,
        'message' => 'User berhasil diperbarui'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'kode' => 500,
        'success' => false,
        'message' => 'Gagal memperbarui user'
    ]);
}
?>

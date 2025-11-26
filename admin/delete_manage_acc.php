<?php
require __DIR__ . "/../shared/config.php";
header('Content-Type: application/json');

if (!isset($_GET['kode_user'])) {
    http_response_code(400);
    echo json_encode([
        'kode' => 400,
        'success' => false,
        'message' => 'kode_user tidak boleh kosong'
    ]);
    exit;
}

$kode = $_GET['kode_user'];

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

$stmt = $conn->prepare("DELETE FROM users WHERE kode_user = ?");
$stmt->bind_param('s', $kode);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode([
        'kode' => 200,
        'success' => true,
        'message' => 'User berhasil dihapus'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'kode' => 500,
        'success' => false,
        'message' => 'Gagal menghapus user'
    ]);
}
?>

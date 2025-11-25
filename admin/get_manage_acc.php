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

$stmt = $conn->prepare("SELECT * FROM users WHERE kode_user = ?");
$stmt->bind_param('s', $kode);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    http_response_code(404);
    echo json_encode([
        'kode' => 404,
        'success' => false,
        'message' => 'User tidak ditemukan'
    ]);
    exit;
}

http_response_code(200);
echo json_encode([
    'kode' => 200,
    'success' => true,
    'message' => 'Data ditemukan',
    'data' => $data
]);
?>

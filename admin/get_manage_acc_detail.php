<?php
require __DIR__ . "/../shared/config.php";
header('Content-Type: application/json');

// cek request
if (!isset($_GET['kode_user']) || empty($_GET['kode_user'])) {
    http_response_code(400);
    echo json_encode([
        'kode'    => 400,
        'success' => false,
        'message' => 'kode_user wajib diisi'
    ]);
    exit;
}

$kode = $_GET['kode_user'];

try {
    $sql = "SELECT kode_user, full_name, email, username, role, avatar_url, 
                   no_telp, alamat, status, last_login, updated_at, created_at
            FROM users
            WHERE kode_user = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Gagal prepare query: " . $conn->error);
    }

    $stmt->bind_param("s", $kode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'kode'    => 404,
            'success' => false,
            'message' => 'Akun tidak ditemukan'
        ]);
        exit;
    }

    $data = $result->fetch_assoc();
    $data['status'] = (int)$data['status'];

    http_response_code(200);
    echo json_encode([
        'kode'    => 200,
        'success' => true,
        'message' => 'Data akun ditemukan',
        'data'    => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'kode'    => 500,
        'success' => false,
        'message' => 'Server error',
        'error'   => $e->getMessage()
    ]);
}

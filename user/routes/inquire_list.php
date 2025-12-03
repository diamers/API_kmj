<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../shared/config.php';        
require_once __DIR__ . '/../repos/inquire_repo.php'; 


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'code'    => 405,
        'message' => 'Method not allowed',
    ]);
    exit;
}

$kode_user = $_GET['kode_user'] ?? '';
if ($kode_user === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'code'    => 400,
        'message' => 'kode_user wajib diisi',
    ]);
    exit;
}


$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
if ($limit <= 0) $limit = 5;

$items = get_inquires_by_user($conn, $kode_user, $limit);

// kirim JSON bersih
echo json_encode([
    'success' => true,
    'code'    => 200,
    'message' => 'OK',
    'data'    => $items,
]);

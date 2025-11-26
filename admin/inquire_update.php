<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../shared/config.php';

function send(int $code, string $message, $data = null) {
    $res = ['code' => $code, 'message' => $message];
    if ($data !== null) $res['data'] = $data;
    echo json_encode($res);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send(400, 'Method not allowed (POST only)');
}

// dukung JSON & form-data
$raw = file_get_contents('php://input');
if (!empty($raw)) {
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $_POST = $json;
    }
}

$id_inquire = isset($_POST['id_inquire']) ? (int)$_POST['id_inquire'] : 0;
$status     = $_POST['status'] ?? '';

if (!$id_inquire || !$status) {
    send(400, 'Data tidak lengkap');
}

// mapping UI -> DB
if ($status === 'closed') {
    $status = 'canceled';
}

if (!in_array($status, ['pending','responded','canceled'], true)) {
    send(400, 'Status tidak valid');
}

$stmt = $conn->prepare("UPDATE inquire SET status = ? WHERE id_inquire = ?");
$stmt->bind_param('si', $status, $id_inquire);
$stmt->execute();

if ($stmt->affected_rows <= 0) {
    send(400, 'Data tidak ditemukan atau status sama');
}

send(200, 'Status berhasil diupdate');

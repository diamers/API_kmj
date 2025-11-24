<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../shared/config.php';

function respond($code, $msg) {
    echo json_encode(['code'=>$code,'message'=>$msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(400, "Method not allowed");
}

$id = $_POST['id_inquire'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || !$status) respond(400, "Data tidak valid");

if ($status === 'closed') $status = 'canceled';

if (!in_array($status, ['pending','responded','canceled'])) {
    respond(400, "Status tidak valid");
}

$stmt = $conn->prepare("UPDATE inquire SET status = ? WHERE id_inquire = ?");
$stmt->bind_param("si", $status, $id);
$stmt->execute();

if ($stmt->affected_rows <= 0) {
    respond(400, "Data tidak ditemukan atau tidak berubah");
}

respond(200, "Status berhasil diubah");

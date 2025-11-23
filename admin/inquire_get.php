<?php
header('Content-Type: application/json');

// SESUAIKAN PATH INI DENGAN STRUKTUR PROJECTMU
require_once '../db/config_api.php'; // misal: ../db/config_api.php

// Helper kirim respon JSON
function send_response(int $code, string $message = '', $data = null)
{
    $res = [
        'code'    => $code,
        'message' => $message,
    ];

    if ($data !== null) {
        $res['data'] = $data;
    }

    echo json_encode($res);
    exit;
}

// Hanya izinkan GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(400, 'Method not allowed (GET only)');
}

// FILTER OPSIONAL
$status    = $_GET['status']    ?? '';   // pending | responded | closed
$kode_user = $_GET['kode_user'] ?? '';   // kalau mau filter per user

// mapping: UI "closed" -> DB "canceled"
if ($status === 'closed') {
    $status = 'canceled';
}

// Base query
$sql = "
    SELECT 
        i.id_inquire,
        i.kode_user,
        i.kode_mobil,
        i.uji_beli,
        i.jenis_janji,
        i.tanggal,
        i.waktu,
        i.no_telp,
        i.status
        /* ==== JOIN KE USERS & MOBIL (OPTIONAL, SESUAIKAN NAMA KOLOM) ==== */
        , u.nama_lengkap AS nama_user
        , u.email        AS email_user
        , m.nama_mobil   AS nama_mobil
        , m.merk         AS merk_mobil
        , m.tahun        AS tahun_mobil
        /* ================================================================ */
    FROM inquire i
    LEFT JOIN users u ON i.kode_user = u.kode_user
    LEFT JOIN mobil m ON i.kode_mobil = m.kode_mobil
    WHERE 1=1
";

$params = [];
$types  = '';

if ($status !== '' && in_array($status, ['pending', 'responded', 'canceled'])) {
    $sql    .= " AND i.status = ?";
    $types  .= 's';
    $params[] = $status;
}

if ($kode_user !== '') {
    $sql    .= " AND i.kode_user = ?";
    $types  .= 's';
    $params[] = $kode_user;
}

$sql .= " ORDER BY i.tanggal DESC, i.waktu DESC";

try {
    if ($types !== '') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {

        // kembalikan 'canceled' sebagai 'closed' ke frontend
        if ($row['status'] === 'canceled') {
            $row['status'] = 'closed';
        }

        $rows[] = $row;
    }

    send_response(200, 'OK', $rows);

} catch (Exception $e) {
    send_response(400, 'Terjadi kesalahan: ' . $e->getMessage());
}

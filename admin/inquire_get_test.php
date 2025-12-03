<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../shared/config.php';   // pastikan path ini benar
// shared/config.php harus nge-define $conn (mysqli)

function respond(int $code, string $message = '', $data = null)
{
    http_response_code($code);

    $res = ['code' => $code];
    if ($message !== '') {
        $res['message'] = $message;
    }
    if ($data !== null) {
        $res['data'] = $data;
    }

    echo json_encode($res);
    exit;
}

// =========================
// 1) Ambil filter status (optional)
// =========================
$status = $_GET['status'] ?? '';
$status = trim($status);

// =========================
/*
   2) Query utama:
      - ambil data dari tabel inquire (alias i)
      - join ke tabel user (alias u) → untuk nama & email
      - join ke tabel mobil (alias m) → untuk nama_mobil
*/
// =========================
$sql = "
    SELECT
        i.id_inquire,
        i.kode_user,
        i.kode_mobil,
        i.tanggal,
        i.waktu,
        i.no_telp,
        i.note,
        i.status,
        i.uji_beli,
        i.jenis_janji,
        u.full_name AS nama_user,
        u.email     AS email_user,
        m.nama_mobil
    FROM inquire AS i
    LEFT JOIN users AS u
        ON u.kode_user = i.kode_user
    LEFT JOIN mobil AS m
        ON m.kode_mobil = i.kode_mobil
";

$params = [];
$types  = '';

// daftar status yang diizinkan untuk filter
$allowedStatus = ['pending', 'responded', 'closed', 'canceled'];

if ($status !== '' && $status !== 'all') {
    if (!in_array($status, $allowedStatus, true)) {
        respond(400, 'Status filter tidak valid');
    }

    $sql .= " WHERE i.status = ? ";
    $params[] = $status;
    $types   .= "s";
}

//urutkan dari apa hayo? based on id lah -nuril imoet
$sql .= " ORDER BY i.id_inquire DESC ";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    respond(500, 'Gagal prepare query: ' . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    respond(500, 'Gagal eksekusi query: ' . $stmt->error);
}

$result = $stmt->get_result();
$data   = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;   // kirim apa adanya: pending / responded / closed / canceled
}

respond(200, '', $data);

<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../shared/config.php';

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['code' => (string)$code, 'message' => $msg]);
    exit;
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id     = isset($_GET['id']) ? trim($_GET['id']) : '';

// =======================================================
// 1. LIST MOBIL → ?action=list
// =======================================================
if ($action === 'list') {
     $statusFilter = $_GET['status'] ?? '';  // <-- tambahan

    $where = '';
    if ($statusFilter !== '') {
        // amanin value
        $statusFilter = $conn->real_escape_string($statusFilter);
        $where = "WHERE m.status = '$statusFilter'";
    }

    $sql = "
      SELECT 
        m.kode_mobil,
        m.nama_mobil,
        m.tahun_mobil AS tahun,
        m.full_prize   AS full_price,
        m.uang_muka    AS dp,
        m.jarak_tempuh AS km,
        m.tenor,
        m.angsuran,
        m.jenis_kendaraan AS tipe,
        m.status
      FROM mobil m
      $where
      ORDER BY m.created_at DESC
    ";

    $res = $conn->query($sql);
    if (!$res) {
        json_error('Gagal mengambil list mobil: ' . $conn->error, 500);
    }

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = [
            'kode_mobil' => $row['kode_mobil'],
            'nama_mobil' => $row['nama_mobil'],
            'tahun'      => $row['tahun'],
        ];
    }

    echo json_encode([
        'code' => '200',
        'data'   => $data,
    ]);
    exit;
}

// =======================================================
// 2. DETAIL MOBIL → ?id=MB0000001
// =======================================================
if ($id === '') {
    json_error('Kode mobil tidak dikirim');
}

$sql = "
    SELECT 
        m.kode_mobil,
        m.nama_mobil,
        m.tahun_mobil,
        m.jarak_tempuh,
        m.full_prize,
        m.uang_muka,
        m.tenor,
        m.angsuran,
        m.jenis_kendaraan
    FROM mobil m
    WHERE m.kode_mobil = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    json_error('Gagal prepare query mobil: ' . $conn->error, 500);
}
$stmt->bind_param('s', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    json_error('Data mobil tidak ditemukan', 404);
}

$uang_muka = (int)($row['uang_muka'] ?? 0);
$fullPrice = $row['full_prize'] !== null ? (int)$row['full_prize'] : null;
$tenor     = (int)($row['tenor'] ?? 0);
$angsuran  = (int)($row['angsuran'] ?? 0);
$km        = (int)($row['jarak_tempuh'] ?? 0);

// --- FOTO DEPAN ---
$fotoPath = null;

$sqlFoto = "
    SELECT nama_file
    FROM mobil_foto
    WHERE kode_mobil = ?
      AND tipe_foto = 'depan'
    ORDER BY urutan ASC, id_foto ASC
    LIMIT 1
";

$stmt2 = $conn->prepare($sqlFoto);
if ($stmt2) {
    $stmt2->bind_param('s', $id);
    $stmt2->execute();
    $resFoto = $stmt2->get_result();
    if ($fotoRow = $resFoto->fetch_assoc()) {
        // contoh di DB: /images/mobil/mobil_xxx.jpg
        $fotoPath = $fotoRow['nama_file'];
    }
    $stmt2->close();
}

echo json_encode([
    'code'      => '200',
    'kode_mobil'  => $row['kode_mobil'],
    'nama_mobil'  => $row['nama_mobil'],
    'tahun'       => $row['tahun_mobil'],
    'km'          => $km,
    'full_price'  => $fullPrice,  
    'dp'          => $uang_muka,
    'tenor'       => $tenor,
    'angsuran'    => $angsuran,
    'tipe'        => $row['jenis_kendaraan'] ?? '-',
    'foto'        => $fotoPath, // /images/mobil/xxx.jpg
]);
exit;

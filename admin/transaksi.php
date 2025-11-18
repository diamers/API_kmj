<?php
header('Content-Type: application/json; charset=utf-8');

require '/../shared/config.php';

$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$req = array_merge($_GET ?? [], $_POST ?? [], $raw ?? []);

$action = $req['action'] ?? '';

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

//get list
if ($action === 'list') {

    $sql = "
      SELECT 
        t.kode_transaksi,
        t.nama_pembeli,
        m.nama_mobil,
        t.created_at AS tanggal,
        t.status,
        t.harga_akhir,
        u.full_name AS kasir
      FROM transaksi t
      LEFT JOIN mobil  m ON t.kode_mobil = m.kode_mobil
      LEFT JOIN users  u ON t.kode_user  = u.kode_user
      ORDER BY t.created_at DESC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        json_error('Gagal mengambil data transaksi: ' . $conn->error, 500);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'data'   => $rows,
    ]);
    exit;
}

//get detail
if ($action === 'detail') {
    $kode = trim($req['id'] ?? '');
    if ($kode === '') {
        json_error('Kode transaksi tidak dikirim');
    }

    $sql = "SELECT
              t.kode_transaksi,
              t.nama_pembeli,
              t.no_hp,
              t.tipe_pembayaran,
              t.harga_akhir,
              t.created_at,
              t.status,
              m.nama_mobil,
              m.tahun_mobil,
              u.full_name AS kasir
            FROM transaksi t
            LEFT JOIN mobil m ON t.kode_mobil = m.kode_mobil
            LEFT JOIN users u ON t.kode_user = u.kode_user
            WHERE t.kode_transaksi = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_error('Gagal prepare query: '.$conn->error, 500);
    }

    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        json_error('Data transaksi tidak ditemukan', 404);
    }

    echo json_encode([
        'status' => 'success',
        'data'   => $data,
    ]);
    exit;
}

//post create
if ($action === 'create') {
    $nama_pembeli    = trim($req['nama_pembeli'] ?? '');
    $no_hp           = trim($req['no_hp'] ?? '');
    $tipe_pembayaran = trim($req['tipe_pembayaran'] ?? '');
    $tipe_pembayaran = strtolower($tipe_pembayaran);
    $mapPembayaran = [
        'tunai'      => 'cash',
        'cash'       => 'cash',
        'kredit'     => 'kredit',
        'credit'     => 'kredit',
    ];
    if (isset($mapPembayaran[$tipe_pembayaran])) {
        $tipe_pembayaran = $mapPembayaran[$tipe_pembayaran];
    } else {
        $tipe_pembayaran = 'cash';
    }
    $harga_akhir     = $req['harga_akhir'] ?? null;
    $kode_mobil      = trim($req['kode_mobil'] ?? '');
    $kode_user       = trim($req['kode_user'] ?? '');
    $status          = trim($req['status'] ?? 'pending');

    // VALIDASI
    if ($nama_pembeli === '') json_error('Field nama_pembeli wajib diisi');
    if ($no_hp === '') json_error('Field no_hp wajib diisi');
    if ($tipe_pembayaran === '') json_error('Field tipe_pembayaran wajib diisi');
    if (!is_numeric($harga_akhir)) json_error('Field harga_akhir wajib numeric');
    if ($kode_mobil === '') json_error('Field kode_mobil wajib diisi');
    if ($kode_user === '') json_error('Field kode_user wajib diisi');

    // NORMALISASI STATUS
    $status = strtolower($status);
    $mapStatus = [
        'selesai'   => 'completed',
        'done'      => 'completed',
        'success'   => 'completed',
        'pending'   => 'pending',
        'menunggu'  => 'pending',
        'cancel'    => 'cancelled',
        'canceled'  => 'cancelled',
        'cancelled' => 'cancelled',
        'batal'     => 'cancelled'
    ];
    if (isset($mapStatus[$status])) {
        $status = $mapStatus[$status];
    } elseif (!in_array($status, ['completed','pending','cancelled'], true)) {
        $status = 'pending';
    }

    // GENERATE KODE TRANSAKSI
    $kode_transaksi = 'TRX' . date('YmdHis');

    // INSERT
    $sql = "INSERT INTO transaksi
            (kode_transaksi, nama_pembeli, no_hp, tipe_pembayaran, harga_akhir, kode_mobil, kode_user, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_error('Gagal prepare insert: '.$conn->error, 500);
    }

    $stmt->bind_param(
        'ssssisss',
        $kode_transaksi,
        $nama_pembeli,
        $no_hp,
        $tipe_pembayaran,
        $harga_akhir,
        $kode_mobil,
        $kode_user,
        $status
    );

    if (!$stmt->execute()) {
        json_error('Gagal menyimpan transaksi: '.$stmt->error, 500);
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Transaksi berhasil dibuat',
        'data'    => [
            'kode_transaksi' => $kode_transaksi,
            'status'         => $status
        ]
    ]);
    exit;
}

json_error('Action tidak valid', 400);

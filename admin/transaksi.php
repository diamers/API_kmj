<?php
header('Content-Type: application/json');
require '../shared/config.php';

// Gabungkan semua sumber input
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$req = array_merge($_GET ?? [], $_POST ?? [], $raw ?? []);

$action = $req['action'] ?? '';

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status'=>'error','message'=>$msg]); exit;
}

if ($action === 'create') {
    $nama_pembeli    = trim($req['nama_pembeli'] ?? '');
    $no_hp           = trim($req['no_hp'] ?? '');
    $tipe_pembayaran = trim($req['tipe_pembayaran'] ?? '');
    $harga_akhir     = $req['harga_akhir'] ?? null;
    $kode_mobil      = trim($req['kode_mobil'] ?? '');
    $kode_user       = trim($req['kode_user'] ?? '');
    $status          = trim($req['status'] ?? 'pending');

    // --- VALIDASI MINIMAL ---
    if ($nama_pembeli === '') json_error('Field nama_pembeli wajib diisi');
    if ($no_hp === '') json_error('Field no_hp wajib diisi');
    if ($tipe_pembayaran === '') json_error('Field tipe_pembayaran wajib diisi');
    if (!is_numeric($harga_akhir)) json_error('Field harga_akhir wajib numeric');
    if ($kode_mobil === '') json_error('Field kode_mobil wajib diisi');
    if ($kode_user === '') json_error('Field kode_user wajib diisi');

    // --- NORMALISASI STATUS ---
    $status = strtolower($status); // ubah ke huruf kecil semua
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

    // --- GENERATE KODE TRANSAKSI ---
    $kode_transaksi = 'TRX' . date('YmdHis');

    // --- INSERT QUERY ---
    $sql = "INSERT INTO transaksi
            (kode_transaksi, nama_pembeli, no_hp, tipe_pembayaran, harga_akhir, kode_mobil, kode_user, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssisss',
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
            'status' => $status
        ]
    ]);
    exit;
}

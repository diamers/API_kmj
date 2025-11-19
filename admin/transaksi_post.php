<?php
header('Content-Type: application/json; charset=utf-8');

require '../shared/config.php';

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// Pastikan cuma boleh POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method tidak diizinkan, gunakan POST', 405);
}

// Ambil dari POST + JSON body
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$req = array_merge($_POST ?? [], $raw ?? []);

$action = $req['action'] ?? 'create'; // default create kalau mau

// ================== POST CREATE ==================
if ($action === 'create') {
    $nama_pembeli    = trim($req['nama_pembeli'] ?? '');
    $no_hp           = trim($req['no_hp'] ?? '');
    $tipe_pembayaran = trim($req['tipe_pembayaran'] ?? '');
    $tipe_pembayaran = strtolower($tipe_pembayaran);
    $note            = trim($req['note'] ?? '');

    $mapPembayaran = [
        'tunai'  => 'cash',
        'cash'   => 'cash',
        'kredit' => 'kredit',
        'credit' => 'kredit',
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
            (kode_transaksi, nama_pembeli, no_hp, tipe_pembayaran, harga_akhir, kode_mobil, kode_user, status, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_error('Gagal prepare insert: '.$conn->error, 500);
    }

    $stmt->bind_param(
        'ssssissss',
        $kode_transaksi,
        $nama_pembeli,
        $no_hp,
        $tipe_pembayaran,
        $harga_akhir,
        $kode_mobil,
        $kode_user,
        $status,
        $note
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


// ================== POST UPDATE ==================
elseif ($action === 'update') {
    // ambil kode_transaksi yang mau di-update
    $kode_transaksi = trim($req['kode_transaksi'] ?? '');
    if ($kode_transaksi === '') {
        json_error('kode_transaksi wajib dikirim untuk update');
    }

    // ambil field lain sama kayak create
    $nama_pembeli    = trim($req['nama_pembeli'] ?? '');
    $no_hp           = trim($req['no_hp'] ?? '');
    $tipe_pembayaran = trim($req['tipe_pembayaran'] ?? '');
    $tipe_pembayaran = strtolower($tipe_pembayaran);
    $note            = trim($req['note'] ?? '');

    $mapPembayaran = [
        'tunai'  => 'cash',
        'cash'   => 'cash',
        'kredit' => 'kredit',
        'credit' => 'kredit',
    ];
    if (isset($mapPembayaran[$tipe_pembayaran])) {
        $tipe_pembayaran = $mapPembayaran[$tipe_pembayaran];
    } else {
        $tipe_pembayaran = 'cash';
    }

    $harga_akhir = $req['harga_akhir'] ?? null;
    $kode_mobil  = trim($req['kode_mobil'] ?? '');
    $kode_user   = trim($req['kode_user'] ?? '');
    $status      = trim($req['status'] ?? 'pending');

    // VALIDASI (boleh sama persis dg create)
    if ($nama_pembeli === '') json_error('Field nama_pembeli wajib diisi');
    if ($no_hp === '') json_error('Field no_hp wajib diisi');
    if ($tipe_pembayaran === '') json_error('Field tipe_pembayaran wajib diisi');
    if (!is_numeric($harga_akhir)) json_error('Field harga_akhir wajib numeric');
    if ($kode_mobil === '') json_error('Field kode_mobil wajib diisi');
    if ($kode_user === '') json_error('Field kode_user wajib diisi');

    // NORMALISASI STATUS (pakai blok yang sama dengan create)
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

    // QUERY UPDATE
    $sql = "UPDATE transaksi
            SET nama_pembeli    = ?,
                no_hp           = ?,
                tipe_pembayaran = ?,
                harga_akhir     = ?,
                kode_mobil      = ?,
                kode_user       = ?,
                status          = ?,
                note            = ?
            WHERE kode_transaksi = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_error('Gagal prepare update: '.$conn->error, 500);
    }

    // tipe: s s s i s s s s s  → 9 parameter
    $stmt->bind_param(
        'sssisssss',
        $nama_pembeli,
        $no_hp,
        $tipe_pembayaran,
        $harga_akhir,
        $kode_mobil,
        $kode_user,
        $status,
        $note,
        $kode_transaksi
    );

    if (!$stmt->execute()) {
        json_error('Gagal mengupdate transaksi: '.$stmt->error, 500);
    }

    if ($stmt->affected_rows === 0) {
        // tidak ada baris ter-update (mungkin kode_transaksi tidak ditemukan)
        json_error('Transaksi tidak ditemukan atau data tidak berubah', 404);
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Transaksi berhasil diupdate',
        'data'    => [
            'kode_transaksi' => $kode_transaksi,
            'status'         => $status,
        ],
    ]);
    exit;
}

json_error('Action tidak valid untuk POST', 400);

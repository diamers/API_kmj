<?php
header('Content-Type: application/json; charset=utf-8');

require '../shared/config.php';

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// Pastikan cuma boleh GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method tidak diizinkan, gunakan GET', 405);
}

// Ambil parameter dari query string
$req = $_GET ?? [];
$action = $req['action'] ?? '';

// ================== GET LIST ==================
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

// ================== GET DETAIL ==================
if ($action === 'detail') {
    $kode = trim($req['id'] ?? '');
    if ($kode === '') {
        json_error('Kode transaksi tidak dikirim');
    }

    $sql = "SELECT
          t.kode_transaksi,
          t.kode_mobil,
          t.nama_pembeli,
          t.no_hp,
          t.tipe_pembayaran,
          t.nama_kredit,
          t.harga_akhir,
          t.created_at,
          t.status,
          t.note,

          m.nama_mobil,
          m.tahun_mobil,
          m.jenis_kendaraan AS tipe_mobil,
          m.full_prize      AS full_price,

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

        // ==== AMBIL JAMINAN BERDASARKAN id_jaminan ====
    $sqlJ = "SELECT dj.id_jaminan
             FROM detail_jaminan dj
             WHERE dj.kode_transaksi = ?";
    $stmtJ = $conn->prepare($sqlJ);
    if ($stmtJ) {
        $stmtJ->bind_param('s', $kode);
        $stmtJ->execute();
        $resJ = $stmtJ->get_result();

        // default semua 0
        $jaminanFlags = [
            'ktp'      => 0,
            'kk'       => 0,
            'rekening' => 0,
        ];

        while ($rowJ = $resJ->fetch_assoc()) {
            $idJ = intval($rowJ['id_jaminan']);

            // ⚠️ SESUAIKAN ANGKA2 INI DENGAN TABEL `jaminan` DI DB-MU
            if ($idJ === 1) {
                $jaminanFlags['ktp'] = 1;
            } elseif ($idJ === 2) {
                $jaminanFlags['kk'] = 1;
            } elseif ($idJ === 3) {
                $jaminanFlags['rekening'] = 1;
            }
        }

        $stmtJ->close();

        $data['jaminan'] = $jaminanFlags;
    }


    echo json_encode([
        'status' => 'success',
        'data'   => $data,
    ]);
    exit;
}

json_error('Action tidak valid untuk GET', 400);

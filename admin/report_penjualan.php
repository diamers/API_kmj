<?php
header('Content-Type: application/json');

// ====== SESUAIKAN DENGAN PROJECTMU ======
require_once __DIR__ . '/../shared/config.php';    // pastikan ada $pdo di sini
require_once __DIR__ . '/../shared/response.php';  // kalau ada helper response_json()

// ---------- Helper fallback kalau response_json belum ada ----------
if (!function_exists('response_json')) {
    function response_json($status, $message, $data = null, $httpCode = 200) {
        http_response_code($httpCode);
        echo json_encode([
            'status'  => $status,
            'message' => $message,
            'data'    => $data
        ]);
        exit;
    }
}

// ---------- Cek koneksi DB ----------
global $pdo;
if (!isset($pdo) || !$pdo) {
    response_json(false, 'Koneksi database tidak tersedia di config.php', null, 500);
}

try {
    // ====== Ambil filter dari query string ======
    $status   = isset($_GET['status']) ? $_GET['status'] : 'completed';
    $fromDate = isset($_GET['from']) && $_GET['from'] !== '' ? $_GET['from'] : null; // YYYY-MM-DD
    $toDate   = isset($_GET['to'])   && $_GET['to']   !== '' ? $_GET['to']   : null; // YYYY-MM-DD

    $params = [];
    $where  = [];

    if ($status !== 'all') {
        $where[]          = 't.status = :status';
        $params['status'] = $status;
    }

    if ($fromDate !== null) {
        $where[]            = 'DATE(t.created_at) >= :fromDate';
        $params['fromDate'] = $fromDate;
    }

    if ($toDate !== null) {
        $where[]          = 'DATE(t.created_at) <= :toDate';
        $params['toDate'] = $toDate;
    }

    $whereSql = '';
    if (count($where) > 0) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    // ======================================================
    // QUERY ANTI-ONLY_FULL_GROUP_BY (pakai SUBQUERY)
    // ======================================================
    $sql = "
        SELECT
            x.periode_key,
            x.periode_label,
            COUNT(*) AS total_transaksi,
            SUM(x.harga_akhir) AS total_pendapatan,
            MIN(x.created_at) AS first_date,
            MAX(x.created_at) AS last_date
        FROM (
            SELECT
                t.harga_akhir,
                t.created_at,
                DATE_FORMAT(t.created_at, '%Y-%m') AS periode_key,
                DATE_FORMAT(t.created_at, '%M %Y') AS periode_label
            FROM transaksi t
            $whereSql
        ) AS x
        GROUP BY
            x.periode_key,
            x.periode_label
        ORDER BY
            last_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // mapping bulan Inggris ke Indonesia (optional)
    $indoMonths = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember',
    ];

    $laporan = [];
    $totalPendapatanAll = 0;
    $totalTransaksiAll  = 0;

    foreach ($rows as $r) {
        // ubah periode_label ke bahasa Indonesia
        $parts = explode(' ', $r['periode_label']); // ex: "October 2025"
        if (count($parts) == 2 && isset($indoMonths[$parts[0]])) {
            $periodeIndo = $indoMonths[$parts[0]] . ' ' . $parts[1];
        } else {
            $periodeIndo = $r['periode_label'];
        }

        $totalPendapatanAll += (float)$r['total_pendapatan'];
        $totalTransaksiAll  += (int)$r['total_transaksi'];

        $laporan[] = [
            'id'               => $r['periode_key'],        // contoh: "2025-10"
            'nama_laporan'     => 'Laporan Penjualan Bulanan',
            'periode'          => $periodeIndo,             // contoh: "Oktober 2025"
            'tanggal_generate' => date('d M Y H:i'),
            'total_transaksi'  => (int)$r['total_transaksi'],
            'total_pendapatan' => (float)$r['total_pendapatan'],
            'first_date'       => $r['first_date'],
            'last_date'        => $r['last_date'],
        ];
    }

    $jumlahLaporan = count($laporan);
    $rataTransaksi = $totalTransaksiAll > 0
    ? $totalPendapatanAll / $totalTransaksiAll
    : 0;


    $data = [
        'ringkasan' => [
            'total_laporan'       => $jumlahLaporan,
            'total_pendapatan'    => $totalPendapatanAll,
            'total_transaksi'     => $totalTransaksiAll,
            'rata_rata_transaksi' => $rataTransaksi,
        ],
        'items' => $laporan
    ];

    response_json(true, 'Berhasil mengambil laporan penjualan', $data);
} catch (Exception $e) {
    response_json(false, 'Terjadi kesalahan: ' . $e->getMessage(), null, 500);
}

<?php
header('Content-Type: application/json');

// pastikan path config bener
require_once __DIR__ . '/../shared/config.php';

$response = [
  'status' => false,
  'message' => '',
  'data' => []
];

try {
  if (!isset($_GET['periode']) || empty($_GET['periode'])) {
    throw new Exception('Parameter periode wajib diisi. Contoh: Januari 2025');
  }

  // contoh nilai yang diterima: "Januari 2025"
  $periodeLabel = $_GET['periode'];

  // pecah jadi ["Januari", "2025"]
  $parts = explode(' ', trim($periodeLabel));
  if (count($parts) != 2) {
    throw new Exception('Format periode tidak valid. Contoh: Januari 2025');
  }

  $namaBulan = $parts[0];
  $tahun     = $parts[1];

  $mapBulan = [
    'Januari'   => '01',
    'Februari'  => '02',
    'Maret'     => '03',
    'April'     => '04',
    'Mei'       => '05',
    'Juni'      => '06',
    'Juli'      => '07',
    'Agustus'   => '08',
    'September' => '09',
    'Oktober'   => '10',
    'November'  => '11',
    'Desember'  => '12',
  ];

  if (!isset($mapBulan[$namaBulan])) {
    throw new Exception('Nama bulan tidak dikenal.');
  }

  // ini yang dipakai di query: 2025-01
  $periodeKey = $tahun . '-' . $mapBulan[$namaBulan];

  // Koneksi PDO: variabel $pdo disiapkan dari config.php
  global $pdo;

  $sql = "
        SELECT 
            t.kode_transaksi,
            t.kode_mobil,
            t.harga_akhir,
            t.tipe_pembayaran,
            t.nama_pembeli,
            t.no_hp,
            t.status,
            t.created_at,
            m.nama_mobil
        FROM transaksi t
        LEFT JOIN mobil m ON t.kode_mobil = m.kode_mobil
        WHERE DATE_FORMAT(t.created_at, '%Y-%m') = :periode
        ORDER BY t.created_at DESC
    ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['periode' => $periodeKey]);

  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $response['status'] = true;
  $response['message'] = 'Berhasil mengambil data transaksi';
  $response['data'] = [
    'periode_label' => $periodeLabel, // "Januari 2025"
    'periode_key'   => $periodeKey,   // "2025-01"
    'items'         => $items,
  ];
} catch (Exception $e) {
  $response['status'] = false;
  $response['message'] = $e->getMessage();
}

echo json_encode($response);

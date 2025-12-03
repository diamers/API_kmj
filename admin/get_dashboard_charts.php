<?php
// API_kmj/admin/get_dashboard_charts.php
require __DIR__ . "/../shared/config.php"; // SAMA seperti API lain
header('Content-Type: application/json');

$response = [
  'code' => 400,
  'message' => 'Terjadi kesalahan',
];

try {
  // ========== LINE CHART: aktivitas 7 hari terakhir ==========
  $sqlLine = "
    SELECT DATE(created_at) AS tgl, COUNT(*) AS total
    FROM activities
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY tgl ASC
  ";
  $resultLine = $conn->query($sqlLine);

  // siapkan struktur 7 hari ke belakang
  $hariMap = [
    'Mon' => 'Sen',
    'Tue' => 'Sel',
    'Wed' => 'Rab',
    'Thu' => 'Kam',
    'Fri' => 'Jum',
    'Sat' => 'Sab',
    'Sun' => 'Min',
  ];

  $days = [];
  $clicks = [];

  // init 7 hari ke belakang (dari 6 hari lalu sampai hari ini)
  $dataIndex = [];
  for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} day"));
    $phpDay = date('D', strtotime($date)); // Mon, Tue, ...
    $label = $hariMap[$phpDay] ?? $phpDay;

    $days[] = $label;
    $clicks[] = 0; // default 0
    $dataIndex[$date] = count($days) - 1; // mapping tgl -> index array
  }

  // isi berdasarkan hasil query
  if ($resultLine && $resultLine->num_rows > 0) {
    while ($row = $resultLine->fetch_assoc()) {
      $tgl = $row['tgl'];
      if (isset($dataIndex[$tgl])) {
        $idx = $dataIndex[$tgl];
        $clicks[$idx] = (int) $row['total'];
      }
    }
  }

  // ========== BAR CHART: jumlah mobil per merk ==========
  $sqlMerk = "
    SELECT TRIM(SUBSTRING_INDEX(jenis_kendaraan, ' ', 1)) AS merk,
           COUNT(*) AS total
    FROM mobil
    GROUP BY merk
    ORDER BY total DESC
    LIMIT 5
  ";
  $resultMerk = $conn->query($sqlMerk);

  $merk_labels = [];
  $merk_values = [];

  if ($resultMerk && $resultMerk->num_rows > 0) {
    while ($row = $resultMerk->fetch_assoc()) {
      $merk_labels[] = $row['merk'];
      $merk_values[] = (int) $row['total'];
    }
  }

  $response['code'] = 200;
  $response['message'] = 'OK';
  $response['data'] = [
    'days' => $days,
    'clicks' => $clicks,
    'merk_labels' => $merk_labels,
    'merk_values' => $merk_values,
  ];
} catch (Exception $e) {
  $response['code'] = 400;
  $response['message'] = $e->getMessage();
}

echo json_encode($response);

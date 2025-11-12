<?php
require "../shared/config.php";

// $conn sekarang tetap ada
// Baca JSON jika ada
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
  $_POST = $input;
}


$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Metode tidak valid']);
  exit;
}

try {
  $nama_mobil = $_POST['nama_mobil'] ?? '';
  $tahun_mobil = (int) ($_POST['tahun'] ?? 0);
  $jarak_tempuh = (int) ($_POST['jarak_tempuh'] ?? 0);
  $uang_muka = (int) ($_POST['uang_muka'] ?? 0);
  $tenor = (int) ($_POST['tenor'] ?? 0);
  $angsuran = (int) ($_POST['angsuran'] ?? 0);
  $jenis_kendaraan = $_POST['tipe_kendaraan'] ?? '';
  $sistem_penggerak = $_POST['sistem_penggerak'] ?? '';
  $tipe_bahan_bakar = $_POST['bahan_bakar'] ?? '';
  $warna_interior = $_POST['warna_interior'] ?? '';
  $warna_exterior = $_POST['warna_exterior'] ?? '';
  $kode_user = 'US00000001';
  $fitur = $_POST['fitur'] ?? [];

  // Ambil kode mobil
  $kodeQuery = $conn->query("SELECT generate_kode_mobil() AS kode");
  if (!$kodeQuery)
    throw new Exception("Gagal ambil kode mobil: " . $conn->error);
  $kodeMobil = $kodeQuery->fetch_assoc()['kode'];

  // Insert ke tabel mobil
  $sql = "INSERT INTO mobil (kode_mobil,kode_user,nama_mobil,tahun_mobil,jarak_tempuh,
            uang_muka,tenor,angsuran,jenis_kendaraan,sistem_penggerak,
            tipe_bahan_bakar,warna_interior,warna_exterior) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";

  $stmt = $conn->prepare($sql);
  if (!$stmt)
    throw new Exception("Prepare gagal: " . $conn->error);

  $stmt->bind_param(
    "sssiiiissssss",
    $kodeMobil,
    $kode_user,
    $nama_mobil,
    $tahun_mobil,
    $jarak_tempuh,
    $uang_muka,
    $tenor,
    $angsuran,
    $jenis_kendaraan,
    $sistem_penggerak,
    $tipe_bahan_bakar,
    $warna_interior,
    $warna_exterior
  );

  if (!$stmt->execute())
    throw new Exception("Gagal insert mobil: " . $stmt->error);

  // Insert fitur
  if (!empty($fitur)) {
    $result = $conn->query("SELECT Id_detail_fitur FROM mobil_fitur ORDER BY Id_detail_fitur DESC LIMIT 1");
    $row = $result->fetch_assoc();
    $newNum = $row ? ((int) $row['Id_detail_fitur'] + 1) : 1;

    $stmtFitur = $conn->prepare("INSERT INTO mobil_fitur (Id_detail_fitur,kode_mobil,id_fitur) VALUES (?,?,?)");
    foreach ($fitur as $id_fitur) {
      $stmtFitur->bind_param("isi", $newNum, $kodeMobil, $id_fitur);
      $stmtFitur->execute();
      $newNum++;
    }
    $stmtFitur->close();
  }

  $stmt->close();
  $conn->close();

  echo json_encode(['success' => true, 'message' => 'Mobil & Fitur berhasil ditambahkan!']);

} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
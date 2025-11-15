<?php
require "../shared/config.php";
header("Content-Type: application/json");

// Ambil input JSON atau POST
$input = json_decode(file_get_contents('php://input'), true);
if ($input)
  $_POST = $input;

file_put_contents("debug_user.txt", print_r($_POST, true));

// Validasi method
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  echo json_encode([
    'success' => false,
    'code' => 405,
    'message' => 'Metode tidak valid. Gunakan POST.'
  ]);
  exit;
}

try {
  // Mode operasi
  $updateMode = !empty($_POST['update']);
  $deleteMode = !empty($_POST['delete']);

  $kodeMobil = $_POST['kode_mobil'] ?? '';

  $kodeUser = $_SESSION['kode_user'] ?? 'US001';

  // ===================== DELETE MOBIL =====================
  if ($deleteMode) {
    if (empty($kodeMobil))
      throw new Exception("Kode mobil wajib diisi untuk delete.");

    $conn->begin_transaction();
    try {
      // Hapus relasi (mobil_foto dan mobil_fitur)
      $tables = ['mobil_foto', 'mobil_fitur', 'mobil'];
      foreach ($tables as $table) {
        $stmt = $conn->prepare("DELETE FROM $table WHERE kode_mobil = ?");
        $stmt->bind_param("s", $kodeMobil);
        $stmt->execute();
        $stmt->close();
      }

      $conn->commit();

      echo json_encode([
        'success' => true,
        'code' => 200,
        'message' => 'Mobil dan relasinya berhasil dihapus.'
      ]);
      exit;
    } catch (Exception $ex) {
      $conn->rollback();
      throw $ex;
    }
  }

  // ===================== DATA UMUM =====================
  $data = [
    'nama_mobil' => $_POST['nama_mobil'] ?? '',
    'tahun_mobil' => (int) ($_POST['tahun'] ?? 0),
    'jarak_tempuh' => (int) ($_POST['jarak_tempuh'] ?? 0),
    'uang_muka' => (int) ($_POST['uang_muka'] ?? 0),
    'tenor' => (int) ($_POST['tenor'] ?? 0),
    'angsuran' => (int) ($_POST['angsuran'] ?? 0),
    'jenis_kendaraan' => $_POST['tipe_kendaraan'] ?? '',
    'sistem_penggerak' => $_POST['sistem_penggerak'] ?? '',
    'tipe_bahan_bakar' => $_POST['bahan_bakar'] ?? '',
    'warna_interior' => $_POST['warna_interior'] ?? '',
    'warna_exterior' => $_POST['warna_exterior'] ?? '',
    'status' => $_POST['status'] ?? 'available'
  ];
  // ananta ngekomen 2 baris di bawah
  // $fitur = $_POST['fitur'] ?? [];
  // $fotoList = $_POST['foto'] ?? [];
// di ganti berikut
  $fitur = $_POST['fitur'] ?? [];
  // Awalnya coba ambil dari POST (dipakai oleh mobile app)
  $fotoList = $_POST['foto'] ?? [];

  // =============== HANDLE UPLOAD FILE DARI WEB ADMIN ===============
  // Kalau dari web admin: $_POST['foto'] biasanya kosong,
  // tapi $_FILES berisi foto_360, foto_depan, dst.
  if (empty($fotoList) && !empty($_FILES)) {
    $fotoList = [];

    // Folder fisik tempat simpan file
    $projectRoot = dirname(__DIR__, 2);

    // Base URL yang nantinya disimpan di DB
    // dan dipakai <img src="...">
    $uploadDir = $projectRoot . '/uploadsImage_kmj/mobil/';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0775, true);
    }
    $publicBase = '/uploadsImage_kmj/mobil/';

    // Helper untuk 1 file (360, depan, belakang, samping)
    $addSingle = function ($field, $tipe, $urutanStart) use (&$fotoList, $uploadDir, $publicBase) {
      if (!isset($_FILES[$field]))
        return $urutanStart;

      $f = $_FILES[$field];
      if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] <= 0) {
        return $urutanStart;
      }

      $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
      $newName = uniqid('mobil_', true) . '.' . strtolower($ext);
      $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newName;

      if (move_uploaded_file($f['tmp_name'], $dest)) {
        $fotoList[] = [
          'tipe_foto' => $tipe,
          'nama_file' => $publicBase . $newName,
          'urutan' => $urutanStart,
        ];
        $urutanStart++;
      }

      return $urutanStart;
    };

    // Helper untuk multiple file (foto_tambahan[])
    $addMultiple = function ($field, $tipe, $urutanStart) use (&$fotoList, $uploadDir, $publicBase) {
      if (!isset($_FILES[$field]))
        return $urutanStart;

      $f = $_FILES[$field];
      if (!is_array($f['name']))
        return $urutanStart;

      $count = count($f['name']);
      for ($i = 0; $i < $count; $i++) {
        if ($f['error'][$i] !== UPLOAD_ERR_OK || $f['size'][$i] <= 0)
          continue;

        $ext = pathinfo($f['name'][$i], PATHINFO_EXTENSION);
        $newName = uniqid('mobil_', true) . '.' . strtolower($ext);
        $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newName;

        if (move_uploaded_file($f['tmp_name'][$i], $dest)) {
          $fotoList[] = [
            'tipe_foto' => $tipe,
            'nama_file' => $publicBase . $newName,
            'urutan' => $urutanStart,
          ];
          $urutanStart++;
        }
      }

      return $urutanStart;
    };

    // Urutan foto: 360, depan, belakang, samping, lalu tambahan
    $urut = 1;
    $urut = $addSingle('foto_depan', 'depan', $urut);
    $urut = $addSingle('foto_belakang', 'belakang', $urut);
    $urut = $addSingle('foto_samping', 'samping', $urut);
    $urut = $addSingle('foto_360', '360', $urut);
    $urut = $addMultiple('foto_tambahan', 'tambahan', $urut);
  }
  // sampai sini ananta menambahkan

  // ===================== UPDATE MOBIL =====================
  if ($updateMode) {
    if (empty($kodeMobil))
      throw new Exception("Kode mobil wajib diisi untuk update.");

    $conn->begin_transaction();
    try {
      // Ambil data lama
      $stmt = $conn->prepare("SELECT * FROM mobil WHERE kode_mobil = ?");
      $stmt->bind_param("s", $kodeMobil);
      $stmt->execute();
      $old = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$old)
        throw new Exception("Mobil dengan kode $kodeMobil tidak ditemukan.");

      // Gunakan nilai baru jika dikirim, jika tidak gunakan nilai lama
      foreach ($data as $k => &$v) {
        if (empty($_POST[$k]) && isset($old[$k])) {
          $v = $old[$k];
        }
      }

      // Update data mobil
      $sql = "UPDATE mobil SET 
                        nama_mobil=?, tahun_mobil=?, jarak_tempuh=?, uang_muka=?, tenor=?, angsuran=?,
                        jenis_kendaraan=?, sistem_penggerak=?, tipe_bahan_bakar=?,
                        warna_interior=?, warna_exterior=?, status=?
                    WHERE kode_mobil=?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param(
        "siiiissssssss",
        $data['nama_mobil'],
        $data['tahun_mobil'],
        $data['jarak_tempuh'],
        $data['uang_muka'],
        $data['tenor'],
        $data['angsuran'],
        $data['jenis_kendaraan'],
        $data['sistem_penggerak'],
        $data['tipe_bahan_bakar'],
        $data['warna_interior'],
        $data['warna_exterior'],
        $data['status'],
        $kodeMobil
      );
      $stmt->execute();
      $stmt->close();

      // ===================== FOTO (smart update) =====================
      if (!empty($fotoList)) {
        // Ambil id foto lama
        $existingIds = [];
        $res = $conn->query("SELECT id_foto FROM mobil_foto WHERE kode_mobil='$kodeMobil'");
        while ($r = $res->fetch_assoc()) {
          $existingIds[] = (int) $r['id_foto'];
        }

        $stmtUpdate = $conn->prepare("UPDATE mobil_foto SET tipe_foto=?, nama_file=?, urutan=? WHERE id_foto=?");
        $stmtInsert = $conn->prepare("INSERT INTO mobil_foto (kode_mobil, tipe_foto, nama_file, urutan) VALUES (?, ?, ?, ?)");
        $idsInRequest = [];

        foreach ($fotoList as $foto) {
          $id = $foto['id_foto'] ?? null;
          $tipe = in_array($foto['tipe_foto'] ?? '', ['360', 'depan', 'belakang', 'samping', 'tambahan'])
            ? $foto['tipe_foto'] : 'tambahan';
          $file = $foto['nama_file'] ?? '';
          $urut = (int) ($foto['urutan'] ?? 0);

          if ($id && in_array($id, $existingIds)) {
            $stmtUpdate->bind_param("ssii", $tipe, $file, $urut, $id);
            $stmtUpdate->execute();
            $idsInRequest[] = $id;
          } else {
            $stmtInsert->bind_param("sssi", $kodeMobil, $tipe, $file, $urut);
            $stmtInsert->execute();
            $idsInRequest[] = $conn->insert_id;
          }
        }

        // Hapus foto yang tidak dikirim
        $toDelete = array_diff($existingIds, $idsInRequest);
        if (!empty($toDelete)) {
          $in = implode(',', array_map('intval', $toDelete));
          $conn->query("DELETE FROM mobil_foto WHERE id_foto IN ($in)");
        }

        $stmtUpdate->close();
        $stmtInsert->close();
      }

      $conn->commit();

      echo json_encode([
        'success' => true,
        'code' => 200,
        'message' => 'Data mobil berhasil diperbarui.',
        'kode_mobil' => $kodeMobil
      ]);
      exit;
    } catch (Exception $ex) {
      $conn->rollback();
      throw $ex;
    }
  }

  // ===================== INSERT MOBIL BARU =====================
  $resKode = $conn->query("SELECT generate_kode_mobil() AS kode");
  if (!$resKode)
    throw new Exception("Gagal mengambil kode mobil: " . $conn->error);
  $kodeMobilBaru = $resKode->fetch_assoc()['kode'];

  $conn->begin_transaction();
  try {
    // Insert mobil utama
    $stmt = $conn->prepare("
            INSERT INTO mobil (
                kode_mobil, kode_user, nama_mobil, tahun_mobil, jarak_tempuh, uang_muka, tenor, angsuran,
                jenis_kendaraan, sistem_penggerak, tipe_bahan_bakar, warna_interior, warna_exterior, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    $stmt->bind_param(
      "sssiiiisssssss",
      $kodeMobilBaru,
      $kodeUser,
      $data['nama_mobil'],
      $data['tahun_mobil'],
      $data['jarak_tempuh'],
      $data['uang_muka'],
      $data['tenor'],
      $data['angsuran'],
      $data['jenis_kendaraan'],
      $data['sistem_penggerak'],
      $data['tipe_bahan_bakar'],
      $data['warna_interior'],
      $data['warna_exterior'],
      $data['status']
    );
    $stmt->execute();
    $stmt->close();

    // Insert fitur
    if (!empty($fitur)) {
      $stmt = $conn->prepare("INSERT INTO mobil_fitur (kode_mobil, id_fitur) VALUES (?, ?)");
      foreach ($fitur as $idFitur) {
        $stmt->bind_param("si", $kodeMobilBaru, $idFitur);
        $stmt->execute();
      }
      $stmt->close();
    }

    // Insert foto
    if (!empty($fotoList)) {
      $stmt = $conn->prepare("INSERT INTO mobil_foto (kode_mobil, tipe_foto, nama_file, urutan) VALUES (?, ?, ?, ?)");
      foreach ($fotoList as $foto) {
        $tipe = in_array($foto['tipe_foto'] ?? '', ['360', 'depan', 'belakang', 'samping', 'tambahan'])
          ? $foto['tipe_foto'] : 'tambahan';
        $file = $foto['nama_file'] ?? '';
        $urut = (int) ($foto['urutan'] ?? 0);
        $stmt->bind_param("sssi", $kodeMobilBaru, $tipe, $file, $urut);
        $stmt->execute();
      }
      $stmt->close();
    }

    $conn->commit();

    echo json_encode([
      'success' => true,
      'code' => 200,
      'message' => 'Mobil, fitur, dan foto berhasil ditambahkan.',
      'kode_mobil' => $kodeMobilBaru
    ]);
    exit;
  } catch (Exception $ex) {
    $conn->rollback();
    throw $ex;
  }

} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'code' => 500,
    'message' => $e->getMessage()
  ]);
}
?>
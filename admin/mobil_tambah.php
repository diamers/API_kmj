<?php
require __DIR__ . "/../shared/config.php";
require_once __DIR__ . "/../shared/path.php";
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

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

// Helper: agar bisa bind_param dinamis dengan call_user_func_array
function refValues($arr)
{
    // untuk PHP yang membutuhkan reference array
    $refs = [];
    foreach ($arr as $k => $v) {
        $refs[$k] = &$arr[$k];
    }
    return $refs;
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

        // ambil dulu semua path foto yang mau dihapus
        $fotoPaths = [];
        $stmt = $conn->prepare("SELECT nama_file FROM mobil_foto WHERE kode_mobil = ?");
        $stmt->bind_param("s", $kodeMobil);
        $stmt->execute();
        $resFoto = $stmt->get_result();
        while ($row = $resFoto->fetch_assoc()) {
            if (!empty($row['nama_file']))
                $fotoPaths[] = $row['nama_file'];
        }
        $stmt->close();

        $conn->begin_transaction();
        try {
            // Hapus relasi (mobil_foto, mobil_fitur) lalu mobil
            $tables = ['mobil_foto', 'mobil_fitur', 'mobil'];
            foreach ($tables as $table) {
                $stmt = $conn->prepare("DELETE FROM {$table} WHERE kode_mobil = ?");
                $stmt->bind_param("s", $kodeMobil);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            // hapus file fisik di images/mobil setelah commit
            $projectRoot = dirname(__DIR__);
            foreach ($fotoPaths as $path) {
                $filePath = parse_url($path, PHP_URL_PATH);
                if ($filePath === null || $filePath === false)
                    $filePath = $path;
                if (strpos($filePath, '/images/mobil/') === 0) {
                    $full = $projectRoot . $filePath;
                    if (is_file($full))
                        @unlink($full);
                }
            }

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
        'full_prize' => (int) ($_POST['full_prize'] ?? 0),
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

    $fitur = $_POST['fitur'] ?? [];
    $fotoList = $_POST['foto'] ?? [];

    // =============== HANDLE UPLOAD FILE DARI WEB ADMIN ===============
    if (empty($fotoList) && !empty($_FILES)) {
        $fotoList = [];

        $uploadDir = API_UPLOAD_DIR;
        $publicBase = API_PUBLIC_PATH;

        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0775, true);
        $publicBase = '/images/mobil/';

        // Helper untuk 1 file (360, depan, belakang, samping)
        $addSingle = function ($field, $tipe, $urutanStart) use (&$fotoList, $uploadDir, $publicBase) {
            if (!isset($_FILES[$field]))
                return $urutanStart;
            $f = $_FILES[$field];
            if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] <= 0)
                return $urutanStart;
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
                if (!isset($_POST[$k]) || $_POST[$k] === "") {
                    if (isset($old[$k]))
                        $v = $old[$k];
                }
            }

            // Update data mobil (perhatikan urutan field sesuai struktur DB)
            $sql = "UPDATE mobil SET 
                        nama_mobil=?, 
                        tahun_mobil=?, 
                        jarak_tempuh=?, 
                        full_prize=?, 
                        uang_muka=?, 
                        tenor=?, 
                        angsuran=?,
                        jenis_kendaraan=?, 
                        sistem_penggerak=?, 
                        tipe_bahan_bakar=?,
                        warna_interior=?, 
                        warna_exterior=?, 
                        status=?
                    WHERE kode_mobil=?";
            $stmt = $conn->prepare($sql);

            // buat type string dinamis: 1 s (nama) + 6 i (tahun,...,angsuran) + 7 s (jenis..,status,kode_mobil)
            $types = 's' . str_repeat('i', 6) . str_repeat('s', 7);

            $params = [
                $types,
                $data['nama_mobil'],
                $data['tahun_mobil'],
                $data['jarak_tempuh'],
                $data['full_prize'],
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
            ];
            call_user_func_array([$stmt, 'bind_param'], refValues($params));

            $stmt->execute();
            $stmt->close();

            // // ===================== FITUR (replace) =====================
            $stmt = $conn->prepare("DELETE FROM mobil_fitur WHERE kode_mobil = ?");
            $stmt->bind_param("s", $kodeMobil);
            $stmt->execute();
            $stmt->close();

            if (!empty($fitur) && is_array($fitur)) {
                $stmt = $conn->prepare("INSERT INTO mobil_fitur (kode_mobil, id_fitur) VALUES (?, ?)");
                foreach ($fitur as $idFitur) {
                    $idFitur = (int) $idFitur;
                    $stmt->bind_param("si", $kodeMobil, $idFitur);
                    $stmt->execute();
                }
                $stmt->close();
            }

            // ===================== FOTO (smart update) =====================
            if (!empty($fotoList)) {
                // Ambil id + nama_file lama
                $existingIds = [];
                $existingFiles = []; // [id_foto => nama_file_lama]
                $res = $conn->query("SELECT id_foto, nama_file FROM mobil_foto WHERE kode_mobil='" . $conn->real_escape_string($kodeMobil) . "'");
                while ($r = $res->fetch_assoc()) {
                    $id = (int) $r['id_foto'];
                    $existingIds[] = $id;
                    $existingFiles[$id] = $r['nama_file'];
                }

                $stmtUpdate = $conn->prepare("UPDATE mobil_foto SET tipe_foto=?, nama_file=?, urutan=? WHERE id_foto=?");
                $stmtInsert = $conn->prepare("INSERT INTO mobil_foto (kode_mobil, tipe_foto, nama_file, urutan) VALUES (?, ?, ?, ?)");
                $idsInRequest = [];
                $filesToDelete = [];

                foreach ($fotoList as $foto) {
                    $id = $foto['id_foto'] ?? null;
                    $tipe = in_array($foto['tipe_foto'] ?? '', ['360', 'depan', 'belakang', 'samping', 'tambahan']) ? $foto['tipe_foto'] : 'tambahan';
                    $file = $foto['nama_file'] ?? '';
                    $urut = (int) ($foto['urutan'] ?? 0);

                    if ($id && in_array($id, $existingIds)) {
                        $oldFile = $existingFiles[$id] ?? null;
                        if ($oldFile && $file && $file !== $oldFile)
                            $filesToDelete[] = $oldFile;
                        $stmtUpdate->bind_param("ssii", $tipe, $file, $urut, $id);
                        $stmtUpdate->execute();
                        $idsInRequest[] = $id;
                    } else {
                        $stmtInsert->bind_param("sssi", $kodeMobil, $tipe, $file, $urut);
                        $stmtInsert->execute();
                        $idsInRequest[] = $conn->insert_id;
                    }
                }

                // Hapus foto yang tidak dikirim (baris DB + file)
                $toDelete = array_diff($existingIds, $idsInRequest);
                if (!empty($toDelete)) {
                    foreach ($toDelete as $idDel) {
                        if (!empty($existingFiles[$idDel]))
                            $filesToDelete[] = $existingFiles[$idDel];
                    }
                    $in = implode(',', array_map('intval', $toDelete));
                    $conn->query("DELETE FROM mobil_foto WHERE id_foto IN ($in)");
                }

                $stmtUpdate->close();
                $stmtInsert->close();

                // hapus file fisik setelah DB commit
                $projectRoot = dirname(__DIR__);
                foreach ($filesToDelete as $path) {
                    $filePath = parse_url($path, PHP_URL_PATH);
                    if ($filePath === null || $filePath === false)
                        $filePath = $path;
                    if (strpos($filePath, '/images/mobil/') === 0) {
                        $full = $projectRoot . $filePath;
                        if (is_file($full))
                            @unlink($full);
                    }
                }
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
                kode_mobil, kode_user, nama_mobil, tahun_mobil, jarak_tempuh, full_prize, uang_muka, tenor, angsuran,
                jenis_kendaraan, sistem_penggerak, tipe_bahan_bakar, warna_interior, warna_exterior, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // types: 3x s, 6x i (tahun,jarak,full_prize,uang_muka,tenor,angsuran), 6x s
        $types = str_repeat('s', 3) . str_repeat('i', 6) . str_repeat('s', 6);

        $params = [
            $types,
            $kodeMobilBaru,
            $kodeUser,
            $data['nama_mobil'],
            $data['tahun_mobil'],
            $data['jarak_tempuh'],
            $data['full_prize'],
            $data['uang_muka'],
            $data['tenor'],
            $data['angsuran'],
            $data['jenis_kendaraan'],
            $data['sistem_penggerak'],
            $data['tipe_bahan_bakar'],
            $data['warna_interior'],
            $data['warna_exterior'],
            $data['status']
        ];
        call_user_func_array([$stmt, 'bind_param'], refValues($params));

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
                $tipe = in_array($foto['tipe_foto'] ?? '', ['360', 'depan', 'belakang', 'samping', 'tambahan']) ? $foto['tipe_foto'] : 'tambahan';
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
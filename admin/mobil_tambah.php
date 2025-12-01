<?php
// ✅ PERBAIKAN 1: Pastikan session dimulai agar $_SESSION['kode_user'] terbaca
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . "/../shared/config.php";
require_once __DIR__ . "/../shared/path.php";
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// ✅ TAMBAHAN: Parse JSON untuk request biasa (mobile)
$input = json_decode(file_get_contents('php://input'), true);
if ($input)
    $_POST = $input;

// ✅ TAMBAHAN: Parse multipart/form-data untuk web (saat ada file upload)
// Ketika ada $_FILES, PHP tidak otomatis parse field biasa ke $_POST
if (!empty($_FILES) && empty($_POST['nama_mobil'])) {
    // Coba ambil dari php://input boundary parsing
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'multipart/form-data') !== false) {
        // Ambil semua field dari $_REQUEST (kombinasi GET, POST, COOKIE)
        // Karena PHP sudah parse sebagian ke $_REQUEST saat ada multipart
        foreach ($_REQUEST as $key => $value) {
            if (!isset($_POST[$key])) {
                $_POST[$key] = $value;
            }
        }
    }
}

file_put_contents("debug_request.txt", print_r([
    'POST' => $_POST,
    'FILES' => $_FILES,
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    '_REQUEST' => $_REQUEST // tambahan untuk debug
], true));

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode([
        'success' => false,
        'code' => 405,
        'message' => 'Metode tidak valid. Gunakan POST.'
    ]);
    exit;
}

function refValues($arr)
{
    $refs = [];
    foreach ($arr as $k => $v) {
        $refs[$k] = &$arr[$k];
    }
    return $refs;
}

try {
    $updateMode = !empty($_POST['update']);
    $deleteMode = !empty($_POST['delete']);

    $kodeMobil = $_POST['kode_mobil'] ?? '';
    
    $kodeUser = $_SESSION['kode_user'] ?? null;
    
    if (!$kodeUser) {
        $kodeUser = $_POST['kode_user'] ?? null;
    }
    
    // ✅ TAMBAHAN: Debug kode_user
    file_put_contents("debug_kode_user.txt", print_r([
        'kodeUser_from_SESSION' => $_SESSION['kode_user'] ?? 'KOSONG',
        'kodeUser_from_POST' => $_POST['kode_user'] ?? 'KOSONG',
        'kodeUser_final' => $kodeUser ?? 'KOSONG',
        'all_POST' => $_POST
    ], true));
    
    if (!$kodeUser && !$deleteMode) {
        throw new Exception("User belum login. Silakan login terlebih dahulu.");
    }

    // ===================== DELETE MOBIL =====================
    if ($deleteMode) {
        if (empty($kodeMobil))
            throw new Exception("Kode mobil wajib diisi untuk delete.");

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
            $tables = ['mobil_foto', 'mobil_fitur', 'mobil'];
            foreach ($tables as $table) {
                $stmt = $conn->prepare("DELETE FROM {$table} WHERE kode_mobil = ?");
                $stmt->bind_param("s", $kodeMobil);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

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

    $isAndroidRequest = !empty($_FILES) && (
        isset($_FILES['foto_360']) || 
        isset($_FILES['foto_depan']) || 
        isset($_FILES['foto_belakang']) || 
        isset($_FILES['foto_samping']) ||
        isset($_FILES['foto_tambahan_slot_0']) ||
        isset($_FILES['foto_tambahan_slot_1']) ||
        isset($_FILES['foto_tambahan_slot_2']) ||
        isset($_FILES['foto_tambahan_slot_3']) ||
        isset($_FILES['foto_tambahan_slot_4']) ||
        isset($_FILES['foto_tambahan_slot_5'])
    );

    $uploadDir = API_UPLOAD_DIR;
    $publicBase = '/images/mobil/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    // =============== HANDLE UPLOAD FILE (ANDROID) ===============
    if ($isAndroidRequest) {
        file_put_contents("debug_android.txt", "=== ANDROID UPLOAD START ===\n", FILE_APPEND);

        $fotoList = [];
        
        $fotoMapping = [
            'foto_360' => '360',
            'foto_depan' => 'depan',
            'foto_belakang' => 'belakang',
            'foto_samping' => 'samping'
        ];

        // Urutan: 1=360, 2=depan, 3=belakang, 4=samping, 5-10=tambahan
        $urutanUtama = ['360' => 1, 'depan' => 2, 'belakang' => 3, 'samping' => 4];

        // ✅ Process foto utama (4 FIXED SLOTS dengan urutan tetap)
        foreach ($fotoMapping as $field => $tipe) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $f = $_FILES[$field];
                $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                $newName = uniqid('mobil_', true) . '.' . strtolower($ext);
                $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newName;

                if (move_uploaded_file($f['tmp_name'], $dest)) {
                    $urutan = $urutanUtama[$tipe];
                    
                    // Cek apakah foto ini sudah ada di DB
                    $stmt = $conn->prepare("SELECT id_foto, nama_file FROM mobil_foto WHERE kode_mobil = ? AND tipe_foto = ? LIMIT 1");
                    $stmt->bind_param("ss", $kodeMobil, $tipe);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($row) {
                        // REPLACE foto utama
                        $entry = [
                            'tipe_foto' => $tipe,
                            'nama_file' => $publicBase . $newName,
                            'urutan' => $urutan,
                            'id_foto' => (int)$row['id_foto'],
                            'old_file' => $row['nama_file']
                        ];
                        file_put_contents("debug_android.txt", "REPLACE UTAMA: $tipe (urutan=$urutan, id={$entry['id_foto']})\n", FILE_APPEND);
                    } else {
                        // INSERT foto utama baru
                        $entry = [
                            'tipe_foto' => $tipe,
                            'nama_file' => $publicBase . $newName,
                            'urutan' => $urutan,
                        ];
                        file_put_contents("debug_android.txt", "NEW UTAMA: $tipe (urutan=$urutan)\n", FILE_APPEND);
                    }

                    $fotoList[] = $entry;
                }
            }
        }

        // ✅ Process foto tambahan (6 FIXED SLOTS: slot 0-5 = urutan 5-10)
        for ($slot = 0; $slot < 6; $slot++) {
            $fieldName = "foto_tambahan_slot_$slot";
            $slotAction = $_POST["tambahan_slot_$slot"] ?? null; // "new" or "replace"
            
            if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
                continue; // Slot ini tidak ada upload
            }
            
            $f = $_FILES[$fieldName];
            $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
            $newName = uniqid('mobil_', true) . '.' . strtolower($ext);
            $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newName;
            
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $urutan = 5 + $slot; // Slot 0 = urutan 5, slot 1 = urutan 6, dst
                
                if ($slotAction === 'replace') {
                    // REPLACE foto tambahan yang sudah ada
                    $oldId = $_POST["tambahan_old_id_$slot"] ?? null;
                    
                    if ($oldId) {
                        // Ambil file lama untuk dihapus
                        $stmt = $conn->prepare("SELECT nama_file FROM mobil_foto WHERE id_foto = ?");
                        $stmt->bind_param("s", $oldId);
                        $stmt->execute();
                        $oldRow = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        
                        $entry = [
                            'tipe_foto' => 'tambahan',
                            'nama_file' => $publicBase . $newName,
                            'urutan' => $urutan,
                            'id_foto' => (int)$oldId,
                            'old_file' => $oldRow['nama_file'] ?? null
                        ];
                        file_put_contents("debug_android.txt", "REPLACE TAMBAHAN: slot=$slot, urutan=$urutan, id=$oldId\n", FILE_APPEND);
                    } else {
                        // Fallback: INSERT jika ID tidak ditemukan
                        $entry = [
                            'tipe_foto' => 'tambahan',
                            'nama_file' => $publicBase . $newName,
                            'urutan' => $urutan,
                        ];
                        file_put_contents("debug_android.txt", "NEW TAMBAHAN (fallback): slot=$slot, urutan=$urutan\n", FILE_APPEND);
                    }
                    
                } else {
                    // INSERT foto tambahan baru
                    $entry = [
                        'tipe_foto' => 'tambahan',
                        'nama_file' => $publicBase . $newName,
                        'urutan' => $urutan,
                    ];
                    file_put_contents("debug_android.txt", "NEW TAMBAHAN: slot=$slot, urutan=$urutan\n", FILE_APPEND);
                }
                
                $fotoList[] = $entry;
            }
        }

        file_put_contents("debug_android.txt", "Total photos to process: " . count($fotoList) . "\n=== END ===\n\n", FILE_APPEND);
    }

    // ===================== UPDATE MOBIL =====================
    if ($updateMode) {
        if (empty($kodeMobil))
            throw new Exception("Kode mobil wajib diisi untuk update.");

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT * FROM mobil WHERE kode_mobil = ?");
            $stmt->bind_param("s", $kodeMobil);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$old)
                throw new Exception("Mobil dengan kode $kodeMobil tidak ditemukan.");

            $fieldMap = [
                'nama_mobil' => 'nama_mobil',
                'tahun_mobil' => 'tahun',
                'jarak_tempuh' => 'jarak_tempuh',
                'full_prize' => 'full_prize',
                'uang_muka' => 'uang_muka',
                'tenor' => 'tenor',
                'angsuran' => 'angsuran',
                'jenis_kendaraan' => 'tipe_kendaraan',
                'sistem_penggerak' => 'sistem_penggerak',
                'tipe_bahan_bakar' => 'bahan_bakar',
                'warna_interior' => 'warna_interior',
                'warna_exterior' => 'warna_exterior',
                'status' => 'status',
            ];

            foreach ($data as $column => &$v) {
                $postKey = $fieldMap[$column] ?? $column;
                if (!isset($_POST[$postKey]) || $_POST[$postKey] === '') {
                    if (isset($old[$column])) {
                        $v = $old[$column];
                    }
                }
            }
            unset($v);

            $sql = "UPDATE mobil SET 
                        kode_user=?,
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

            $types = 's' . 's' . str_repeat('i', 6) . str_repeat('s', 7);
            $params = [
                $types,
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
                $data['status'],
                $kodeMobil
            ];
            call_user_func_array([$stmt, 'bind_param'], refValues($params));
            $stmt->execute();
            $stmt->close();

            // ===================== FITUR (replace) =====================
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

            // ===================== FOTO UPDATE =====================
            if (!empty($fotoList) && $isAndroidRequest) {
                $filesToDelete = [];

                foreach ($fotoList as $foto) {
                    $id = $foto['id_foto'] ?? null;
                    $tipe = $foto['tipe_foto'];
                    $file = $foto['nama_file'];
                    $urut = (int) $foto['urutan'];
                    $oldFile = $foto['old_file'] ?? null;

                    if ($id) {
                        // UPDATE: Replace foto lama
                        $stmt = $conn->prepare("UPDATE mobil_foto SET nama_file=?, urutan=? WHERE id_foto=?");
                        $stmt->bind_param("sii", $file, $urut, $id);
                        $stmt->execute();
                        $stmt->close();

                        if ($oldFile && $oldFile !== $file) {
                            $filesToDelete[] = $oldFile;
                        }
                    } else {
                        // INSERT: Foto baru
                        $stmt = $conn->prepare("INSERT INTO mobil_foto (kode_mobil, tipe_foto, nama_file, urutan) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("sssi", $kodeMobil, $tipe, $file, $urut);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Hapus file fisik
                $projectRoot = dirname(__DIR__);
                foreach ($filesToDelete as $path) {
                    $filePath = parse_url($path, PHP_URL_PATH);
                    if ($filePath === null || $filePath === false)
                        $filePath = $path;
                    if (strpos($filePath, '/images/mobil/') === 0) {
                        $full = $projectRoot . $filePath;
                        if (is_file($full)) {
                            @unlink($full);
                            file_put_contents("debug_android.txt", "Deleted: $full\n", FILE_APPEND);
                        }
                    }
                }
            }

            $conn->commit();

            echo json_encode([
                'success' => true,
                'code' => 200,
                'message' => 'Data mobil berhasil diperbarui.',
                'kode_mobil' => $kodeMobil,
                'kode_user' => $kodeUser,
                'foto_processed' => count($fotoList)
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
        $stmt = $conn->prepare("
            INSERT INTO mobil (
                kode_mobil, kode_user, nama_mobil, tahun_mobil, jarak_tempuh, full_prize, uang_muka, tenor, angsuran,
                jenis_kendaraan, sistem_penggerak, tipe_bahan_bakar, warna_interior, warna_exterior, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

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

        if (!empty($fitur)) {
            $stmt = $conn->prepare("INSERT INTO mobil_fitur (kode_mobil, id_fitur) VALUES (?, ?)");
            foreach ($fitur as $idFitur) {
                $stmt->bind_param("si", $kodeMobilBaru, $idFitur);
                $stmt->execute();
            }
            $stmt->close();
        }

        if (!empty($fotoList)) {
            $stmt = $conn->prepare("INSERT INTO mobil_foto (kode_mobil, tipe_foto, nama_file, urutan) VALUES (?, ?, ?, ?)");
            foreach ($fotoList as $foto) {
                $tipe = in_array($foto['tipe_foto'] ?? '', ['360', 'depan', 'belakang', 'samping', 'tambahan']) 
                    ? $foto['tipe_foto'] 
                    : 'tambahan';
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
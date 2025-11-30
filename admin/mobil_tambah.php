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

file_put_contents("debug_request.txt", print_r([
    'POST' => $_POST,
    'FILES' => $_FILES,
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD']
], true));

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
$kodeUser  = $_SESSION['kode_user'] 
          ?? ($_POST['kode_user'] ?? 'US001'); // fallback terakhir kalau bener2 belum login


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

    // ✅ DETEKSI SUMBER REQUEST: Android vs Web Admin
    $isAndroidRequest = !empty($_FILES) && (
        isset($_FILES['foto_360']) || 
        isset($_FILES['foto_depan']) || 
        isset($_FILES['foto_belakang']) || 
        isset($_FILES['foto_samping']) ||
        isset($_FILES['foto_tambahan'])
    );

    $uploadDir = API_UPLOAD_DIR;
    $publicBase = '/images/mobil/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    // =============== HANDLE UPLOAD FILE (ANDROID & WEB) ===============
    if ($isAndroidRequest) {
    file_put_contents("debug_android.txt", "=== ANDROID UPLOAD START ===\n", FILE_APPEND);

    // ✅ ANDROID: Process uploaded files langsung dari $_FILES
    $fotoList = [];
    
    // Mapping tipe foto
    $fotoMapping = [
        'foto_360' => '360',
        'foto_depan' => 'depan',
        'foto_belakang' => 'belakang',
        'foto_samping' => 'samping'
    ];

    // ✅ FIX: Ambil urutan maksimal dari database (jika update)
    $urut = 1;
    if ($updateMode && !empty($kodeMobil)) {
        $stmt = $conn->prepare("SELECT COALESCE(MAX(urutan), 0) AS max_urut FROM mobil_foto WHERE kode_mobil = ?");
        $stmt->bind_param("s", $kodeMobil);
        $stmt->execute();
        $rowMax = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $urut = (int)($rowMax['max_urut'] ?? 0) + 1; // Lanjut dari urutan terakhir
        file_put_contents("debug_android.txt", "Starting urutan from: $urut\n", FILE_APPEND);
    }

    // Process single files (360, depan, belakang, samping)
    foreach ($fotoMapping as $field => $tipe) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES[$field];
            $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
            $newName = uniqid('mobil_', true) . '.' . strtolower($ext);
            $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newName;

            if (move_uploaded_file($f['tmp_name'], $dest)) {
                // ✅ FIX: Cek apakah tipe foto ini sudah ada di DB
                $stmt = $conn->prepare("SELECT id_foto, urutan, nama_file FROM mobil_foto WHERE kode_mobil = ? AND tipe_foto = ? LIMIT 1");
                $stmt->bind_param("ss", $kodeMobil, $tipe);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($row) {
                    // ✅ REPLACE: Gunakan id_foto dan PERTAHANKAN urutan lama
                    $entry = [
                        'tipe_foto' => $tipe,
                        'nama_file' => $publicBase . $newName,
                        'urutan' => (int)$row['urutan'], // ❗ PENTING: Pakai urutan lama
                        'id_foto' => (int)$row['id_foto'],
                        'old_file' => $row['nama_file']
                    ];
                    
                    file_put_contents("debug_android.txt", "REPLACE: $field ($tipe) - id={$entry['id_foto']}, urutan={$entry['urutan']}\n", FILE_APPEND);
                } else {
                    // ✅ INSERT BARU: Gunakan urutan baru
                    $entry = [
                        'tipe_foto' => $tipe,
                        'nama_file' => $publicBase . $newName,
                        'urutan' => $urut++, // Increment untuk foto baru
                    ];
                    
                    file_put_contents("debug_android.txt", "NEW: $field ($tipe) - urutan={$entry['urutan']}\n", FILE_APPEND);
                }

                $fotoList[] = $entry;
            }
        }
    }

    // Process multiple files (foto_tambahan[])
    if (isset($_FILES['foto_tambahan']) && is_array($_FILES['foto_tambahan']['name'])) {
        $count = count($_FILES['foto_tambahan']['name']);
        
        // ✅ FIX: Hapus semua foto tambahan lama jika ada upload baru
        if ($updateMode && !empty($kodeMobil) && $count > 0) {
            $stmt = $conn->prepare("SELECT id_foto, nama_file FROM mobil_foto WHERE kode_mobil = ? AND tipe_foto = 'tambahan'");
            $stmt->bind_param("s", $kodeMobil);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $oldTambahanFiles = [];
            while ($row = $result->fetch_assoc()) {
                $oldTambahanFiles[] = $row['nama_file'];
            }
            $stmt->close();
            
            // Hapus record foto tambahan lama
            if (!empty($oldTambahanFiles)) {
                $stmt = $conn->prepare("DELETE FROM mobil_foto WHERE kode_mobil = ? AND tipe_foto = 'tambahan'");
                $stmt->bind_param("s", $kodeMobil);
                $stmt->execute();
                $stmt->close();
                
                // Hapus file fisik
                $projectRoot = dirname(__DIR__);
                foreach ($oldTambahanFiles as $path) {
                    $filePath = parse_url($path, PHP_URL_PATH);
                    if ($filePath === null || $filePath === false)
                        $filePath = $path;
                    if (strpos($filePath, '/images/mobil/') === 0) {
                        $full = $projectRoot . $filePath;
                        if (is_file($full)) {
                            @unlink($full);
                            file_put_contents("debug_android.txt", "Deleted old tambahan: $full\n", FILE_APPEND);
                        }
                    }
                }
            }
        }
        
        // Upload foto tambahan baru
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['foto_tambahan']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['foto_tambahan']['name'][$i], PATHINFO_EXTENSION);
                $newName = uniqid('mobil_', true) . '.' . strtolower($ext);
                $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newName;

                if (move_uploaded_file($_FILES['foto_tambahan']['tmp_name'][$i], $dest)) {
                    $fotoList[] = [
                        'tipe_foto' => 'tambahan',
                        'nama_file' => $publicBase . $newName,
                        'urutan' => $urut++, // Lanjut increment
                    ];
                    file_put_contents("debug_android.txt", "NEW TAMBAHAN: urutan={$urut}\n", FILE_APPEND);
                }
            }
        }
    }

    file_put_contents("debug_android.txt", "Total processed: " . count($fotoList) . "\n=== ANDROID UPLOAD END ===\n\n", FILE_APPEND);
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

            // Mapping field
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

            // Gunakan nilai baru jika dikirim, kalau tidak, pakai nilai lama
            foreach ($data as $column => &$v) {
                $postKey = $fieldMap[$column] ?? $column;
                if (!isset($_POST[$postKey]) || $_POST[$postKey] === '') {
                    if (isset($old[$column])) {
                        $v = $old[$column];
                    }
                }
            }
            unset($v);

            // Update data mobil
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

            // ===================== FOTO UPDATE (ANDROID) =====================
            if (!empty($fotoList) && $isAndroidRequest) {
                $filesToDelete = [];

                foreach ($fotoList as $foto) {
                    $id = $foto['id_foto'] ?? null;
                    $tipe = $foto['tipe_foto'];
                    $file = $foto['nama_file'];
                    $urut = (int) $foto['urutan'];
                    $oldFile = $foto['old_file'] ?? null;

                    if ($id) {
                        // ✅ UPDATE: Replace foto lama
                        $stmt = $conn->prepare("UPDATE mobil_foto SET nama_file=?, urutan=? WHERE id_foto=?");
                        $stmt->bind_param("sii", $file, $urut, $id);
                        $stmt->execute();
                        $stmt->close();

                        // Tandai file lama untuk dihapus
                        if ($oldFile && $oldFile !== $file) {
                            $filesToDelete[] = $oldFile;
                        }
                    } else {
                        // ✅ INSERT: Foto baru (untuk foto tambahan)
                        $stmt = $conn->prepare("INSERT INTO mobil_foto (kode_mobil, tipe_foto, nama_file, urutan) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("sssi", $kodeMobil, $tipe, $file, $urut);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Hapus file fisik setelah DB commit
                $projectRoot = dirname(__DIR__);
                foreach ($filesToDelete as $path) {
                    $filePath = parse_url($path, PHP_URL_PATH);
                    if ($filePath === null || $filePath === false)
                        $filePath = $path;
                    if (strpos($filePath, '/images/mobil/') === 0) {
                        $full = $projectRoot . $filePath;
                        if (is_file($full)) {
                            @unlink($full);
                            file_put_contents("debug_android.txt", "Deleted old file: $full\n", FILE_APPEND);
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
        // Insert mobil utama
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
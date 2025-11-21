<?php
require __DIR__ . "/../shared/config.php";
require_once __DIR__ . "/../shared/path.php";
header("Content-Type: application/json");

$response = [
    "code" => 400,
    "success" => false,
    "message" => "Terjadi kesalahan",
    "mobil" => null,
    "fitur" => [],
    "foto" => []
];

// Ambil kode_mobil
$kode = $_GET["kode_mobil"] ?? "";

if (!$kode) {
    echo json_encode([
        "success" => false,
        "message" => "kode_mobil wajib dikirim"
    ]);
    exit;
}

try {
    // ============================
    // 1. AMBIL DATA MOBIL
    // ============================
    $stmt = $conn->prepare("
               SELECT 
            kode_mobil,
            nama_mobil,
            tahun_mobil,
            jarak_tempuh,
            full_prize,
            uang_muka,
            tenor,
            angsuran,
            jenis_kendaraan,
            sistem_penggerak,
            tipe_bahan_bakar,
            warna_interior,
            warna_exterior,
            status
        FROM mobil
        WHERE kode_mobil = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $kode);
    $stmt->execute();
    $mobil = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$mobil) {
        echo json_encode([
            "success" => false,
            "message" => "Mobil tidak ditemukan"
        ]);
        exit;
    }

    // ============================
    // 2. AMBIL FITUR
    // ============================
    $fitur = [];
    $resFitur = $conn->query("SELECT id_fitur FROM mobil_fitur WHERE kode_mobil = '$kode'");
    while ($row = $resFitur->fetch_assoc()) {
        $fitur[] = (int) $row['id_fitur'];
    }

    // ============================
    // 3. AMBIL FOTO
    // ============================
    $foto = [];
    $resFoto = $conn->query("
        SELECT id_foto, tipe_foto, nama_file, urutan
        FROM mobil_foto
        WHERE kode_mobil = '$kode'
        ORDER BY urutan ASC
    ");

    while ($row = $resFoto->fetch_assoc()) {

        // Convert nama_file → full URL
        if (!empty($row['nama_file'])) {
            // jika masih /images/mobil/xxx.jpg → jadikan URL lengkap
            if (
                strpos($row['nama_file'], "http://") === false &&
                strpos($row['nama_file'], "https://") === false
            ) {

                $path = $row['nama_file'];

                if ($path[0] !== '/')
                    $path = '/' . $path;

                $row['nama_file'] = rtrim(BASE_URL, '/') . $path;
            }
        }

        $foto[] = $row;
    }

    // ============================
    // 4. RETURN JSON
    // ============================
    $response["code"] = 200;
    $response["success"] = true;
    $response["message"] = "OK";
    $response["mobil"] = $mobil;
    $response["fitur"] = $fitur;
    $response["foto"] = $foto;

} catch (Exception $e) {
    $response["code"] = 400;
    $response["message"] = $e->getMessage();
}

echo json_encode($response);

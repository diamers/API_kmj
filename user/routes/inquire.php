<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../repos/inquire_repo.php';


// Parse JSON jika RAW JSON
$raw = file_get_contents("php://input");
if (!empty($raw)) {
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $_POST = $json;
    }
}


function send_response(int $code, string $message = '', $data = null)
{
    $res = [
        'code'    => $code,
        'message' => $message,
    ];

    if ($data !== null) {
        $res['data'] = $data;
    }

    echo json_encode($res);
    exit;
}

// Hanya izinkan POST (user create janji temu)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(400, 'Method not allowed (POST only)');
}

// ==== ambil kode_user ====
// // produksi: ambil dari session login
// if (isset($_SESSION['kode_user'])) {
//     $kode_user = $_SESSION['kode_user'];
// } elseif (isset($_POST['kode_user'])) {
//     // untuk testing manual (Postman), boleh kirim kode_user di body
//     $kode_user = $_POST['kode_user'];
// } else {
//     send_response(400, 'kode_user tidak ditemukan (user belum login)');
// }
$kode_user = 'US002';

// ==== ambil data dari form user ====
$kode_mobil  = $_POST['kode_mobil']  ?? null;
$uji_beli    = isset($_POST['uji_beli']) ? (int)$_POST['uji_beli'] : 0;
$jenis_janji = isset($_POST['jenis_janji']) ? (int)$_POST['jenis_janji'] : 0;
$tanggal     = $_POST['tanggal']     ?? '';
$waktu       = $_POST['waktu']       ?? '';
$no_telp     = $_POST['no_telp']     ?? '';

// validasi minimal
if (!$kode_mobil || !$tanggal || !$waktu || !$no_telp) {
    send_response(400, 'Debug', [
        'kode_mobil' => $kode_mobil,
        'tanggal'    => $tanggal,
        'waktu'      => $waktu,
        'no_telp'    => $no_telp,
        'raw_post'   => $_POST
    ]);
}

try {
    $id = create_inquire($conn, [
        'kode_user'   => $kode_user,
        'kode_mobil'  => $kode_mobil,
        'uji_beli'    => $uji_beli,
        'jenis_janji' => $jenis_janji,
        'tanggal'     => $tanggal,
        'waktu'       => $waktu,
        'no_telp'     => $no_telp,
    ]);

    send_response(200, 'Inquire berhasil dibuat', [
        'id_inquire' => $id
    ]);
} catch (Exception $e) {
    send_response(400, 'Terjadi kesalahan: ' . $e->getMessage());
}

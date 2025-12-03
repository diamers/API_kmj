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
    http_response_code($code);

    $res = [
        'code' => $code,
        'message' => $message,
    ];

    if ($data !== null) {
        $res['data'] = $data;
    }

    echo json_encode($res);
    exit;
}

// Hanya izinkan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, 'Method not allowed (POST only)');
}

// Ambil kode_user dari SESSION
if (!isset($_SESSION['kode_user']) || empty($_SESSION['kode_user'])) {
    // user belum login
    send_response(401, 'User belum login');
}

$kode_user = $_SESSION['kode_user'];

// ==== ambil data dari form user ====
$kode_mobil = $_POST['kode_mobil'] ?? null;
$uji_beli = isset($_POST['uji_beli']) ? (int) $_POST['uji_beli'] : 0;
$jenis_janji = isset($_POST['jenis_janji']) ? (int) $_POST['jenis_janji'] : 0;
$tanggal = $_POST['tanggal'] ?? '';
$waktu = $_POST['waktu'] ?? '';
$no_telp = $_POST['no_telp'] ?? '';
$note = $_POST['note'] ?? null;

// Validasi minimal
if (!$kode_mobil || !$tanggal || !$waktu || !$no_telp) {
    send_response(400, 'Data janji temu belum lengkap', [
        'kode_mobil' => $kode_mobil,
        'tanggal' => $tanggal,
        'waktu' => $waktu,
        'no_telp' => $no_telp,
    ]);
}

try {
    $id = create_inquire($conn, [
        'kode_user' => $kode_user,
        'kode_mobil' => $kode_mobil,
        'uji_beli' => $uji_beli,
        'jenis_janji' => $jenis_janji,
        'tanggal' => $tanggal,
        'waktu' => $waktu,
        'no_telp' => $no_telp,
        'note' => $note,
    ]);

    // ambil nomor WA default
    $whatsapp = get_default_whatsapp($conn);

    send_response(200, 'Janji temu berhasil dibuat', [
        'id_inquire' => $id,
        'whatsapp' => $whatsapp,
    ]);

} catch (Exception $e) {
    send_response(500, 'Terjadi kesalahan: ' . $e->getMessage());
}

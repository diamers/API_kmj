<?php
header('Content-Type: application/json');

// SESUAIKAN PATH INI DENGAN STRUKTUR PROJECTMU
require_once '../db/config_api.php';

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

// Hanya izinkan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(400, 'Method not allowed (POST only)');
}

// Tentukan jenis aksi
$action = $_GET['action'] ?? 'create';

if ($action === 'create') {
    create_inquire($conn);
} elseif ($action === 'update_status') {
    update_inquire_status($conn);
} else {
    send_response(400, 'Unknown action');
}

/**
 * USER CREATE INQUIRE
 * -------------------
 * POST /API_kmj/inquire_post.php?action=create
 *
 * body:
 *  - kode_user
 *  - kode_mobil
 *  - uji_beli    (0/1)
 *  - jenis_janji (0/1)
 *  - tanggal     (Y-m-d)
 *  - waktu       (H:i)
 *  - no_telp
 */
function create_inquire(mysqli $conn)
{
    $kode_user   = $_POST['kode_user']   ?? null;
    $kode_mobil  = $_POST['kode_mobil']  ?? null;
    $uji_beli    = isset($_POST['uji_beli']) ? (int)$_POST['uji_beli'] : 0;
    $jenis_janji = isset($_POST['jenis_janji']) ? (int)$_POST['jenis_janji'] : 0;
    $tanggal     = $_POST['tanggal']     ?? '';
    $waktu       = $_POST['waktu']       ?? '';
    $no_telp     = $_POST['no_telp']     ?? '';

    if (!$kode_user || !$kode_mobil || !$tanggal || !$waktu || !$no_telp) {
        send_response(400, 'Data belum lengkap');
    }

    try {
        $sql = "INSERT INTO inquire 
                (kode_user, kode_mobil, uji_beli, jenis_janji, tanggal, waktu, no_telp, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssiisss',
            $kode_user,
            $kode_mobil,
            $uji_beli,
            $jenis_janji,
            $tanggal,
            $waktu,
            $no_telp
        );
        $stmt->execute();

        $id = $conn->insert_id;

        send_response(200, 'Inquire berhasil dibuat', [
            'id_inquire' => $id
        ]);

    } catch (Exception $e) {
        send_response(400, 'Terjadi kesalahan: ' . $e->getMessage());
    }
}

/**
 * ADMIN UPDATE STATUS
 * -------------------
 * POST /API_kmj/inquire_post.php?action=update_status
 *
 * body:
 *  - id_inquire (int)
 *  - status     : pending | responded | closed
 *
 * note:
 *  status "closed" akan disimpan sebagai "canceled" di DB
 */
function update_inquire_status(mysqli $conn)
{
    $id_inquire = isset($_POST['id_inquire']) ? (int)$_POST['id_inquire'] : 0;
    $status     = $_POST['status'] ?? '';

    if (!$id_inquire || !$status) {
        send_response(400, 'Data tidak valid');
    }

    // mapping UI -> DB
    if ($status === 'closed') {
        $status = 'canceled';
    }

    if (!in_array($status, ['pending', 'responded', 'canceled'])) {
        send_response(400, 'Status tidak dikenali');
    }

    try {
        $sql  = "UPDATE inquire SET status = ? WHERE id_inquire = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $status, $id_inquire);
        $stmt->execute();

        if ($stmt->affected_rows <= 0) {
            send_response(400, 'Data tidak ditemukan atau status tidak berubah');
        }

        send_response(200, 'Status berhasil diubah');

    } catch (Exception $e) {
        send_response(400, 'Terjadi kesalahan: ' . $e->getMessage());
    }
}

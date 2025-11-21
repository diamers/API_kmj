<?php
header('Content-Type: application/json');
require '../shared/config.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    // ========= INQUIRE =========
    case 'inquire_list':
        inquire_list($conn);
        break;

    case 'inquire_create':
        inquire_create($conn);
        break;

    case 'inquire_update_status':
        inquire_update_status($conn);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Unknown action'
        ]);
        break;
}

/**
 * GET /API_kmj.php?action=inquire_list&status=pending
 * status (optional): pending | responded | closed
 * - kalau tidak diisi -> tampilkan semua
 */
function inquire_list($conn)
{
    $status = isset($_GET['status']) ? $_GET['status'] : '';

    // mapping "closed" (di UI) ke "canceled" (di DB)
    if ($status === 'closed') {
        $status = 'canceled';
    }

    try {
        if ($status !== '' && in_array($status, ['pending', 'responded', 'canceled'])) {
            $sql = "
                SELECT i.*, 
                       u.nama_lengkap AS nama_user,
                       u.email,
                       m.nama_mobil,
                       m.merk,
                       m.tahun
                FROM inquire i
                LEFT JOIN users u ON i.kode_user = u.kode_user
                LEFT JOIN mobil m ON i.kode_mobil = m.kode_mobil
                WHERE i.status = ?
                ORDER BY i.tanggal DESC, i.waktu DESC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $status);
        } else {
            $sql = "
                SELECT i.*, 
                       u.nama_lengkap AS nama_user,
                       u.email,
                       m.nama_mobil,
                       m.merk,
                       m.tahun
                FROM inquire i
                LEFT JOIN users u ON i.kode_user = u.kode_user
                LEFT JOIN mobil m ON i.kode_mobil = m.kode_mobil
                ORDER BY i.tanggal DESC, i.waktu DESC
            ";
            $stmt = $conn->prepare($sql);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {

            // ubah "canceled" jadi "closed" untuk dikirim ke FE admin
            if ($row['status'] === 'canceled') {
                $row['status'] = 'closed';
            }

            $rows[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data'    => $rows
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * POST /API_kmj.php?action=inquire_create
 * INPUT dari pengguna (frontend user):
 * - kode_user
 * - kode_mobil
 * - uji_beli       (0/1)
 * - jenis_janji    (0/1 atau sesuai kebutuhanmu)
 * - tanggal        (Y-m-d)
 * - waktu          (H:i)
 * - no_telp
 * status akan otomatis: pending
 */
function inquire_create($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $kode_user   = $_POST['kode_user']   ?? null;
    $kode_mobil  = $_POST['kode_mobil']  ?? null;
    $uji_beli    = isset($_POST['uji_beli']) ? (int)$_POST['uji_beli'] : 0;
    $jenis_janji = isset($_POST['jenis_janji']) ? (int)$_POST['jenis_janji'] : 0;
    $tanggal     = $_POST['tanggal']     ?? '';
    $waktu       = $_POST['waktu']       ?? '';
    $no_telp     = $_POST['no_telp']     ?? '';

    // validasi sederhana
    if (!$kode_user || !$kode_mobil || !$tanggal || !$waktu || !$no_telp) {
        echo json_encode([
            'success' => false,
            'message' => 'Data belum lengkap'
        ]);
        return;
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

        echo json_encode([
            'success'     => true,
            'message'     => 'Inquire berhasil dibuat',
            'id_inquire'  => $conn->insert_id
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * POST /API_kmj.php?action=inquire_update_status
 * Input:
 * - id_inquire
 * - status: pending | responded | closed
 *
 * status "closed" akan disimpan sebagai "canceled" di DB
 */
function inquire_update_status($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $id_inquire = isset($_POST['id_inquire']) ? (int)$_POST['id_inquire'] : 0;
    $status     = $_POST['status'] ?? '';

    if (!$id_inquire || !$status) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
        return;
    }

    // mapping status dari FE ke DB
    if ($status === 'closed') {
        $status = 'canceled'; // di DB enum-nya "canceled"
    }

    if (!in_array($status, ['pending', 'responded', 'canceled'])) {
        echo json_encode(['success' => false, 'message' => 'Status tidak dikenali']);
        return;
    }

    try {
        $sql = "UPDATE inquire SET status = ? WHERE id_inquire = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $status, $id_inquire);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

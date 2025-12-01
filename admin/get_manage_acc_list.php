<?php
require __DIR__ . "/../shared/config.php";
header('Content-Type: application/json');

// Optional: filter role via query string, default: admin + owner
$allowedRoles = ['admin', 'owner'];

$roleFilter = null;
if (isset($_GET['role'])) {
    $r = strtolower(trim($_GET['role']));
    if (in_array($r, $allowedRoles, true)) {
        $roleFilter = $r;
    }
}

try {
    // Build SQL
    if ($roleFilter) {
        $sql = "SELECT kode_user, full_name, email, role, avatar_url, status, last_login, updated_at, created_at
                FROM users
                WHERE role = ?
                ORDER BY (role = 'owner') DESC, created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $roleFilter);
    } else {
        $sql = "SELECT kode_user, full_name, email, role, avatar_url, status, last_login, updated_at, created_at
                FROM users
                WHERE role IN ('admin','owner')
                ORDER BY (role = 'owner') DESC, created_at DESC";
        $stmt = $conn->prepare($sql);
    }

    if (!$stmt) {
        throw new Exception("Gagal menyiapkan query: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        // pastikan tipe data konsisten
        $row['status'] = isset($row['status']) ? (int)$row['status'] : 0;
        $rows[] = $row;
    }

    $stmt->close();

    http_response_code(200);
    echo json_encode([
        'kode'    => 200,
        'success' => true,
        'message' => 'Data akun ditemukan',
        'data'    => $rows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'kode'    => 500,
        'success' => false,
        'message' => 'Terjadi kesalahan pada server',
        'error'   => $e->getMessage()
    ]);
}

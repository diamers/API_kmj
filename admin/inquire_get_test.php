<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../shared/config.php';

// helper untuk error
function respond($code, $message = '', $data = null) {
    $res = ['code' => $code];
    if ($message !== '') {
        $res['message'] = $message;
    }
    if ($data !== null) {
        $res['data'] = $data;
    }

    echo json_encode($res);
    exit;
}

// Hanya GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(400, "Method not allowed (GET only)");
}

$status = $_GET['status'] ?? '';

// UI → DB mapping
if ($status === 'closed') {
    $status = 'canceled';
}

$sql = "
    SELECT
        i.id_inquire,
        i.kode_user,
        i.kode_mobil,
        i.uji_beli,
        i.jenis_janji,
        i.tanggal,
        i.waktu,
        i.no_telp,
        i.note,
        i.status,
        u.email AS email_user
    FROM inquire i
    LEFT JOIN users u ON u.kode_user = i.kode_user
    WHERE 1
";

$params = [];
$types  = '';

if ($status && in_array($status, ['pending','responded','canceled'], true)) {
    $sql    .= " AND i.status = ?";
    $types  .= 's';
    $params[] = $status;
}

$sql .= " ORDER BY i.id_inquire DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    respond(400, "Query prepare error: " . $conn->error);
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    // DB -> UI mapping
    if ($row['status'] === 'canceled') {
        $row['status'] = 'closed';
    }
    $data[] = $row;
}

// ✅ SUCCESS RESPONSE (tanpa message OK)
echo json_encode([
    'code' => 200,
    'data' => $data
]);
exit;

<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../shared/config.php';

function respond($code, $message, $data = null) {
    $res = [
        'code' => $code,
        'message' => $message,
    ];
    if ($data !== null) $res['data'] = $data;

    echo json_encode($res);
    exit;
}

// Only GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(400, "Method not allowed (GET only)");
}

$status = $_GET['status'] ?? '';

if ($status === 'closed') {
    $status = 'canceled';
}

$sql = "
    SELECT
        id_inquire,
        kode_user,
        kode_mobil,
        uji_beli,
        jenis_janji,
        tanggal,
        waktu,
        no_telp,
        status
    FROM inquire
    WHERE 1
";

$params = [];
$types  = '';

if ($status && in_array($status, ['pending','responded','canceled'], true)) {
    $sql    .= " AND status = ?";
    $types  .= 's';
    $params[] = $status;
}

$sql .= " ORDER BY id_inquire DESC";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'canceled') {
        $row['status'] = 'closed';
    }
    $data[] = $row;
}

echo json_encode([
    'code' => 200,
    'message' => 'OK',
    'data' => $data
]);
exit;

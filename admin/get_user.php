<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";

$kode_user = $_GET['kode_user'] ?? null;
if (!$kode_user) {
    http_response_code(400);
    echo json_encode(["code"=>400,"message"=>"kode_user wajib"]);
    exit;
}

$stmt = $conn->prepare("SELECT kode_user, username, email, full_name, no_telp, role, avatar_url, provider_type, alamat, status FROM users WHERE kode_user = ?");
$stmt->bind_param("s", $kode_user);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["code"=>404,"message"=>"User tidak ditemukan"]);
    exit;
}

$user = $res->fetch_assoc();
echo json_encode(["code"=>200,"data"=>$user]);
?>

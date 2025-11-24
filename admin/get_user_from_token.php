<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";
require __DIR__ . "/../shared/jwt_helper.php";

$headers = apache_request_headers();

$authHeader = $headers["Authorization"] ?? $headers["authorization"] ?? null;

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode([
        "code" => 401,
        "message" => "Token tidak ditemukan"
    ]);
    exit;
}

$token = $matches[1];
$payload = decode_jwt($token);

if (!$payload) {
    echo json_encode([
        "code" => 403,
        "message" => "Token tidak valid"
    ]);
    exit;
}

$kode_user = $payload['kode_user'];

$stmt = $conn->prepare("SELECT * FROM users WHERE kode_user=? LIMIT 1");
$stmt->bind_param("s", $kode_user);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode([
        "code" => 404,
        "message" => "User tidak ditemukan"
    ]);
    exit;
}

echo json_encode([
    "code" => 200,
    "message" => "Token valid",
    "user" => [
        "kode_user" => $user["kode_user"],
        "username" => $user["username"],
        "full_name" => $user["full_name"],
        "email" => $user["email"],
        "no_telp" => $user["no_telp"],
        "role" => $user["role"],
        "avatar_url" => $user["avatar_url"],
        "provider_type" => $user["provider_type"],
        "status" => (int)$user["status"]
    ]
]);
?>

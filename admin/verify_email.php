<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";

$data = json_decode(file_get_contents("php://input"), true);

$kode_user = $data["kode_user"] ?? null;
$kode_verifikasi = $data["kode_verifikasi"] ?? null;

if (!$kode_user || !$kode_verifikasi) {
    http_response_code(400);
    echo json_encode(["code" => 400, "message" => "Kode user dan kode verifikasi wajib diisi"]);
    exit;
}

// Cek apakah user ada
$checkUser = $conn->prepare("SELECT email_verified FROM users WHERE kode_user = ?");
$checkUser->bind_param("s", $kode_user);
$checkUser->execute();
$userResult = $checkUser->get_result();

if ($userResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["code" => 404, "message" => "User tidak ditemukan"]);
    exit;
}

$user = $userResult->fetch_assoc();

// Jika sudah diverifikasi
if ($user["email_verified"] == 1) {
    http_response_code(409);
    echo json_encode(["code" => 409, "message" => "Email sudah terverifikasi"]);
    exit;
}

// Ambil data verifikasi
$checkCode = $conn->prepare("
    SELECT * FROM email_verifications 
    WHERE kode_user = ? AND kode_verifikasi = ? 
    ORDER BY created_at DESC LIMIT 1
");
$checkCode->bind_param("ss", $kode_user, $kode_verifikasi);
$checkCode->execute();
$verifyResult = $checkCode->get_result();

if ($verifyResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["code" => 404, "message" => "Kode verifikasi tidak valid"]);
    exit;
}

$verification = $verifyResult->fetch_assoc();

// (Opsional) Cek kadaluwarsa kode, misal 10 menit
$expired = false;
$created_time = strtotime($verification["created_at"]);
if (time() - $created_time > 600) { // 600 detik = 10 menit
    $expired = true;
}

if ($expired) {
    http_response_code(410);
    echo json_encode(["code" => 410, "message" => "Kode verifikasi sudah kedaluwarsa"]);
    exit;
}

// Update status user
$update = $conn->prepare("UPDATE users SET email_verified = 1, updated_at = NOW() WHERE kode_user = ?");
$update->bind_param("s", $kode_user);
$update->execute();

// Hapus kode verifikasi agar tidak bisa digunakan lagi
$delete = $conn->prepare("DELETE FROM email_verifications WHERE kode_user = ?");
$delete->bind_param("s", $kode_user);
$delete->execute();

http_response_code(200);
echo json_encode(["code" => 200, "message" => "Email berhasil diverifikasi"]);
?>

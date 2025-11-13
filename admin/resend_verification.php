<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";
require __DIR__ . "/../shared/send_mail.php";

$data = json_decode(file_get_contents("php://input"), true);

$kode_user = $data["kode_user"] ?? null;

if (!$kode_user) {
    http_response_code(400);
    echo json_encode(["code" => 400, "message" => "Kode user wajib diisi"]);
    exit;
}

// Ambil data user
$userQuery = $conn->prepare("SELECT email, full_name, email_verified, role FROM users WHERE kode_user = ?");
$userQuery->bind_param("s", $kode_user);
$userQuery->execute();
$result = $userQuery->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["code" => 404, "message" => "User tidak ditemukan"]);
    exit;
}

$user = $result->fetch_assoc();

// Jika user bukan customer, tidak perlu verifikasi
if ($user["role"] !== "customer") {
    http_response_code(403);
    echo json_encode(["code" => 403, "message" => "Role ini tidak memerlukan verifikasi email"]);
    exit;
}

// Jika sudah diverifikasi
if ($user["email_verified"] == 1) {
    http_response_code(409);
    echo json_encode(["code" => 409, "message" => "Email sudah terverifikasi"]);
    exit;
}

$email = $user["email"];
$full_name = $user["full_name"];

// Hapus kode lama (jika ada)
$deleteOld = $conn->prepare("DELETE FROM email_verifications WHERE kode_user = ?");
$deleteOld->bind_param("s", $kode_user);
$deleteOld->execute();

// Generate kode baru
$verification_code = rand(100000, 999999);

// Simpan kode baru
$saveCode = $conn->prepare("
    INSERT INTO email_verifications (kode_user, email, kode_verifikasi, created_at)
    VALUES (?, ?, ?, NOW())
");
$saveCode->bind_param("sss", $kode_user, $email, $verification_code);
$saveCode->execute();

// Kirim ulang email
$subject = "Kode Verifikasi Baru Akun Anda";
$body = "Halo $full_name,\n\nKode verifikasi baru Anda adalah: $verification_code\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Berlaku selama 10 menit.";
$send = sendVerificationEmail($email, $full_name, $verification_code);

if ($send) {
    http_response_code(200);
    echo json_encode(["code" => 200, "message" => "Kode verifikasi baru telah dikirim ke email Anda"]);
} else {
    http_response_code(500);
    echo json_encode(["code" => 500, "message" => "Gagal mengirim email verifikasi"]);
}
?>

<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";
require __DIR__ . "/../shared/send_mail.php";

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST;
}

$role = $data["role"] ?? "";
$username = $data["username"] ?? null;
$email = $data["email"] ?? null;
$password = $data["password"] ?? null;
$full_name = $data["full_name"] ?? null;
$provider_type = $data["provider_type"] ?? "local";
$provider_id = $data["provider_id"] ?? null;
$no_telp = $data["no_telp"] ?? null;
$alamat = $data["alamat"] ?? null;

if (!$role) {
    http_response_code(400);
    echo json_encode(["code" => 400, "message" => "Role wajib diisi"]);
    exit;
}

if ($role == "owner") {
    if (!$username || !$email || !$password || !$full_name) {
        http_response_code(400);
        echo json_encode(["code" => 400, "message" => "Nama lengkap, username, email, dan password wajib diisi untuk owner"]);
        exit;
    }
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $no_telp = null;
    $alamat = null;

} elseif ($role == "admin") {
    if (!$username || !$email || !$password || !$full_name || !$no_telp || !$alamat) {
        http_response_code(400);
        echo json_encode(["code" => 400, "message" => "Nama lengkap, username, email, password, no. telepon, dan alamat wajib diisi untuk admin"]);
        exit;
    }
    $hashed = password_hash($password, PASSWORD_BCRYPT);

} elseif ($role == "customer") {
    if ($provider_type == "local") {
        if (!$username || !$email || !$password || !$full_name || !$no_telp) {
            http_response_code(400);
            echo json_encode(["code" => 400, "message" => "Nama lengkap, username, email, password, dan nomor telepon wajib diisi untuk customer lokal"]);
            exit;
        }
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $email_verified = 0;
        $verification_code = rand(100000, 999999);
    } else {
        if (!$provider_id || !$email) {
            http_response_code(400);
            echo json_encode(["code" => 400, "message" => "Email dan Provider ID wajib untuk login sosial"]);
            exit;
        }
        if (!$username) {
            $username = strtolower($provider_type) . "_" . substr($provider_id, 0, 8);
        }

        $hashed = null;
        $email_verified = 1; 
        $verification_code = null;
    }
} else {
    http_response_code(400);
    echo json_encode(["code" => 400, "message" => "Role tidak valid"]);
    exit;
}

$check = $conn->prepare("SELECT * FROM users WHERE email=? OR username=?");
$check->bind_param("ss", $email, $username);
$check->execute();
$result = $check->get_result();
if ($result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["code" => 409, "message" => "Email atau username sudah digunakan"]);
    exit;
}

$kodeQuery = $conn->query("SELECT generate_kode_users() AS kode_user");
if ($kodeQuery && $row = $kodeQuery->fetch_assoc()) {
    $kode_user = $row['kode_user'];
} else {
    http_response_code(500);
    echo json_encode(["code" => 500, "message" => "Gagal membuat kode user"]);
    exit;
}

$email_verified = 0;
$verification_code = null;

if ($role == "customer") {
    if ($provider_type == "local") {
        $verification_code = rand(100000, 999999);
        $email_verified = 0;
    } else {
        $email_verified = 1;
    }
} else {
    $email_verified = 1;
}


$stmt = $conn->prepare("
    INSERT INTO users 
    (kode_user, username, email, password, full_name, role, provider_type, provider_id, no_telp, alamat, email_verified, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param(
    "ssssssssssi",
    $kode_user,
    $username,
    $email,
    $hashed,
    $full_name,
    $role,
    $provider_type,
    $provider_id,
    $no_telp,
    $alamat,
    $email_verified
);

if ($stmt->execute()) {
    if ($role == "customer" && $verification_code) {
        $saveCode = $conn->prepare("INSERT INTO email_verifications (kode_user, email, kode_verifikasi, created_at) VALUES (?, ?, ?, NOW())");
        $saveCode->bind_param("sss", $kode_user, $email, $verification_code);
        $saveCode->execute();

        $subject = "Kode Verifikasi Akun Anda";
        $body = "Halo $full_name,\n\nKode verifikasi Anda adalah: $verification_code\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda.";
        sendVerificationEmail($email, $full_name, $verification_code);
    }

    http_response_code(200);
    echo json_encode([
        "code" => 200,
        "message" => "Registrasi berhasil",
        "role" => $role,
        "kode_user" => $kode_user,
        "email_verified" => $email_verified
    ]);
} else {
    http_response_code(500);
    echo json_encode(["code" => 500, "message" => "Gagal registrasi: " . $conn->error]);
}
?>
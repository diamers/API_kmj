<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";

// Ambil data JSON dari body
$data = json_decode(file_get_contents("php://input"), true);

$role = $data["role"] ?? "";
$username = $data["username"] ?? null;
$email = $data["email"] ?? null;
$password = $data["password"] ?? null;
$full_name = $data["full_name"] ?? null;
$provider_type = $data["provider_type"] ?? "local";
$provider_id = $data["provider_id"] ?? null;

// Validasi role
if (!$role) {
    echo json_encode(["status" => "error", "message" => "Role wajib diisi"]);
    exit;
}

if (in_array($role, ["owner", "admin"])) {
    if (!$username || !$email || !$password || !$full_name) {
        echo json_encode(["status" => "error", "message" => "Nama lengkap, username, email, dan password wajib diisi"]);
        exit;
    }
    $hashed = password_hash($password, PASSWORD_BCRYPT);
} elseif ($role == "customer") {
    if ($provider_type != "local" && !$provider_id) {
        echo json_encode(["status" => "error", "message" => "Provider ID wajib diisi untuk login sosial"]);
        exit;
    }
    $hashed = $password ? password_hash($password, PASSWORD_BCRYPT) : null;
} else {
    echo json_encode(["status" => "error", "message" => "Role tidak valid"]);
    exit;
}

// Cek apakah email atau username sudah ada
$check = $conn->prepare("SELECT * FROM users WHERE email=? OR username=?");
$check->bind_param("ss", $email, $username);
$check->execute();
$result = $check->get_result();
if ($result->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email atau username sudah digunakan"]);
    exit;
}

// Ambil kode_user dari fungsi database
$kodeQuery = $conn->query("SELECT generate_kode_users() AS kode_user");
if ($kodeQuery && $row = $kodeQuery->fetch_assoc()) {
    $kode_user = $row['kode_user'];
} else {
    echo json_encode(["status" => "error", "message" => "Gagal membuat kode user"]);
    exit;
}

// Insert data baru
$stmt = $conn->prepare("
    INSERT INTO users 
    (kode_user, username, email, password, full_name, role, provider_type, provider_id, email_verified)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
");
$stmt->bind_param("ssssssss", $kode_user, $username, $email, $hashed, $full_name, $role, $provider_type, $provider_id);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Registrasi berhasil",
        "kode_user" => $kode_user
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Gagal registrasi: " . $conn->error]);
}
?>

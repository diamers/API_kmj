<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require __DIR__ . "/../shared/config.php";

// Ambil data dari FormData atau JSON
$data = $_POST;
if (!$data) {
    $json = json_decode(file_get_contents("php://input"), true);
    if (is_array($json)) {
        $data = $json;
    }
}

$full_name = trim($data["full_name"] ?? "");
$username  = trim($data["username"] ?? "");
$email     = trim($data["email"] ?? "");
$password  = $data["password"] ?? "";
$no_telp   = trim($data["no_telp"] ?? "");
$alamat    = trim($data["alamat"] ?? "");

// Khusus manajemen akun → nambah admin
$role          = "admin";
$provider_type = "local";
$provider_id   = null;
$email_verified = 1;
$status         = 1;

/* =============== VALIDASI SEDERHANA =============== */
if (!$full_name || !$username || !$email || !$password || !$no_telp || !$alamat) {
    http_response_code(400);
    echo json_encode([
        "kode" => 400,
        "success" => false,
        "message" => "Semua field wajib diisi"
    ]);
    exit;
}

/* =============== CEK EMAIL / USERNAME =============== */
$check = $conn->prepare("SELECT 1 FROM users WHERE email = ? OR username = ?");
$check->bind_param("ss", $email, $username);
$check->execute();
$resCheck = $check->get_result();
if ($resCheck && $resCheck->num_rows > 0) {
    http_response_code(409);
    echo json_encode([
        "kode" => 409,
        "success" => false,
        "message" => "Email atau username sudah digunakan"
    ]);
    exit;
}
$check->close();

/* =============== PANGGIL FUNCTION generate_kode_user() ===============
   ⚠️ DI SINI KITA PAKAI FUNCTION MU PERSIS SEPERTI YANG ADA DI DB
   Format hasil: 'US001' (5 karakter) → PASTI MUAT ke kolom kode_user(5)
*/
$stmtKode = $conn->prepare("SELECT generate_kode_users() AS kode_user");
$stmtKode->execute();
$resKode = $stmtKode->get_result();
$rowKode = $resKode ? $resKode->fetch_assoc() : null;

if (!$rowKode || empty($rowKode['kode_user'])) {
    http_response_code(500);
    echo json_encode([
        "kode" => 500,
        "success" => false,
        "message" => "Gagal membuat kode user"
    ]);
    exit;
}

$kode_user = $rowKode['kode_user']; // contoh: US001
$stmtKode->close();

/* =============== HANDLE AVATAR (SAMA LOGIKA DASAR) =============== */
$avatar_url = null;

if (isset($_FILES["avatar_file"]["name"]) && $_FILES["avatar_file"]["error"] === 0) {
    $dir = __DIR__ . "/../../images/user/";
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }

    $filename = time() . "_" . basename($_FILES["avatar_file"]["name"]);
    $target   = $dir . $filename;

    if (move_uploaded_file($_FILES["avatar_file"]["tmp_name"], $target)) {
        $avatar_url = "/images/user/" . $filename;
    }
}

// kalau masih belum ada avatar, pakai default admin
if (!$avatar_url) {
    $adminPics = [
        "/images/user/profil_admin_1.png",
        "/images/user/profil_admin_2.png"
    ];
    $avatar_url = $adminPics[array_rand($adminPics)];
}

/* =============== HASH PASSWORD =============== */
$hashed = password_hash($password, PASSWORD_BCRYPT);

/* =============== INSERT USER BARU =============== */
$stmt = $conn->prepare("
    INSERT INTO users 
    (kode_user, username, email, password, full_name, role, provider_type, provider_id,
     no_telp, alamat, avatar_url, email_verified, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param(
    "sssssssssssii",
    $kode_user, $username, $email, $hashed, $full_name,
    $role, $provider_type, $provider_id,
    $no_telp, $alamat, $avatar_url, $email_verified, $status
);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode([
        "kode" => 201,
        "success" => true,
        "message" => "Akun admin berhasil dibuat",
        "data" => [
            "kode_user"  => $kode_user,
            "full_name"  => $full_name,
            "email"      => $email,
            "role"       => $role,
            "avatar_url" => $avatar_url
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "kode" => 500,
        "success" => false,
        "message" => "Gagal membuat akun admin",
        "error"   => $stmt->error
    ]);
}

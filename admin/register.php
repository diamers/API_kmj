<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";
require __DIR__ . "/../shared/send_mail.php";
require __DIR__ . "/../shared/jwt_helper.php"; // JWT helper

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
$avatar_url = null;
$aktif = 1;

/* ======================= VALIDASI ROLE ======================= */
if (!$role) {
    http_response_code(400);
    echo json_encode(["code" => 400, "message" => "Role wajib diisi"]);
    exit;
}

/* ======================= VALIDASI KHUSUS ROLE ======================= */
if ($role == "owner") {
    if (!$username || !$email || !$password || !$full_name) {
        http_response_code(400);
        echo json_encode(["code" => 400, "message" => "Nama lengkap, username, email, dan password wajib untuk owner"]);
        exit;
    }
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $email_verified = 1;

} elseif ($role == "admin") {
    if (!$username || !$email || !$password || !$full_name || !$no_telp || !$alamat) {
        http_response_code(400);
        echo json_encode(["code" => 400, "message" => "Nama lengkap, username, email, password, telepon, alamat wajib untuk admin"]);
        exit;
    }
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $email_verified = 1;

} elseif ($role == "customer") {

    if ($provider_type == "local") {
        if (!$username || !$email || !$password || !$full_name || !$no_telp) {
            http_response_code(400);
            echo json_encode(["code" => 400, "message" => "Data wajib belum lengkap untuk customer lokal"]);
            exit;
        }
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $email_verified = 0;
        $verification_code = rand(100000, 999999);

    } else {
        // Google / Apple login
        if (!$provider_id || !$email) {
            http_response_code(400);
            echo json_encode(["code" => 400, "message" => "Email & Provider ID wajib untuk login sosial"]);
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

/* ======================= CEK EMAIL / USERNAME ======================= */
$check = $conn->prepare("SELECT * FROM users WHERE email=? OR username=?");
$check->bind_param("ss", $email, $username);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["code" => 409, "message" => "Email atau username sudah digunakan"]);
    exit;
}

/* ======================= GENERATE KODE USER ======================= */
$q = $conn->query("SELECT generate_kode_users() AS kode_user");
if (!$q || !($row = $q->fetch_assoc())) {
    http_response_code(500);
    echo json_encode(["code" => 500, "message" => "Gagal membuat kode user"]);
    exit;
}
$kode_user = $row['kode_user'];

/* ======================= HANDLE AVATAR ======================= */
if ($provider_type != "local") {
    $avatar_url = !empty($data["profile_url"]) ? $data["profile_url"] : null;
} elseif (isset($_FILES["avatar_file"]["name"]) && $_FILES["avatar_file"]["error"] === 0) {
    $dir = __DIR__ . "/../../images/user/";
    if (!file_exists($dir)) mkdir($dir, 0777, true);

    $filename = time() . "_" . basename($_FILES["avatar_file"]["name"]);
    $target = $dir . $filename;

    if (move_uploaded_file($_FILES["avatar_file"]["tmp_name"], $target)) {
        list($width, $height, $type) = getimagesize($target);
        switch ($type) {
            case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($target); break;
            case IMAGETYPE_PNG: $srcImg = imagecreatefrompng($target); break;
            case IMAGETYPE_WEBP: $srcImg = imagecreatefromwebp($target); break;
            default: $srcImg = null;
        }

        if ($srcImg) {
            $newSize = 512;
            $dstImg = imagecreatetruecolor($newSize, $newSize);
            if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
                imagealphablending($dstImg, false);
                imagesavealpha($dstImg, true);
            }
            imagecopyresampled($dstImg, $srcImg, 0,0,0,0,$newSize,$newSize,$width,$height);
            if ($type == IMAGETYPE_JPEG) imagejpeg($dstImg, $target, 90);
            if ($type == IMAGETYPE_PNG) imagepng($dstImg, $target);
            if ($type == IMAGETYPE_WEBP) imagewebp($dstImg, $target, 90);
            imagedestroy($srcImg); imagedestroy($dstImg);
        }
        $avatar_url = "/images/user/" . $filename;
    }
}

if (!$avatar_url) {
    if ($role == "owner" || $role == "admin") {
        $adminPics = ["/images/user/profil_admin_1.png", "/images/user/profil_admin_2.png"];
        $avatar_url = $adminPics[array_rand($adminPics)];
    } else {
        $userPics = [
            "/images/user/profil_user_1.png","/images/user/profil_user_2.png",
            "/images/user/profil_user_3.png","/images/user/profil_user_4.png",
            "/images/user/profil_user_5.png"
        ];
        $avatar_url = $userPics[array_rand($userPics)];
    }
}

/* ======================= INSERT USER ======================= */
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
    $no_telp, $alamat, $avatar_url, $email_verified, $aktif
);

if ($stmt->execute()) {

    // Email verification untuk customer lokal
    if ($role == "customer" && $provider_type == "local" && $verification_code) {
        $save = $conn->prepare("
            INSERT INTO email_verifications (kode_user, email, kode_verifikasi, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $save->bind_param("sss", $kode_user, $email, $verification_code);
        $save->execute();
        sendVerificationEmail($email, $full_name, $verification_code);
    }

    // Generate JWT untuk owner/admin
    $token = null;
    if ($role == "owner" || $role == "admin") {
        $token = generate_jwt([
            "kode_user" => $kode_user,
            "role" => $role,
            "email" => $email
        ]);
    }

    echo json_encode([
        "code" => 200,
        "message" => "Registrasi berhasil",
        "avatar_url" => $avatar_url,
        "kode_user" => $kode_user,
        "role" => $role,
        "token" => $token
    ]);
} else {
    http_response_code(500);
    echo json_encode(["code" => 500, "message" => "Gagal registrasi: " . $conn->error]);
}
?>

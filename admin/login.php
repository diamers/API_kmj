<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";

$data = json_decode(file_get_contents("php://input"), true);
$identifier = $data["identifier"] ?? null;
$password = $data["password"] ?? null;
$provider_type = $data["provider_type"] ?? "local";
$provider_id = $data["provider_id"] ?? null;

if ($provider_type == "local") {
    if (!$identifier || !$password) {
        echo json_encode([
            "code" => 400,
            "message" => "Email/Username dan password wajib diisi"
        ]);
        exit;
    }

    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    }

    $stmt->bind_param("s", $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user["password"])) {

        if ((int)$user["email_verified"] !== 1) {
            echo json_encode([
                "code" => 403,
                "message" => "Email Anda belum diverifikasi. Silakan verifikasi terlebih dahulu."
            ]);
            exit;
        }

        // Update last login
        $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE kode_user = ?");
        $update->bind_param("s", $user["kode_user"]);
        $update->execute();

        echo json_encode([
            "code" => 200,
            "message" => "Selamat, Login berhasil",
            "user" => [
                "kode_user" => $user["kode_user"],
                "username" => $user["username"],
                "full_name" => $user["full_name"],
                "email" => $user["email"],
                "role" => $user["role"],
                "provider_type" => $user["provider_type"]
            ]
        ]);
    } else {
        echo json_encode([
            "code" => 401,
            "message" => "Email/Username atau password salah"
        ]);
    }

} else {
    if (!$provider_id) {
        echo json_encode([
            "code" => 400,
            "message" => "Provider ID wajib diisi untuk login sosial"
        ]);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE provider_type=? AND provider_id=?");
    $stmt->bind_param("ss", $provider_type, $provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE kode_user = ?");
        $update->bind_param("s", $user["kode_user"]);
        $update->execute();

        echo json_encode([
            "code" => 200,
            "message" => "Login sosial berhasil",
            "user" => [
                "kode_user" => $user["kode_user"],
                "email" => $user["email"],
                "role" => $user["role"],
                "provider_type" => $user["provider_type"]
            ]
        ]);
    } else {
        echo json_encode([
            "code" => 404,
            "message" => "Akun sosial belum terdaftar"
        ]);
    }
}
?>

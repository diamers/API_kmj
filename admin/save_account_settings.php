<?php
// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require __DIR__ . "/../shared/config.php";

// Cek login
if (!isset($_SESSION['kode_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $kode_user = $_SESSION['kode_user'];
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $profile_image = $_POST['profile_image'] ?? '';

    // Validasi input
    if (empty($fullname)) {
        throw new Exception('Nama lengkap tidak boleh kosong');
    }

    if (empty($username)) {
        throw new Exception('Username tidak boleh kosong');
    }

    // Cek username duplikat
    $checkUsername = "SELECT kode_user FROM users WHERE username = ? AND kode_user != ?";
    $stmt = $conn->prepare($checkUsername);
    $stmt->bind_param('ss', $username, $kode_user);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Username sudah digunakan');
    }

    // Handle upload foto profil
    $avatar_url = null;
    if (!empty($profile_image)) {
        // Decode base64 image
        if (preg_match('/^data:image\/(\w+);base64,/', $profile_image, $type)) {
            $profile_image = substr($profile_image, strpos($profile_image, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif

            if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
                throw new Exception('Format gambar tidak valid');
            }

            $profile_image = str_replace(' ', '+', $profile_image);
            $image_data = base64_decode($profile_image);

            if ($image_data === false) {
                throw new Exception('Base64 decode gagal');
            }

            // Path ke folder API_KMJ/images/user/
            $upload_dir = '../images/user/';
            
            // Buat folder jika belum ada
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate nama file unik
            $filename = 'profil_' . strtolower(str_replace('-', '_', $kode_user)) . '_' . time() . '.' . $type;
            $filepath = $upload_dir . $filename;

            // Simpan file
            if (file_put_contents($filepath, $image_data)) {
                // Path relatif untuk disimpan di database (untuk diakses via URL)
                $avatar_url = '/images/user/' . $filename;

                // Hapus foto lama jika ada
                $oldPhoto = "SELECT avatar_url FROM users WHERE kode_user = ?";
                $stmt = $conn->prepare($oldPhoto);
                $stmt->bind_param('s', $kode_user);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $old = $result->fetch_assoc();
                    if (!empty($old['avatar_url'])) {
                        // Hapus dari folder API_KMJ
                        $old_file = '../images/user/' . basename($old['avatar_url']);
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                }
            } else {
                throw new Exception('Gagal menyimpan file gambar');
            }
        }
    }

    // Update data profil
    if ($avatar_url) {
        $updateQuery = "UPDATE users SET full_name = ?, username = ?, no_telp = ?, avatar_url = ? WHERE kode_user = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('sssss', $fullname, $username, $phone, $avatar_url, $kode_user);
    } else {
        $updateQuery = "UPDATE users SET full_name = ?, username = ?, no_telp = ? WHERE kode_user = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('ssss', $fullname, $username, $phone, $kode_user);
    }

    if (!$stmt->execute()) {
        throw new Exception('Gagal mengupdate profil');
    }

    // Update password jika diisi
    if (!empty($old_password) && !empty($new_password)) {
        if ($new_password !== $confirm_password) {
            throw new Exception('Password baru dan konfirmasi tidak cocok');
        }

        // Verifikasi password lama
        $checkPass = "SELECT password FROM users WHERE kode_user = ?";
        $stmt = $conn->prepare($checkPass);
        $stmt->bind_param('s', $kode_user);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!password_verify($old_password, $user['password'])) {
            throw new Exception('Password lama tidak sesuai');
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $updatePass = "UPDATE users SET password = ? WHERE kode_user = ?";
        $stmt = $conn->prepare($updatePass);
        $stmt->bind_param('ss', $hashed_password, $kode_user);
        
        if (!$stmt->execute()) {
            throw new Exception('Gagal mengupdate password');
        }
    }

    // Log aktivitas
    $description = "Mengubah data akun profil";
    $logQuery = "INSERT INTO activities (kode_user, activity_type, description, created_at) 
                VALUES (?, 'Update Account', ?, NOW())";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param('ss', $kode_user, $description);
    $logStmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Data akun berhasil diperbarui',
        'avatar_url' => $avatar_url
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
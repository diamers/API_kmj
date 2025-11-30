<?php
session_start();
require __DIR__ . "/../shared/config.php";


header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['kode_user']) || !in_array($_SESSION['role'], ['owner', 'admin'])) {
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

    // Cek apakah username sudah digunakan user lain
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
        $image_parts = explode(";base64,", $profile_image);
        $image_base64 = base64_decode($image_parts[1]);
        
        // Generate unique filename
        $filename = 'profile_' . $kode_user . '_' . time() . '.png';
        $filepath = '../../uploads/profiles/' . $filename;
        
        // Buat folder jika belum ada
        if (!file_exists('../../uploads/profiles/')) {
            mkdir('../../uploads/profiles/', 0777, true);
        }
        
        // Simpan file
        if (file_put_contents($filepath, $image_base64)) {
            $avatar_url = '/uploads/profiles/' . $filename;
            
            // Hapus foto lama jika ada
            $oldPhoto = "SELECT avatar_url FROM users WHERE kode_user = ?";
            $stmt = $conn->prepare($oldPhoto);
            $stmt->bind_param('s', $kode_user);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $old = $result->fetch_assoc();
                if (!empty($old['avatar_url']) && file_exists('../../' . $old['avatar_url'])) {
                    unlink('../../' . $old['avatar_url']);
                }
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
        'message' => 'Data akun berhasil diperbarui'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
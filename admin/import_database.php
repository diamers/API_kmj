<?php
session_start();
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');

// Cek akses
if (!isset($_SESSION['kode_user']) || !in_array($_SESSION['role'], ['owner', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Verifikasi password admin
    $password = $_POST['password'] ?? '';
    $kode_user = $_SESSION['kode_user'];
    
    if (empty($password)) {
        throw new Exception('Password tidak boleh kosong');
    }
    
    $checkPass = "SELECT password FROM users WHERE kode_user = ?";
    $stmt = $conn->prepare($checkPass);
    $stmt->bind_param('s', $kode_user);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        throw new Exception('User tidak ditemukan');
    }
    
    $user = $result->fetch_assoc();
    
    if (!password_verify($password, $user['password'])) {
        throw new Exception('Password salah! Pastikan Anda memasukkan password yang benar.');
    }
    
    // Cek apakah ada file yang diupload
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File SQL tidak ditemukan atau terjadi error saat upload');
    }
    
    $file = $_FILES['sql_file'];
    $filename = $file['name'];
    $fileTmp = $file['tmp_name'];
    $fileSize = $file['size'];
    
    // Validasi ekstensi file
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        throw new Exception('File harus berformat .sql');
    }
    
    // Validasi ukuran file (max 50MB)
    if ($fileSize > 50 * 1024 * 1024) {
        throw new Exception('Ukuran file terlalu besar! Maksimal 50MB');
    }
    
    // Baca isi file SQL
    $sqlContent = file_get_contents($fileTmp);
    
    if (empty($sqlContent)) {
        throw new Exception('File SQL kosong');
    }
    
    // Nonaktifkan foreign key check
    $conn->query('SET FOREIGN_KEY_CHECKS=0');
    $conn->query('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');
    
    // Split query by semicolon (handle multi-line statements)
    $sqlContent = preg_replace('/^--.*$/m', '', $sqlContent); // Remove comments
    $sqlContent = preg_replace('/^\/\*.*?\*\//ms', '', $sqlContent); // Remove /* */ comments
    
    $queries = array_filter(
        array_map('trim', explode(';', $sqlContent)),
        function($query) {
            return !empty($query) && strlen($query) > 5;
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    // Eksekusi setiap query
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        if ($conn->query($query)) {
            $successCount++;
        } else {
            $errorCount++;
            $errors[] = substr($query, 0, 100) . '... Error: ' . $conn->error;
            
            // Hanya simpan max 5 error untuk ditampilkan
            if (count($errors) >= 5) {
                break;
            }
        }
    }
    
    // Aktifkan kembali foreign key check
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    
    if ($errorCount > 0) {
        $errorMsg = "Restore selesai dengan {$errorCount} error dari " . count($queries) . " query.\n\n";
        $errorMsg .= "Sample errors:\n" . implode("\n", array_slice($errors, 0, 3));
        throw new Exception($errorMsg);
    }
    
    // Log aktivitas
    $description = "Restore database dari file: {$filename} (Queries: {$successCount})";
    $logQuery = "INSERT INTO activities (kode_user, activity_type, description, created_at) 
                VALUES (?, 'Restore Database', ?, NOW())";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param('ss', $kode_user, $description);
    $logStmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => "Database berhasil di-restore! ({$successCount} queries berhasil dijalankan)"
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
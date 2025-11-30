<?php
session_start();
require __DIR__ . "/../shared/config.php";


// Cek akses
if (!isset($_SESSION['kode_user']) || !in_array($_SESSION['role'], ['owner', 'admin'])) {
    die('Unauthorized access');
}

try {
    // Konfigurasi database
    $dbHost = 'localhost';
    $dbName = 'maverick_kmj';
    $dbUser = 'root';
    $dbPass = '';
    
    // Nama file backup
    $backupFile = 'backup_' . $dbName . '_' . date('Y-m-d_H-i-s') . '.sql';
    $backupPath = '../../backups/';
    
    // Buat folder backups jika belum ada
    if (!file_exists($backupPath)) {
        mkdir($backupPath, 0777, true);
    }
    
    $fullPath = $backupPath . $backupFile;
    
    // Jalankan mysqldump
    $command = sprintf(
        'mysqldump --user=%s --password=%s --host=%s %s > %s',
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbHost),
        escapeshellarg($dbName),
        escapeshellarg($fullPath)
    );
    
    exec($command, $output, $return);
    
    if ($return !== 0) {
        throw new Exception('Gagal membuat backup database');
    }
    
    // Hitung ukuran file
    $fileSize = filesize($fullPath);
    $fileSizeMB = round($fileSize / 1048576, 2);
    
    // Simpan log backup
    $kode_user = $_SESSION['kode_user'];
    $logQuery = "INSERT INTO backup_logs 
                (kode_user, backup_type, file_name, file_path, backup_size_mb, backup_time) 
                VALUES (?, 'manual', ?, ?, ?, NOW())";
    $stmt = $conn->prepare($logQuery);
    $stmt->bind_param('sssd', $kode_user, $backupFile, $fullPath, $fileSizeMB);
    $stmt->execute();
    
    // Log aktivitas
    $description = "Melakukan export database manual (Size: {$fileSizeMB} MB)";
    $activityQuery = "INSERT INTO activities (kode_user, activity_type, description, created_at) 
                     VALUES (?, 'Export Database', ?, NOW())";
    $actStmt = $conn->prepare($activityQuery);
    $actStmt->bind_param('ss', $kode_user, $description);
    $actStmt->execute();
    
    // Download file
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backupFile . '"');
    header('Content-Length: ' . $fileSize);
    readfile($fullPath);
    
    // Hapus file setelah download (optional)
    // unlink($fullPath);
    
    exit;

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

$conn->close();
?>
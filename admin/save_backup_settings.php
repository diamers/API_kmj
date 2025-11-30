<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['kode_user']) || !in_array($_SESSION['role'], ['owner', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $is_auto_backup = isset($_POST['is_auto_backup']) ? 1 : 0;
    $backup_interval = $_POST['backup_interval'] ?? 'daily';
    
    // Cek apakah sudah ada settings
    $check = $conn->query("SELECT id_setting FROM backup_settings LIMIT 1");
    
    if ($check->num_rows > 0) {
        // Update
        $update = "UPDATE backup_settings SET is_auto_backup = ?, backup_interval = ?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param('is', $is_auto_backup, $backup_interval);
    } else {
        // Insert
        $insert = "INSERT INTO backup_settings (is_auto_backup, backup_interval) VALUES (?, ?)";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param('is', $is_auto_backup, $backup_interval);
    }
    
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pengaturan backup berhasil disimpan'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
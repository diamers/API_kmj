<?php
session_start();
require __DIR__ . "/../shared/config.php";

// Cek akses
if (!isset($_SESSION['kode_user']) || !in_array($_SESSION['role'], ['owner', 'admin'])) {
    die('Unauthorized access');
}

try {
    $dbName = 'kmjshowrooms';
    
    // Nama file backup
    $backupFile = 'backup_' . $dbName . '_' . date('Y-m-d_H-i-s') . '.sql';
    $backupPath = '../backups/';
    
    // Buat folder backups jika belum ada
    if (!file_exists($backupPath)) {
        mkdir($backupPath, 0777, true);
    }
    
    $fullPath = $backupPath . $backupFile;
    
    // Get all tables
    $tables = array();
    $result = $conn->query('SHOW TABLES');
    
    if (!$result) {
        throw new Exception('Tidak dapat mengambil daftar tabel: ' . $conn->error);
    }
    
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    if (empty($tables)) {
        throw new Exception('Tidak ada tabel yang ditemukan di database');
    }
    
    // Start SQL output
    $sql = "-- Database: {$dbName}\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- PHP Version: " . phpversion() . "\n\n";
    $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql .= "SET time_zone = \"+00:00\";\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    // Loop through tables
    foreach ($tables as $table) {
        $sql .= "-- --------------------------------------------------------\n";
        $sql .= "-- Table structure for table `{$table}`\n";
        $sql .= "-- --------------------------------------------------------\n\n";
        
        // Drop table if exists
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n\n";
        
        // Create table
        $createResult = $conn->query("SHOW CREATE TABLE `{$table}`");
        if (!$createResult) {
            continue; // Skip table if error
        }
        $row = $createResult->fetch_row();
        $sql .= $row[1] . ";\n\n";
        
        // Get table data
        $dataResult = $conn->query("SELECT * FROM `{$table}`");
        if (!$dataResult) {
            continue; // Skip if error
        }
        
        $num_fields = $dataResult->field_count;
        $num_rows = $dataResult->num_rows;
        
        if ($num_rows > 0) {
            $sql .= "-- Dumping data for table `{$table}`\n\n";
            
            $rowCount = 0;
            while ($row = $dataResult->fetch_row()) {
                $sql .= "INSERT INTO `{$table}` VALUES(";
                
                for ($i = 0; $i < $num_fields; $i++) {
                    if ($row[$i] === null) {
                        $sql .= 'NULL';
                    } else {
                        $sql .= "'" . $conn->real_escape_string($row[$i]) . "'";
                    }
                    
                    if ($i < ($num_fields - 1)) {
                        $sql .= ',';
                    }
                }
                $sql .= ");\n";
                
                $rowCount++;
                
                // Add batch every 100 rows for better performance
                if ($rowCount % 100 == 0) {
                    $sql .= "\n";
                }
            }
            $sql .= "\n";
        }
        $sql .= "\n";
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Save to file
    $written = file_put_contents($fullPath, $sql);
    
    if (!$written) {
        throw new Exception('Gagal menulis file backup. Periksa permission folder.');
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
    $description = "Export database manual berhasil (Size: {$fileSizeMB} MB, Tables: " . count($tables) . ")";
    $activityQuery = "INSERT INTO activities (kode_user, activity_type, description, created_at) 
                     VALUES (?, 'Export Database', ?, NOW())";
    $actStmt = $conn->prepare($activityQuery);
    $actStmt->bind_param('ss', $kode_user, $description);
    $actStmt->execute();
    
    // Download file
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backupFile . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($fullPath);
    
    exit;

} catch (Exception $e) {
    // Log error
    error_log('Export Database Error: ' . $e->getMessage());
    
    echo '<h2>Error Export Database</h2>';
    echo '<p style="color: red;">' . $e->getMessage() . '</p>';
    echo '<p><a href="javascript:history.back()">← Kembali</a></p>';
}

$conn->close();
?>
    
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
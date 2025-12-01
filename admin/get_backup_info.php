<?php
session_start();
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['kode_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Ambil info backup terakhir
    $query = "SELECT file_name, backup_size_mb, backup_time 
              FROM backup_logs 
              ORDER BY backup_time DESC 
              LIMIT 1";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        // Format tanggal
        $timestamp = strtotime($data['backup_time']);
        $data['backup_time_formatted'] = strftime('%d %B %Y pukul %H.%M', $timestamp);
        
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'file_name' => '-',
                'backup_size_mb' => 0,
                'backup_time_formatted' => 'Belum ada backup'
            ]
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
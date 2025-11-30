<?php
session_start();
require __DIR__ . "/../shared/config.php";


header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

try {
    // Ambil data dari tabel showroom_general
    $query = "SELECT showroom_status, jual_mobil, schedule_pelanggan 
              FROM showroom_general 
              LIMIT 1";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } else {
        // Jika belum ada data, return default values
        echo json_encode([
            'success' => true,
            'data' => [
                'showroom_status' => 0,
                'jual_mobil' => 0,
                'schedule_pelanggan' => 1
            ]
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
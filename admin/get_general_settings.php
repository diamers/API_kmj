<?php
// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

require __DIR__ . "/../shared/config.php";

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
            'data' => [
                'showroom_status' => (int)$data['showroom_status'],
                'jual_mobil' => (int)$data['jual_mobil'],
                'schedule_pelanggan' => (int)$data['schedule_pelanggan']
            ]
        ]);
    } else {
        // Jika belum ada data, return default values
        echo json_encode([
            'success' => true,
            'data' => [
                'showroom_status' => 0,
                'jual_mobil' => 0,
                'schedule_pelanggan' => 1
            ],
            'message' => 'Menggunakan data default (belum ada data di database)'
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
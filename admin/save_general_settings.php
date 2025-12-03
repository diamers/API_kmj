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

// Validasi request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Ambil data dari request
    $showroom_status = isset($_POST['showroom_status']) ? 1 : 0;
    $jual_mobil = isset($_POST['jual_mobil']) ? 1 : 0;
    $schedule_pelanggan = isset($_POST['schedule_pelanggan']) ? 1 : 0;

    // Cek apakah sudah ada data di tabel showroom_general
    $checkQuery = "SELECT id_general FROM showroom_general LIMIT 1";
    $checkResult = $conn->query($checkQuery);

    if ($checkResult->num_rows > 0) {
        // Update data yang sudah ada
        $row = $checkResult->fetch_assoc();
        $id_general = $row['id_general'];
        
        $updateQuery = "UPDATE showroom_general 
                       SET showroom_status = ?, 
                           jual_mobil = ?, 
                           schedule_pelanggan = ?
                       WHERE id_general = ?";
        
        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('iiii', $showroom_status, $jual_mobil, $schedule_pelanggan, $id_general);
    } else {
        // Insert data baru
        $insertQuery = "INSERT INTO showroom_general (showroom_status, jual_mobil, schedule_pelanggan) 
                       VALUES (?, ?, ?)";
        
        $stmt = $conn->prepare($insertQuery);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('iii', $showroom_status, $jual_mobil, $schedule_pelanggan);
    }

    if ($stmt->execute()) {
        // Log aktivitas (jika ada session)
        if (isset($_SESSION['kode_user'])) {
            $kode_user = $_SESSION['kode_user'];
            $description = "Mengubah pengaturan general showroom";
            
            $logQuery = "INSERT INTO activities (kode_user, activity_type, description, created_at) 
                        VALUES (?, 'Update Settings', ?, NOW())";
            $logStmt = $conn->prepare($logQuery);
            if ($logStmt) {
                $logStmt->bind_param('ss', $kode_user, $description);
                $logStmt->execute();
                $logStmt->close();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Pengaturan general berhasil disimpan!',
            'data' => [
                'showroom_status' => $showroom_status,
                'jual_mobil' => $jual_mobil,
                'schedule_pelanggan' => $schedule_pelanggan
            ]
        ]);
    } else {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
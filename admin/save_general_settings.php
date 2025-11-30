<?php
session_start();
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');

// Cek apakah user sudah login dan memiliki akses
if (!isset($_SESSION['kode_user']) || !in_array($_SESSION['role'], ['owner', 'admin'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

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
        $stmt->bind_param('iiii', $showroom_status, $jual_mobil, $schedule_pelanggan, $id_general);
    } else {
        // Insert data baru
        $insertQuery = "INSERT INTO showroom_general (showroom_status, jual_mobil, schedule_pelanggan) 
                       VALUES (?, ?, ?)";
        
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param('iii', $showroom_status, $jual_mobil, $schedule_pelanggan);
    }

    if ($stmt->execute()) {
        // Log aktivitas
        $kode_user = $_SESSION['kode_user'];
        $description = "Mengubah pengaturan general showroom";
        
        $logQuery = "INSERT INTO activities (kode_user, activity_type, description, created_at) 
                    VALUES (?, 'Update Settings', ?, NOW())";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param('ss', $kode_user, $description);
        $logStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Pengaturan berhasil disimpan'
        ]);
    } else {
        throw new Exception('Gagal menyimpan pengaturan');
    }

    $stmt->close();
    if (isset($logStmt)) {
        $logStmt->close();
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
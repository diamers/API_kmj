<?php
session_start();
require __DIR__ . "/../shared/config.php";


header('Content-Type: application/json');

if (!isset($_SESSION['kode_user']) || !in_array($_SESSION['role'], ['owner', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $instagram_url = trim($_POST['instagram_url'] ?? '');
    $facebook_url = trim($_POST['facebook_url'] ?? '');
    $tiktok_url = trim($_POST['tiktok_url'] ?? '');
    $youtube_url = trim($_POST['youtube_url'] ?? '');

    // Format nomor WhatsApp (tambahkan +62 jika belum ada)
    if (!empty($whatsapp)) {
        $whatsapp = preg_replace('/[^0-9]/', '', $whatsapp);
        if (substr($whatsapp, 0, 1) === '0') {
            $whatsapp = '62' . substr($whatsapp, 1);
        } else if (substr($whatsapp, 0, 2) !== '62') {
            $whatsapp = '62' . $whatsapp;
        }
        $whatsapp = '+' . $whatsapp;
    }

    // Cek apakah sudah ada data
    $checkQuery = "SELECT id_contact FROM showroom_contacts LIMIT 1";
    $checkResult = $conn->query($checkQuery);

    if ($checkResult->num_rows > 0) {
        // Update
        $row = $checkResult->fetch_assoc();
        $id_contact = $row['id_contact'];
        
        $updateQuery = "UPDATE showroom_contacts 
                       SET whatsapp = ?, instagram_url = ?, facebook_url = ?, 
                           tiktok_url = ?, youtube_url = ?
                       WHERE id_contact = ?";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('sssssi', $whatsapp, $instagram_url, $facebook_url, 
                         $tiktok_url, $youtube_url, $id_contact);
    } else {
        // Insert
        $insertQuery = "INSERT INTO showroom_contacts 
                       (whatsapp, instagram_url, facebook_url, tiktok_url, youtube_url) 
                       VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param('sssss', $whatsapp, $instagram_url, $facebook_url, 
                         $tiktok_url, $youtube_url);
    }

    if (!$stmt->execute()) {
        throw new Exception('Gagal menyimpan kontak & sosial media');
    }

    // Log aktivitas
    $kode_user = $_SESSION['kode_user'];
    $description = "Mengubah kontak dan sosial media showroom";
    $logQuery = "INSERT INTO activities (kode_user, activity_type, description, created_at) 
                VALUES (?, 'Update Contacts', ?, NOW())";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param('ss', $kode_user, $description);
    $logStmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Kontak & sosial media berhasil disimpan'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
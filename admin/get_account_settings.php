<?php
session_start();
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['kode_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $kode_user = $_SESSION['kode_user'];
    
    $query = "SELECT username, email, full_name, no_telp, avatar_url FROM users WHERE kode_user = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $kode_user);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        // Add full URL untuk avatar
        if (!empty($data['avatar_url'])) {
            $data['avatar_full_url'] = 'http://localhost/API_KMJ' . $data['avatar_url'];
        } else {
            $initial = strtoupper(substr($data['username'], 0, 1));
            $data['avatar_full_url'] = "https://via.placeholder.com/150/007bff/ffffff?text={$initial}";
        }
        
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
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
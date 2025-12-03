<?php
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');

try {
    $query = "SELECT whatsapp, instagram_url, facebook_url, tiktok_url, youtube_url 
              FROM showroom_contacts LIMIT 1";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        // Format WhatsApp untuk ditampilkan (hilangkan +62)
        if (!empty($data['whatsapp'])) {
            $wa = str_replace('+62', '', $data['whatsapp']);
            $data['whatsapp_display'] = $wa;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'whatsapp' => '',
                'whatsapp_display' => '',
                'instagram_url' => '',
                'facebook_url' => '',
                'tiktok_url' => '',
                'youtube_url' => ''
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
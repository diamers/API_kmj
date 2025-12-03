<?php
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');

try {
    $query = "SELECT hari, slot_index, jam_buka, jam_tutup, is_active 
              FROM showroom_schedule 
              ORDER BY 
                FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'),
                slot_index";
    
    $result = $conn->query($query);
    $schedules = [];

    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $schedules
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
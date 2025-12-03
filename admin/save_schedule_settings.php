<?php
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $schedules = json_decode($_POST['schedules'], true);
    
    if (!is_array($schedules)) {
        throw new Exception('Format data schedule tidak valid');
    }

    // Hapus schedule lama
    $conn->query("DELETE FROM showroom_schedule");

    // Insert schedule baru
    $insertQuery = "INSERT INTO showroom_schedule (hari, slot_index, jam_buka, jam_tutup, is_active) 
                   VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);

    foreach ($schedules as $schedule) {
        $hari = $schedule['hari'];
        $slot_index = $schedule['slot_index'];
        $jam_buka = $schedule['jam_buka'];
        $jam_tutup = $schedule['jam_tutup'];
        $is_active = $schedule['is_active'] ? 1 : 0;

        $stmt->bind_param('sissi', $hari, $slot_index, $jam_buka, $jam_tutup, $is_active);
        $stmt->execute();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Jadwal operasional berhasil disimpan'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
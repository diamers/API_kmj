<?php
require __DIR__ . "/../shared/config.php";

header('Content-Type: application/json');

$sql = "SELECT * FROM showroom_schedule 
        ORDER BY FIELD(hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), slot_index ASC";
$result = $conn->query($sql);

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

if (count($schedules) > 0) {
    http_response_code(200);
    echo json_encode([
        "code" => 200,
        "message" => "Data berhasil ditemukan",
        "data" => $schedules
    ]);
} else {
    http_response_code(200);
    echo json_encode([
        "code" => 200,
        "message" => "Tidak ada jadwal tersedia",
        "data" => []
    ]);
}
?>

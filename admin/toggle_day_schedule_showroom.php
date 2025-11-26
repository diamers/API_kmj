<?php
require __DIR__ . "/../shared/config.php";
header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['hari']) || !isset($input['is_active'])) {
    echo json_encode([
        "code" => 400,
        "message" => "Parameter tidak lengkap"
    ]);
    exit();
}

$hari = $input['hari'];
$is_active = (int)$input['is_active'];

$sqlCheck = "SELECT id_schedule FROM showroom_schedule WHERE hari = ?";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param("s", $hari);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();

if ($resultCheck->num_rows > 0) {

    $sqlUpdate = "UPDATE showroom_schedule SET is_active = ? WHERE hari = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param("is", $is_active, $hari);
    if ($stmtUpdate->execute()) {
        echo json_encode([
            "code" => 200,
            "message" => "Status hari berhasil diperbarui"
        ]);
    } else {
        echo json_encode([
            "code" => 500,
            "message" => "Gagal memperbarui status hari"
        ]);
    }

} else {

    $slot_index = 1;
    $jam_buka = "09:00:00";
    $jam_tutup = "10:00:00";

    $sqlInsert = "INSERT INTO showroom_schedule (hari, slot_index, jam_buka, jam_tutup, is_active)
                  VALUES (?, ?, ?, ?, ?)";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bind_param("sissi", $hari, $slot_index, $jam_buka, $jam_tutup, $is_active);

    if ($stmtInsert->execute()) {
        echo json_encode([
            "code" => 201,
            "message" => "Slot baru dibuat karena hari belum memiliki jadwal"
        ]);
    } else {
        echo json_encode([
            "code" => 500,
            "message" => "Gagal membuat slot baru"
        ]);
    }
}

$conn->close();

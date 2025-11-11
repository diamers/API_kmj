<?php
require_once __DIR__ . '/../../shared/db.php';  // koneksi
header('Content-Type: application/json');

// ambil data dari DB
$result = $conn->query("SELECT * FROM cars");

$cars = [];
while ($row = $result->fetch_assoc()) {
    $cars[] = $row;
}

echo json_encode([
    "success" => true,
    "data" => $cars
]);

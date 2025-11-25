<?php
require __DIR__ . "/../shared/config.php";
header('Content-Type: application/json');

$sql = "SELECT * FROM showroom_contacts ORDER BY id_contact DESC";
$result = $conn->query($sql);

$contacts = [];
while ($row = $result->fetch_assoc()) {
    $contacts[] = $row;
}

echo json_encode([
    "code" => 200,
    "data" => $contacts
]);

$conn->close();

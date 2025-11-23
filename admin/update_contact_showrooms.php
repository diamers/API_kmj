<?php
require __DIR__ . "/../shared/config.php";
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['id_contact'])) {
    echo json_encode(["code" => 400, "message" => "id_contact diperlukan"]);
    exit;
}

$id_contact = $conn->real_escape_string($input['id_contact']);
$fields = [];

foreach (['whatsapp', 'instagram_url', 'facebook_url', 'tiktok_url', 'youtube_url'] as $field) {
    if (isset($input[$field])) {
        $value = $conn->real_escape_string($input[$field]);
        $fields[] = "$field = " . ($value !== '' ? "'$value'" : "NULL");
    }
}

if (empty($fields)) {
    echo json_encode(["code" => 400, "message" => "Tidak ada field yang perlu diperbarui"]);
    exit;
}

$sql = "UPDATE showroom_contacts SET " . implode(", ", $fields) . " WHERE id_contact = '$id_contact'";

if ($conn->query($sql)) {
    echo json_encode(["code" => 200, "message" => "URL berhasil di update"]);
} else {
    echo json_encode(["code" => 500, "message" => "Gagal update URL"]);
}

$conn->close();

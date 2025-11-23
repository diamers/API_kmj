<?php
require __DIR__ . "/../shared/config.php";
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['whatsapp']) || empty($input['whatsapp'])) {
    echo json_encode(["code" => 400, "message" => "No. Whatsapp wajib diisi"]);
    exit;
}

$whatsapp = $conn->real_escape_string($input['whatsapp']);
$instagram = isset($input['instagram_url']) ? "'" . $conn->real_escape_string($input['instagram_url']) . "'" : "NULL";
$facebook = isset($input['facebook_url']) ? "'" . $conn->real_escape_string($input['facebook_url']) . "'" : "NULL";
$tiktok = isset($input['tiktok_url']) ? "'" . $conn->real_escape_string($input['tiktok_url']) . "'" : "NULL";
$youtube = isset($input['youtube_url']) ? "'" . $conn->real_escape_string($input['youtube_url']) . "'" : "NULL";

$sql = "INSERT INTO showroom_contacts 
        (whatsapp, instagram_url, facebook_url, tiktok_url, youtube_url)
        VALUES ('$whatsapp', $instagram, $facebook, $tiktok, $youtube)";

if ($conn->query($sql)) {
    echo json_encode([
        "code" => 201,
        "message" => "URL berhasil ditambahkan"
    ]);
} else {
    echo json_encode([
        "code" => 500,
        "message" => "Gagal menambahkan URL"
    ]);
}

$conn->close();

<?php
require __DIR__ . "/../shared/config.php";

header("Content-Type: application/json");

if (!isset($_GET['id'])) {
    echo json_encode([
        "code" => 400,
        "message" => "Parameter id wajib"
    ]);
    exit;
}

$id = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.kode_user, u.full_name, a.activity_type, 
               a.description, a.created_at
        FROM activities a
        LEFT JOIN users u ON a.kode_user = u.kode_user
        WHERE a.id = :id
        LIMIT 1
    ");

    $stmt->execute([":id" => $id]);
    $data = $stmt->fetch();

    if (!$data) {
        echo json_encode([
            "code" => 404,
            "message" => "Activity tidak ditemukan"
        ]);
        exit;
    }

    echo json_encode([
        "code" => 200,
        "data" => $data
    ]);
} catch (Exception $e) {
    echo json_encode([
        "code" => 500,
        "message" => $e->getMessage()
    ]);
}

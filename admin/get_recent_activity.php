<?php
require __DIR__ . "/../shared/config.php";

header("Content-Type: application/json");

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

try {
    $stmt = $pdo->prepare("
        SELECT id, activity_type, description, created_at
        FROM activities
        ORDER BY created_at DESC
        LIMIT :limit
    ");

    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        "code" => 200,
        "data" => $stmt->fetchAll()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "code" => 500,
        "message" => $e->getMessage()
    ]);
}

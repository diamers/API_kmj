<?php
require __DIR__ . "/../shared/config.php";

header("Content-Type: application/json");

$limit  = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Mapping kata kunci berdasarkan filter
$filterConditions = [
    "mobil" => [
        "Tambah Mobil",
        "Delete Mobil",
        "Delete Foto",
        "Update Mobil"
    ],
    "transaksi" => [
        "Tambah Transaksi",
        "Update Transaksi"
    ]
];

$whereSql = "";
$params = [];

if ($filter !== "all" && isset($filterConditions[$filter])) {
    // Buat kondisi WHERE IN (?,?,?)
    $placeholders = rtrim(str_repeat('?,', count($filterConditions[$filter])), ',');
    $whereSql = "WHERE activity_type IN ($placeholders)";
    $params = $filterConditions[$filter];
}

try {

    $sql = "
        SELECT id, activity_type, description, created_at
        FROM activities
        $whereSql
        ORDER BY created_at DESC
        LIMIT ?
    ";

    $stmt = $pdo->prepare($sql);

    // Binding parameters (kategori + limit)
    $i = 1;
    foreach ($params as $value) {
        $stmt->bindValue($i++, $value);
    }
    $stmt->bindValue($i, $limit, PDO::PARAM_INT);

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

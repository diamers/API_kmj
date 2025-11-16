<?php
require __DIR__ . "/../shared/config.php";
header("Content-Type: application/json; charset=UTF-8");

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_available FROM mobil WHERE status = 'available'");
    $stmt->execute();
    $totalAvailable = (int) $stmt->fetch(PDO::FETCH_ASSOC)["total_available"];


    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_reserved FROM mobil WHERE status = 'reserved'");
    $stmt->execute();
    $totalReserved = (int) $stmt->fetch(PDO::FETCH_ASSOC)["total_reserved"];


    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_transaksi 
        FROM transaksi 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
        AND status = 'selesai'
    ");
    $stmt->execute();
    $totalTransaksi = (int) $stmt->fetch(PDO::FETCH_ASSOC)["total_transaksi"];


    $stmt = $pdo->prepare("
        SELECT SUM(harga_akhir) AS total_pendapatan
        FROM transaksi
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
        AND status = 'selesai'
    ");
    $stmt->execute();

    $pendapatan = $stmt->fetch(PDO::FETCH_ASSOC)["total_pendapatan"];
    if ($pendapatan === null) $pendapatan = 0;


    echo json_encode([
        "code" => 200,
        "data" => [
            "total_mobil_available" => $totalAvailable,
            "total_transaksi_bulan_ini" => $totalTransaksi,
            "total_pendapatan_bulan_ini" => (int)$pendapatan,
            "total_mobil_reserved" => $totalReserved
        ]
    ]);

} catch (Exception $e) {

    echo json_encode([
        "code" => 500,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

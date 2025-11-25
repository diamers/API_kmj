<?php
header('Content-Type: application/json; charset=utf-8');
require "../shared/config.php";

$response = [
    "status" => 400,
    "message" => "Unknown error"
];
try {

    // ========== 1. DATA TRANSAKSI ==========
    $sqlTransaksi = "
        SELECT 
            t.kode_transaksi,
            t.nama_pembeli,
            t.tipe_pembayaran,
            t.harga_akhir,
            t.status,
            t.created_at,
            m.nama_mobil
        FROM transaksi t
        LEFT JOIN mobil m ON t.kode_mobil = m.kode_mobil
        ORDER BY t.created_at DESC
    ";

    $resultT = $conn->query($sqlTransaksi);
    $transaksi = [];

    while ($row = $resultT->fetch_assoc()) {
        // format harga + tanggal
        $row['harga_akhir'] = (int) $row['harga_akhir'];
        $row['tanggal'] = date('d-m-Y', strtotime($row['created_at']));
        $transaksi[] = $row;
    }

    // ========== 2. DATA MOBIL ==========
    $sqlMobil = "
        SELECT 
            kode_mobil,
            nama_mobil,
            tahun_mobil,
            jenis_kendaraan,
            status,
            full_prize
        FROM mobil
        ORDER BY nama_mobil ASC
    ";

    $resultM = $conn->query($sqlMobil);
    $mobil = [];

    while ($row = $resultM->fetch_assoc()) {
        $row['full_prize'] = (int) $row['full_prize'];
        $mobil[] = $row;
    }

    // ========== FINAL RESPONSE ==========
    $response = [
        "status" => 200,
        "message" => "OK",
        "data" => [
            "transaksi" => $transaksi,
            "mobil" => $mobil
        ]
    ];

} catch (Exception $e) {
    $response = [
        "status" => 400,
        "message" => $e->getMessage()
    ];
}

echo json_encode($response);

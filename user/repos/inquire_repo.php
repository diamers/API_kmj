<?php

function create_inquire(mysqli $conn, array $data): int
{
    $sql = "INSERT INTO inquire 
            (kode_user, kode_mobil, uji_beli, jenis_janji, tanggal, waktu, no_telp, note, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssiissss',
        $data['kode_user'],
        $data['kode_mobil'],
        $data['uji_beli'],
        $data['jenis_janji'],
        $data['tanggal'],
        $data['waktu'],
        $data['no_telp'],
        $data['note']
    );
    $stmt->execute();

    return $conn->insert_id;
}


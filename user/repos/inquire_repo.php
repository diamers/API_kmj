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

function get_default_whatsapp(mysqli $conn): ?string
{
    // ambil nomor WA dari tabel showroom_contacts
    $sql = "SELECT whatsapp FROM showroom_contacts ORDER BY id_contact ASC LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        return $row['whatsapp'] ?? null;
    }

    return null;
}
function get_inquires_by_user(mysqli $conn, string $kode_user, int $limit = 5): array
{
    $sql = "
        SELECT 
            i.id_inquire,
            i.kode_user,
            i.kode_mobil,
            i.uji_beli,
            i.jenis_janji,
            i.tanggal,
            i.waktu,
            i.no_telp,
            i.note,
            i.status
        FROM inquire i
        WHERE i.kode_user = ?
        ORDER BY i.id_inquire DESC
        LIMIT ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $kode_user, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}


<?php
require "../shared/config.php"; 
header("Content-Type: application/json");

$response = [
    "code"    => 400,
    "success" => false,
    "api_type" => "WEB_ADMIN",
    "message" => "Terjadi kesalahan",
    "data" => []
];

try {

    $query = "
        SELECT 
            m.kode_mobil,
            m.nama_mobil,
            m.tahun_mobil,
            m.full_prize,
            m.warna_exterior,
            m.warna_interior,
            m.tipe_bahan_bakar,
            m.jenis_kendaraan,
            m.jarak_tempuh,
            m.angsuran,
            m.uang_muka AS dp,
            m.tenor,
            m.status,
            mf.nama_file AS foto
        FROM mobil m
        LEFT JOIN mobil_foto mf 
            ON mf.kode_mobil = m.kode_mobil 
            AND mf.urutan = 1
        ORDER BY m.created_at DESC;
    ";

    $result = $conn->query($query);
    $data = [];

    while ($row = $result->fetch_assoc()) {

        $row['full_prize'] = (int)$row['full_prize'];
        $row['angsuran']   = (int)$row['angsuran'];
        $row['dp']         = (int)$row['dp'];
        $row['jarak_tempuh'] = (int)$row['jarak_tempuh'];

        if (!empty($row['foto'])) {
            $row['foto'] = BASE_URL . $row['foto'];
        } else {
            $row['foto'] = null;
        }

        $data[] = $row;
    }

    $response['code'] = 200;
    $response['success'] = true;
    $response['message'] = "OK";
    $response['data'] = $data;

} catch (Exception $e) {

    $response['code'] = 400;
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

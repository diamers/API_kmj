<?php
require "../shared/config.php";
header("Content-Type: application/json");

try {
    $query = "
        SELECT 
            m.kode_mobil,
            m.nama_mobil,
            m.tahun_mobil,
            m.warna_exterior,
            m.tipe_bahan_bakar,
            m.jarak_tempuh,
            m.full_prize,
            m.angsuran,
            m.tenor,
            m.uang_muka AS dp,
            m.status,
            mf.nama_file AS foto
        FROM mobil m
        LEFT JOIN mobil_foto mf 
            ON mf.kode_mobil = m.kode_mobil 
            AND mf.tipe_foto = 'depan'   -- jadi foto urutan 1 sebagai thumbnail default
        ORDER BY m.created_at DESC;
    ";

    $result = $conn->query($query);

    $data = [];
    while ($row = $result->fetch_assoc()) {

        // Kolom nama_file sudah berbentuk /API_KMJ/images/mobil/abc.jpg
        if (!empty($row['foto'])) {
            $row['foto'] = BASE_URL . $row['foto'];
        } else {
            $row['foto'] = null;
        }


        $data[] = $row;
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
?>
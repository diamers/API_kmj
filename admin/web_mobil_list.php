<?php
require "../shared/config.php"; // koneksi + BASE_URL
header("Content-Type: application/json");

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

        if (!empty($row['foto'])) {
            // path di DB: /images/mobil/xxx.jpg
            $row['foto'] = BASE_URL . $row['foto'];
        } else {
            $row['foto'] = null;
        }

        $data[] = $row;
    }

    echo json_encode([
        "success" => true,
        "api_type" => "WEB_ADMIN",   // 🔥 penanda bahwa ini API WEB ADMIN
        "data" => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "api_type" => "WEB_ADMIN",
        "message" => $e->getMessage()
    ]);
}
?>
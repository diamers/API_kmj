<?php
require_once __DIR__ . '/../../shared/config.php';

function get_cars_for_perbandingan(string $kode1, string $kode2): array
{
    global $pdo;

    // karena di DB nama_file sudah "images/mobil/xxxx.png",
    // kita cukup tambahin BASE_URL saja di depannya
    $baseImageUrl = BASE_URL . '/';

    $sql = "
        SELECT 
            m.kode_mobil,
            m.nama_mobil,
            m.tahun_mobil,
            m.jarak_tempuh,
            m.jenis_kendaraan,
            m.sistem_penggerak,
            m.warna_exterior,
            m.warna_interior,
            m.tipe_bahan_bakar,
            m.full_prize,
            m.angsuran,
            m.tenor,
            -- hasilnya: BASE_URL + nama_file (yang sudah 'images/mobil/...png')
            CONCAT(:baseImageUrl, COALESCE(f.nama_file, '')) AS foto_depan
        FROM mobil m
        LEFT JOIN mobil_foto f
            ON f.kode_mobil = m.kode_mobil
           AND f.tipe_foto = 'depan'
        WHERE m.kode_mobil IN (:kode1, :kode2)
        ORDER BY FIELD(m.kode_mobil, :kode1, :kode2)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':baseImageUrl' => $baseImageUrl,
        ':kode1'        => $kode1,
        ':kode2'        => $kode2,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function get_fitur_for_perbandingan(string $kode1, string $kode2): array
{
    global $pdo;

    $sql = "
        SELECT
            f.nama_fitur,
            MAX(CASE WHEN mf.kode_mobil = :kode1 THEN 1 ELSE 0 END) AS car1,
            MAX(CASE WHEN mf.kode_mobil = :kode2 THEN 1 ELSE 0 END) AS car2
        FROM fitur f
        LEFT JOIN mobil_fitur mf ON mf.id_fitur = f.id_fitur
        WHERE mf.kode_mobil IN (:kode1, :kode2)
        GROUP BY f.id_fitur, f.nama_fitur
        ORDER BY f.nama_fitur
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'kode1' => $kode1,
        'kode2' => $kode2,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

<?php
// API_KMJ/user/repos/cars_repo.php

require_once __DIR__ . '/../../shared/config.php'; // pastikan di sini ada $pdo / $conn

/**
 * Ambil semua mobil (status available) + satu foto depan kalau ada
 */
function get_all_cars()
{
    // pakai variabel koneksi dari config.php
    global $pdo;

    $sql = "
        SELECT 
            m.kode_mobil,
            m.nama_mobil,
            m.tahun_mobil,
            m.jarak_tempuh,
            m.full_prize,
            m.uang_muka,
            m.tenor,
            m.angsuran,
            m.jenis_kendaraan,
            m.sistem_penggerak,
            m.tipe_bahan_bakar,
            m.warna_interior,
            m.warna_exterior,
            m.status,
            f.nama_file AS foto_utama
        FROM mobil m
        LEFT JOIN mobil_foto f 
            ON f.kode_mobil = m.kode_mobil 
           AND f.tipe_foto = 'depan'
           AND f.urutan = 1
        WHERE m.status = 'available'
        ORDER BY m.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

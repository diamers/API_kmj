<?php
// user/routes/favorites.php

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../../shared/response.php';

// helper singkat
function json_ok($data = [], $message = 'OK') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
}

function json_err($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // ========= ADD / REMOVE FAVORITE =========
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data) {
        json_err('Body request harus JSON');
    }

    $kode_user  = $data['kode_user']  ?? null;
    $kode_mobil = $data['kode_mobil'] ?? null;
    $action     = $data['action']     ?? 'add';

    if (!$kode_user || !$kode_mobil) {
        json_err('kode_user dan kode_mobil wajib diisi');
    }

    global $pdo;

    if ($action === 'add') {
        // Cek dulu, kalau sudah ada jangan dobel
        $cek = $pdo->prepare("SELECT id_favorite FROM favorite 
                              WHERE kode_user = :u AND kode_mobil = :m");
        $cek->execute([
            ':u' => $kode_user,
            ':m' => $kode_mobil
        ]);

        if ($cek->fetch()) {
            json_ok(null, 'Sudah ada di favorit');
        }

        $stmt = $pdo->prepare("INSERT INTO favorite (kode_user, kode_mobil) 
                               VALUES (:u, :m)");
        $stmt->execute([
            ':u' => $kode_user,
            ':m' => $kode_mobil
        ]);

        json_ok(null, 'Berhasil ditambahkan ke favorit');
    } elseif ($action === 'remove') {
        $stmt = $pdo->prepare("DELETE FROM favorite 
                               WHERE kode_user = :u AND kode_mobil = :m");
        $stmt->execute([
            ':u' => $kode_user,
            ':m' => $kode_mobil
        ]);

        json_ok(null, 'Berhasil dihapus dari favorit');
    } else {
        json_err('Action tidak dikenali (harus add/remove)');
    }

} elseif ($method === 'GET') {
    // ========= LIST FAVORITE PER USER =========
    $kode_user = $_GET['kode_user'] ?? null;
    if (!$kode_user) {
        json_err('kode_user wajib diisi');
    }

    global $pdo;

    // Join ke mobil + 1 foto depan (kalau ada)
    $sql = "SELECT 
                m.kode_mobil,
                m.nama_mobil,
                m.tahun_mobil,
                m.jarak_tempuh,
                m.uang_muka,
                m.angsuran,
                m.tenor,
                m.status,
                mf.nama_file AS foto_depan
            FROM favorite f
            JOIN mobil m ON f.kode_mobil = m.kode_mobil
            LEFT JOIN mobil_foto mf 
                ON mf.kode_mobil = m.kode_mobil 
               AND mf.tipe_foto = 'depan'
               AND mf.urutan = 1
            WHERE f.kode_user = :u";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':u' => $kode_user]);

    $rows = $stmt->fetchAll();

    $data = array_map(function ($r) {
        return [
            'kode_mobil'   => $r['kode_mobil'],
            'nama_mobil'   => $r['nama_mobil'],
            'tahun_mobil'  => $r['tahun_mobil'],
            'jarak_tempuh' => $r['jarak_tempuh'],
            'dp'           => $r['uang_muka'],
            'angsuran'     => $r['angsuran'],
            'tenor'        => $r['tenor'],
            'status'       => $r['status'],
            'foto'         => $r['foto_depan'] 
                ? BASE_URL . '/images/mobil/' . $r['foto_depan'] 
                : null
        ];
    }, $rows);

    json_ok($data);
} else {
    json_err('Method tidak diizinkan', 405);
}

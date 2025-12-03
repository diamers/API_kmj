<?php
// API_KMJ/user/routes/profile.php

header('Content-Type: application/json');

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../repos/profile_repo.php';

function send_json($status, $message, $data = null, $http_code = 200)
{
    http_response_code($http_code);
    $res = [
        'status'  => $status,
        'message' => $message,
    ];
    if ($data !== null) {
        $res['data'] = $data;
    }
    echo json_encode($res);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // ======================
    // GET: ambil profil user
    // ======================
    if ($method === 'GET') {
        $kode_user = $_GET['kode_user'] ?? null;

        if (!$kode_user) {
            send_json(false, 'kode_user wajib diisi', null, 400);
        }

        $user = profile_get_user($kode_user);
        if (!$user) {
            send_json(false, 'User tidak ditemukan', null, 404);
        }

        send_json(true, 'OK', $user);
    }

    // ==========================
    // POST: update profil + foto
    // ==========================
    if ($method === 'POST') {
        $kode_user = $_POST['kode_user'] ?? null;
        $full_name = trim($_POST['full_name'] ?? '');
        $no_telp   = trim($_POST['no_telp'] ?? '');
        $alamat    = trim($_POST['alamat'] ?? '');

        if (!$kode_user) {
            send_json(false, 'kode_user wajib diisi', null, 400);
        }
        if ($full_name === '') {
            send_json(false, 'Nama lengkap tidak boleh kosong.', null, 400);
        }

        // Null-kan kalau string kosong
        $no_telp = $no_telp !== '' ? $no_telp : null;
        $alamat  = $alamat !== '' ? $alamat : null;

        // ========== HANDLE AVATAR DARI BASE64 (OPTIONAL) ==========
        $avatar_url    = null;
        $avatar_base64 = $_POST['avatar_base64'] ?? '';
        $avatar_name   = $_POST['avatar_name'] ?? '';

        if ($avatar_base64 !== '') {
            $binary = base64_decode($avatar_base64);
            if ($binary === false) {
                send_json(false, 'Data avatar tidak valid (base64).', null, 400);
            }

            // Validasi image
            $info = @getimagesizefromstring($binary);
            if ($info === false) {
                send_json(false, 'File avatar bukan gambar yang valid.', null, 400);
            }

            $mime = $info['mime'] ?? '';
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowed)) {
                send_json(false, 'Format file tidak didukung. Hanya JPG/PNG/WEBP.', null, 400);
            }

            // Buat image resource
            $src = @imagecreatefromstring($binary);
            if (!$src) {
                send_json(false, 'Gagal membaca data gambar.', null, 400);
            }

            $w = imagesx($src);
            $h = imagesy($src);

            // Resize + crop ke 512x512
            $size    = 512;
            $minSide = min($w, $h);
            $cropX   = (int)(($w - $minSide) / 2);
            $cropY   = (int)(($h - $minSide) / 2);

            $dst = imagecreatetruecolor($size, $size);
            imagecopyresampled(
                $dst,
                $src,
                0,
                0,
                $cropX,
                $cropY,
                $size,
                $size,
                $minSide,
                $minSide
            );

            // Simpan ke folder images/user
            $dir = __DIR__ . '/../../images/user/';
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $newFileName = 'USER_' . $kode_user . '_' . time() . '.png';
            $target      = $dir . $newFileName;

            imagepng($dst, $target);

            imagedestroy($src);
            imagedestroy($dst);

            // Path yang disimpan di DB
            $avatar_url = '/API_KMJ/images/user/' . $newFileName;
        }

        // Update DB
        profile_update_user($kode_user, $full_name, $no_telp, $alamat, $avatar_url);

        // Ambil data terbaru
        $user = profile_get_user($kode_user);

        send_json(true, 'Profil berhasil diperbarui.', $user);
    }

    // Kalau method lain (PUT/DELETE) belum didukung
    send_json(false, 'Metode tidak diizinkan', null, 405);

} catch (Exception $e) {
    send_json(false, 'Terjadi kesalahan server: ' . $e->getMessage(), null, 500);
}

<?php
// API_KMJ/user/routes/delete_account.php

header('Content-Type: application/json');

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../repos/account_repo.php';

function send_json($status, $message, $data = null, $http_code = 200)
{
  http_response_code($http_code);
  $res = [
    'status' => $status,
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
  if ($method !== 'POST') {
    send_json(false, 'Metode tidak diizinkan', null, 405);
  }

  $kode_user = $_POST['kode_user'] ?? null;
  $password = $_POST['password'] ?? null;

  if (!$kode_user || !$password) {
    send_json(false, 'kode_user dan password wajib diisi.', null, 400);
  }

  // Ambil user + password hash
  $user = account_get_user_with_password($kode_user);
  if (!$user) {
    send_json(false, 'User tidak ditemukan.', null, 404);
  }

  $hash = $user['password'] ?? '';

  // ❗ Asumsi password disimpan pakai password_hash()
  if (!password_verify($password, $hash)) {
    send_json(false, 'Password salah.', null, 401);
  }

  // Simpan dulu info avatar untuk dihapus file-nya
  $avatar_url = $user['avatar_url'] ?? null;

  // Hapus user dari DB
  account_delete_user($kode_user);

  // Opsional: hapus file avatar di server
  if ($avatar_url) {
    // Contoh: /API_KMJ/images/user/USER_...png
    $filePath = __DIR__ . '/../../images/user/' . basename($avatar_url);
    if (file_exists($filePath)) {
      @unlink($filePath);
    }
  }

  send_json(true, 'Akun berhasil dihapus.', null, 200);

} catch (Exception $e) {
  send_json(false, 'Terjadi kesalahan server: ' . $e->getMessage(), null, 500);
}

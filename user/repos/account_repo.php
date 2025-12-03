<?php
// API_KMJ/user/repos/account_repo.php

require_once __DIR__ . '/../../shared/config.php';

/**
 * Ambil user lengkap termasuk password hash
 */
function account_get_user_with_password(string $kode_user): ?array
{
    global $conn;

    // ❗ Sesuaikan nama kolom password kalau berbeda (misal: user_password)
    $sql = "SELECT kode_user, email, password, avatar_url 
            FROM users 
            WHERE kode_user = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Gagal prepare statement: " . $conn->error);
    }

    $stmt->bind_param("s", $kode_user);
    $stmt->execute();

    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();

    $stmt->close();

    return $user ?: null;
}

/**
 * Hapus user dari DB
 */
function account_delete_user(string $kode_user): bool
{
    global $conn;

    $sql = "DELETE FROM users WHERE kode_user = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Gagal prepare statement: " . $conn->error);
    }

    $stmt->bind_param("s", $kode_user);
    $ok = $stmt->execute();
    if (!$ok) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception("Gagal eksekusi query DELETE: " . $err);
    }

    $stmt->close();
    return true;
}

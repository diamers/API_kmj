<?php
// API_KMJ/user/repos/profile_repo.php

require_once __DIR__ . '/../../shared/config.php';

/**
 * Ambil data user berdasarkan kode_user
 */
function profile_get_user(string $kode_user): ?array
{
    global $conn;

    $sql = "SELECT kode_user, full_name, email, no_telp, alamat, avatar_url 
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
 * Update data user (dinamis, termasuk avatar kalau ada)
 */
function profile_update_user(
    string $kode_user,
    string $full_name,
    ?string $no_telp,
    ?string $alamat,
    ?string $avatar_url = null
): bool {
    global $conn;

    $fields = [];
    $params = [];
    $types  = "";

    $fields[] = "full_name = ?";
    $params[] = $full_name;
    $types    .= "s";

    $fields[] = "no_telp = ?";
    $params[] = $no_telp;
    $types    .= "s";

    $fields[] = "alamat = ?";
    $params[] = $alamat;
    $types    .= "s";

    if ($avatar_url !== null) {
        $fields[] = "avatar_url = ?";
        $params[] = $avatar_url;
        $types    .= "s";
    }

    $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE kode_user = ?";
    $params[] = $kode_user;
    $types    .= "s";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Gagal prepare statement: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    if (!$ok) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception("Gagal eksekusi query: " . $err);
    }

    $stmt->close();
    return true;
}

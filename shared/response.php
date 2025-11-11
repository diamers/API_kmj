<?php
/**
 * Helper untuk kirim response JSON
 * Bisa dipakai di semua endpoint
 */

function ok($data = null, $meta = []) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        "success" => true,
        "data" => $data,
        "error" => null,
        "meta" => $meta
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function err($status = 400, $message = "Terjadi kesalahan", $code = null) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false,
        "data" => null,
        "error" => [
            "message" => $message,
            "code" => $code
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../shared/config.php';
require_once __DIR__ . '/../repos/cars_repo.php';

// ambil parameter (isi dengan kode_mobil)
$car1 = $_GET['car1'] ?? null;   // contoh: MB00000007
$car2 = $_GET['car2'] ?? null;   // contoh: MB00000008

if (!$car1 || !$car2) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Parameter car1 dan car2 wajib diisi.'
    ]);
    exit;
}
try {
    $cars = get_cars_for_perbandingan($car1, $car2);

    if (count($cars) < 2) {
        http_response_code(404);
        echo json_encode([
            'status' => false,
            'message' => 'Data mobil tidak lengkap untuk perbandingan.'
        ]);
        exit;
    }

    // ==========================
    // HIGHLIGHT
    // ==========================
    $highlight = [
        'warna_exterior' => [
            'label'  => 'Warna Exterior',
            'values' => [$cars[0]['warna_exterior'], $cars[1]['warna_exterior']],
        ],
        'warna_interior' => [
            'label'  => 'Warna Interior',
            'values' => [$cars[0]['warna_interior'], $cars[1]['warna_interior']],
        ],
    ];

    // ==========================
    // FINAL CHECK
    // ==========================
    $final_check = [
        'jenis_kendaraan' => [
            'label'  => 'Tipe Kendaraan',
            'values' => [$cars[0]['jenis_kendaraan'], $cars[1]['jenis_kendaraan']],
        ],
        'sistem_penggerak' => [
            'label'  => 'Sistem Penggerak',
            'values' => [$cars[0]['sistem_penggerak'], $cars[1]['sistem_penggerak']],
        ],
    ];

    // ==========================
    // FITUR (KESAMAAN & PERBEDAAN)
    // ==========================
    // ==========================
// FITUR (KESAMAAN & PERBEDAAN)
// ==========================
$fitur_rows = get_fitur_for_perbandingan($car1, $car2);

$similarities = [];
$differences  = [];

foreach ($fitur_rows as $row) {
    // pastikan bool: 1/0 → true/false
    $has1 = !empty($row['car1']);
    $has2 = !empty($row['car2']);

    // kalau dua-duanya NULL, skip aja
    if ($row['car1'] === null && $row['car2'] === null) {
        continue;
    }

    $item = [
        'label' => $row['nama_fitur'],
        'car1'  => $has1,
        'car2'  => $has2,
    ];

    if ($has1 === $has2) {
        // sama-sama punya fitur ⇒ Kesamaan
        if ($has1) {
            $similarities[] = $item;
        }
    } else {
        // cuma salah satu yang punya ⇒ Perbedaan
        $differences[] = $item;
    }
}


    echo json_encode([
        'status' => true,
        'message' => 'OK',
        'data' => [
            'cars'        => $cars,
            'highlight'   => $highlight,
            'final_check' => $final_check,
            'features'    => [
                'similarities' => $similarities,
                'differences'  => $differences,
            ],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ]);
}

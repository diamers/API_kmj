<?php
require "../shared/config.php";
header("Content-Type: application/json");

$kode_mobil = $_GET["kode_mobil"] ?? null;

if (!$kode_mobil) {
    echo json_encode([
        "success" => false,
        "message" => "kode_mobil tidak ditemukan"
    ]);
    exit;
}

try {
    // Ambil info utama
    $query = "
        SELECT *
        FROM mobil
        WHERE kode_mobil = '$kode_mobil'
    ";
    $mobil = $conn->query($query)->fetch_assoc();

    if (!$mobil) {
        echo json_encode([
            "success" => false,
            "message" => "Mobil tidak ditemukan"
        ]);
        exit;
    }

    // Ambil foto mobil
    $qFoto = "
        SELECT id_foto, tipe_foto, nama_file AS foto
        FROM mobil_foto 
        WHERE kode_mobil = '$kode_mobil'
    ";

    $fotoList = [];
    $resultFoto = $conn->query($qFoto);

    while ($f = $resultFoto->fetch_assoc()) {
        $f["foto"] = BASE_URL . "/images/mobil/" . $f["foto"];
        $fotoList[] = $f;
    }

    // Ambil fitur mobil
    $qFitur = "
        SELECT Id_fitur AS fitur_id
        FROM mobil_fitur
        WHERE kode_mobil = '$kode_mobil'
    ";

    $fiturList = [];
    $resultFitur = $conn->query($qFitur);

    while ($row = $resultFitur->fetch_assoc()) {
        $fiturList[] = intval($row["fitur_id"]);
    }

    echo json_encode([
        "code" => 200,
        "success" => true,
        "mobil" => $mobil,
        "foto" => $fotoList,
        "fitur" => $fiturList
    ]);

} catch (Exception $e) {
    echo json_encode([
        "code" => 500,
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>

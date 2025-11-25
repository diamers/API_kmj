<?php
require "../shared/config.php";
header("Content-Type: application/json");

$kode_mobil = $_GET["kode_mobil"] ?? null;

if (!$kode_mobil) {
    echo json_encode([
        "code" => 400,
        "message" => "kode_mobil tidak ditemukan"
    ]);
    exit;
}

try {
    // INFO UTAMA
    $query = "
        SELECT *
        FROM mobil
        WHERE kode_mobil = '$kode_mobil'
    ";
    $mobil = $conn->query($query)->fetch_assoc();

    if (!$mobil) {
        echo json_encode([
            "code" => 404,
            "message" => "Mobil tidak ditemukan"
        ]);
        exit;
    }

    // FOTO
    $qFoto = "
        SELECT id_foto, tipe_foto, nama_file AS foto
        FROM mobil_foto 
        WHERE kode_mobil = '$kode_mobil'
    ";

    $fotoList = [];
    $resultFoto = $conn->query($qFoto);

    while ($f = $resultFoto->fetch_assoc()) {
        $f["foto"] = BASE_URL . $f["foto"];
        $fotoList[] = $f;
    }

    // FITUR (JOIN → Ambil nama fitur)
    $qFitur = "
        SELECT 
            mf.Id_fitur AS id,
            f.nama_fitur AS nama
        FROM mobil_fitur mf
        JOIN fitur f ON mf.Id_fitur = f.id_fitur
        WHERE mf.kode_mobil = '$kode_mobil'
    ";

    $fiturList = [];
    $resultFitur = $conn->query($qFitur);

    while ($row = $resultFitur->fetch_assoc()) {
        $fiturList[] = [
            "id" => intval($row["id"]),
            "nama" => $row["nama"]
        ];
    }

    echo json_encode([
        "code" => 200,
        "mobil" => $mobil,
        "foto" => $fotoList,
        "fitur" => $fiturList
    ]);

} catch (Exception $e) {
    echo json_encode([
        "code" => 500,
        "message" => $e->getMessage()
    ]);
}
?>

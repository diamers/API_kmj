<?php
header('Content-Type: application/json');
require __DIR__ . "/../shared/config.php";

$kode_user = $_POST['kode_user'] ?? null;

if (!$kode_user) {
    http_response_code(400);
    echo json_encode(["code"=>400,"message"=>"kode_user wajib"]);
    exit;
}

if (!isset($_FILES["avatar_file"]["name"]) || $_FILES["avatar_file"]["error"] !== 0) {
    http_response_code(400);
    echo json_encode(["code"=>400,"message"=>"File avatar tidak ditemukan"]);
    exit;
}

$dir = __DIR__ . "/../../images/user/";
if (!file_exists($dir)) mkdir($dir, 0777, true);

$filename = time() . "_" . basename($_FILES["avatar_file"]["name"]);
$target = $dir . $filename;

if (!move_uploaded_file($_FILES["avatar_file"]["tmp_name"], $target)) {
    http_response_code(500);
    echo json_encode(["code"=>500,"message"=>"Gagal menyimpan file"]);
    exit;
}

// Resize / crop ke 512x512 (preserve alpha untuk PNG/WEBP)
list($width, $height, $type) = getimagesize($target);
switch ($type) {
    case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($target); break;
    case IMAGETYPE_PNG: $srcImg = imagecreatefrompng($target); break;
    case IMAGETYPE_WEBP: $srcImg = imagecreatefromwebp($target); break;
    default: $srcImg = null;
}

if ($srcImg) {
    $newSize = 512;
    // crop center square
    $min = min($width, $height);
    $srcX = intval(($width - $min) / 2);
    $srcY = intval(($height - $min) / 2);

    $dstImg = imagecreatetruecolor($newSize, $newSize);
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
    }
    imagecopyresampled($dstImg, $srcImg, 0,0, $srcX, $srcY, $newSize, $newSize, $min, $min);

    if ($type == IMAGETYPE_JPEG) imagejpeg($dstImg, $target, 90);
    if ($type == IMAGETYPE_PNG) imagepng($dstImg, $target);
    if ($type == IMAGETYPE_WEBP) imagewebp($dstImg, $target, 90);

    imagedestroy($srcImg);
    imagedestroy($dstImg);
}

$avatar_url = "/images/user/" . $filename;

$stmt = $conn->prepare("UPDATE users SET avatar_url = ? WHERE kode_user = ?");
$stmt->bind_param("ss", $avatar_url, $kode_user);
if ($stmt->execute()) {
    echo json_encode(["code"=>200,"message"=>"Avatar berhasil diupload","avatar_url"=>$avatar_url]);
} else {
    http_response_code(500);
    echo json_encode(["code"=>500,"message"=>"Gagal update DB: ".$conn->error]);
}
?>

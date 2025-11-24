<?php
header("Content-Type: application/json");
require __DIR__ . "/../shared/config.php";

$kode_user = $_POST['kode_user'] ?? null;
$full_name = $_POST['full_name'] ?? null;
$no_telp = $_POST['no_telp'] ?? null;
$alamat = $_POST['alamat'] ?? null;

if(!$kode_user){
    http_response_code(400);
    echo json_encode(["code"=>400,"message"=>"kode_user wajib"]);
    exit;
}

// === UPLOAD AVATAR (Optional) ===
$avatar_url = null;
if(isset($_FILES["avatar_file"]["name"]) && $_FILES["avatar_file"]["error"] === 0){
    $dir = __DIR__ . "/../../images/user/";
    if(!file_exists($dir)) mkdir($dir, 0777, true);

    $filename = "USER_" . $kode_user . "_" . time() . ".png";
    $target = $dir . $filename;

    move_uploaded_file($_FILES["avatar_file"]["tmp_name"], $target);

    // Resize + Crop ke 512x512
    list($w,$h,$type)=getimagesize($target);
    $src = ($type == IMAGETYPE_JPEG) ? imagecreatefromjpeg($target) :
           (($type == IMAGETYPE_PNG) ? imagecreatefrompng($target) :
           (($type == IMAGETYPE_WEBP) ? imagecreatefromwebp($target) : null));

    if($src){
        $size = 512;
        $min = min($w,$h);
        $cropX = ($w-$min)/2;
        $cropY = ($h-$min)/2;

        $dst = imagecreatetruecolor($size, $size);
        imagecopyresampled($dst,$src,0,0,$cropX,$cropY,$size,$size,$min,$min);
        imagepng($dst,$target);

        imagedestroy($src);
        imagedestroy($dst);
    }

    $avatar_url = "/images/user/".$filename;
}

// === BUILD SQL ===
$fields = [];
$params = [];
$types="";

if($full_name){ $fields[]="full_name=?"; $params[]=$full_name; $types.="s";}
if($no_telp){ $fields[]="no_telp=?"; $params[]=$no_telp; $types.="s";}
if($alamat){ $fields[]="alamat=?"; $params[]=$alamat; $types.="s";}
if($avatar_url){ $fields[]="avatar_url=?"; $params[]=$avatar_url; $types.="s";}

if(!$fields){
    echo json_encode(["code"=>200,"message"=>"Tidak ada yang diupdate"]);
    exit;
}

$sql = "UPDATE users SET ".implode(", ",$fields)." WHERE kode_user=?";
$params[]=$kode_user;
$types.="s";

$stmt=$conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if($stmt->execute()){
    echo json_encode([
        "code"=>200,
        "message"=>"Sukses update profile",
        "avatar_url"=>$avatar_url
    ]);
}else{
    http_response_code(500);
    echo json_encode(["code"=>500,"message"=>"DB Error: ".$conn->error]);
}

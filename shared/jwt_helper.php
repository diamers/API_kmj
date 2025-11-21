<?php
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

require_once __DIR__ . "/../vendor/autoload.php"; 

define('JWT_SECRET_KEY', 'ini_rahasia_sangat_aman'); 
define('JWT_ISSUER', 'kmjshowroom'); 
define('JWT_EXPIRATION', 3600); 

function generate_jwt($payload) {
    $issuedAt = time();
    $expire = $issuedAt + JWT_EXPIRATION;

    $token = array(
        "iss" => JWT_ISSUER,
        "iat" => $issuedAt,
        "exp" => $expire,
        "data" => $payload
    );

    return JWT::encode($token, JWT_SECRET_KEY, 'HS256');
}

function decode_jwt($jwt) {
    try {
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));
        return (array) $decoded->data;
    } catch (Exception $e) {
        return null;
    }
}
?>

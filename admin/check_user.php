<?php
require "config.php";

$query = $conn->query("SELECT COUNT(*) as user_count FROM users");
$result = $query->fetch_assoc();

echo json_encode([
    "success" => true,
    "user_count" => (int)$result['user_count']
]);
?>

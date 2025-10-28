<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(86400); 
    session_start();
}

$host = 'localhost';
$dbname = 'voting_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("❌ Koneksi database gagal: " . htmlspecialchars($e->getMessage()));
}

function generateCode($len = 6) {
    return substr(str_shuffle("23456789ABCDEFGHJKLMNPQRSTUVWXYZ"), 0, $len);
}
?>
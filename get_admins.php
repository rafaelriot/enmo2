<?php
require __DIR__ . '/api/vendor/autoload.php';
if (file_exists(__DIR__ . '/api/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/api');
    $dotenv->load();
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$dbname = $_ENV['DB_NAME'] ?? 'enmo2';

try {
    $db = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass);
    $stmt = $db->query("SELECT id, nombre, email, rol, estado FROM usuarios WHERE rol = 'administrador'");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($admins, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error BD: " . $e->getMessage();
}

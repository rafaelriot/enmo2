<?php
require_once __DIR__ . '/api/src/db.php';
use App\Db;

try {
    $dbObj = new Db();
    $db = $dbObj->connect();

    $stmt = $db->query("SHOW COLUMNS FROM pedidos WHERE Field = 'estado'");
    print_r($stmt->fetch(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

<?php
try {
    $db = new PDO("mysql:host=147.93.42.226;dbname=u980038333_enmo2;charset=utf8mb4", "u980038333_enmo2root", "S\$vTGIfnu1");
    $stmt = $db->query("SELECT id, nombre, email, rol, estado FROM usuarios WHERE rol = 'administrador'");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($admins, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error BD Remote: " . $e->getMessage();
}

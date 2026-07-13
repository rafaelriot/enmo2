<?php
require_once __DIR__ . '/api/src/db.php';
use App\Db;

try {
    $dbObj = new Db();
    $db = $dbObj->connect();

    $pedido_id = 3;
    $estado = 'asignado';
    $repartidor_id = 1;

    $sql = "UPDATE pedidos 
            SET estado = :estado, repartidor_id = :repartidor_id, updated_at = CURRENT_TIMESTAMP 
            WHERE id = :pedido_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':estado' => $estado,
        ':repartidor_id' => $repartidor_id,
        ':pedido_id' => $pedido_id
    ]);

    echo "SIMULACIÓN EXITOSA. Filas afectadas: " . $stmt->rowCount() . "\n";

} catch (Exception $e) {
    echo "ERROR EN SIMULACIÓN: " . $e->getMessage() . "\n";
}

<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Db;

require_once __DIR__ . '/../db.php';

$app->group('/api/admin', function ($group) {

    // Obtener estadísticas generales
    $group->get('/stats', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // 1. Pedidos Activos (pendiente, asignado, en_ruta)
            $stmtActivos = $db->query("SELECT COUNT(*) FROM pedidos WHERE estado IN ('pendiente', 'asignado', 'en_camino_recogida', 'en_ruta')");
            $pedidosActivos = (int)$stmtActivos->fetchColumn();

            // 2. Pedidos Pendientes por asignar
            $stmtPendientes = $db->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'");
            $pedidosPendientes = (int)$stmtPendientes->fetchColumn();

            // 3. Pedidos Asignados (espera de recogida)
            $stmtAsignados = $db->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'asignado'");
            $pedidosAsignados = (int)$stmtAsignados->fetchColumn();

            // 4. Pedidos En Ruta
            $stmtEnRutaPedidos = $db->query("SELECT COUNT(*) FROM pedidos WHERE estado IN ('en_camino_recogida','en_ruta')");
            $pedidosEnRuta = (int)$stmtEnRutaPedidos->fetchColumn();

            // 5. Pedidos Entregados Hoy
            $stmtEntregadosHoy = $db->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'entregado' AND DATE(updated_at) = CURDATE()");
            $pedidosEntregadosHoy = (int)$stmtEntregadosHoy->fetchColumn();

            // 6. Ingresos del Día (suma de precios de entregados hoy)
            $stmtIngresos = $db->query("SELECT SUM(precio) FROM pedidos WHERE estado = 'entregado' AND DATE(updated_at) = CURDATE()");
            $ingresosHoy = (float)($stmtIngresos->fetchColumn() ?? 0.0);

            // 7. Repartidores en Ruta (repartidores con al menos un pedido 'en_ruta')
            $stmtEnRuta = $db->query("SELECT COUNT(DISTINCT repartidor_id) FROM pedidos WHERE estado = 'en_ruta' AND repartidor_id IS NOT NULL");
            $repartidoresEnRuta = (int)$stmtEnRuta->fetchColumn();

            // 8. Repartidores Libres/Disponibles (usuarios con rol repartidor, estado activo, lat/lng != 0 y sin pedidos 'asignado' o 'en_ruta')
            $stmtLibres = $db->query("
                SELECT COUNT(*) FROM usuarios 
                WHERE rol = 'repartidor' 
                  AND estado = 'activo' 
                  AND latitud_actual != 0 
                  AND longitud_actual != 0
                  AND id NOT IN (
                      SELECT DISTINCT repartidor_id FROM pedidos 
                      WHERE estado IN ('asignado', 'en_camino_recogida', 'en_ruta') AND repartidor_id IS NOT NULL
                  )
            ");
            $repartidoresLibres = (int)$stmtLibres->fetchColumn();

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => [
                    "pedidos_activos" => $pedidosActivos,
                    "pedidos_pendientes" => $pedidosPendientes,
                    "pedidos_asignados" => $pedidosAsignados,
                    "pedidos_en_ruta" => $pedidosEnRuta,
                    "pedidos_entregados_hoy" => $pedidosEntregadosHoy,
                    "ingresos_hoy" => $ingresosHoy,
                    "repartidores_en_ruta" => $repartidoresEnRuta,
                    "repartidores_libres" => $repartidoresLibres
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error de base de datos: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Obtener todos los pedidos para el gestor y buscador del administrador
    $group->get('/pedidos', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "SELECT p.id, p.cliente_nombre, p.direccion_entrega, p.precio, p.estado, p.updated_at, 
                           u.nombre as repartidor_nombre, u.telefono as repartidor_telefono
                    FROM pedidos p
                    LEFT JOIN usuarios u ON p.repartidor_id = u.id
                    ORDER BY p.id DESC";
            $stmt = $db->query($sql);
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $pedidos
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error de base de datos: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Obtener actividad reciente (últimos 5 pedidos)
    $group->get('/actividad', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "SELECT p.id, p.cliente_nombre, p.estado, p.direccion_entrega, p.precio, p.updated_at, 
                           u.nombre as repartidor_nombre 
                    FROM pedidos p
                    LEFT JOIN usuarios u ON p.repartidor_id = u.id
                    ORDER BY p.updated_at DESC LIMIT 5";
            $stmt = $db->query($sql);
            $actividad = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $actividad
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error de base de datos: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Obtener todos los repartidores activos/online y sus ubicaciones (Optimizado sin N+1)
    $group->get('/repartidores-online', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Seleccionar repartidores que tengan coordenadas válidas y calcular su estado de ocupación en una sola consulta
            $sql = "SELECT u.id, u.nombre, u.email, u.telefono, u.latitud_actual AS latitud, u.longitud_actual AS longitud, u.estado,
                           (SELECT COUNT(*) FROM pedidos p WHERE p.repartidor_id = u.id AND p.estado IN ('asignado', 'en_camino_recogida', 'en_ruta')) > 0 AS ocupado
                    FROM usuarios u
                    WHERE u.rol = 'repartidor' AND u.estado = 'activo' AND u.latitud_actual != 0 AND u.longitud_actual != 0";
            $stmt = $db->query($sql);
            $repartidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Asegurar tipo booleano para el atributo ocupado
            foreach ($repartidores as &$rep) {
                $rep['ocupado'] = (bool)$rep['ocupado'];
            }

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $repartidores
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error de base de datos: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Obtener estadísticas analíticas para gráficos (GET /api/admin/chart-stats)
    $group->get('/chart-stats', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // 1. Ganancias diarias de la última semana (últimos 7 días)
            $semanaSql = "
                SELECT DATE(updated_at) as fecha, SUM(precio) as total 
                FROM pedidos 
                WHERE estado = 'entregado' 
                  AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY DATE(updated_at)
                ORDER BY DATE(updated_at) ASC
            ";
            $semanaStmt = $db->query($semanaSql);
            $semanaRaw = $semanaStmt->fetchAll(PDO::FETCH_ASSOC);

            // Poblar días vacíos para asegurar 7 días en el gráfico
            $ingresosSemana = [];
            for ($i = 6; $i >= 0; $i--) {
                $fechaStr = date('Y-m-d', strtotime("-$i days"));
                $ingresosSemana[$fechaStr] = 0.0;
            }
            foreach ($semanaRaw as $row) {
                if (isset($ingresosSemana[$row['fecha']])) {
                    $ingresosSemana[$row['fecha']] = (float)$row['total'];
                }
            }

            // 2. Conteo de pedidos agrupados por estado (todos los históricos)
            $estadosSql = "SELECT estado, COUNT(*) as cantidad FROM pedidos GROUP BY estado";
            $estadosStmt = $db->query($estadosSql);
            $estadosRaw = $estadosStmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => [
                    "semana_fechas" => array_keys($ingresosSemana),
                    "semana_ganancias" => array_values($ingresosSemana),
                    "estados" => $estadosRaw
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error de base de datos analítica: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Obtener historial completo de pedidos con filtros (GET /api/admin/historial)
    $group->get('/historial', function (Request $request, Response $response) {
        $queryParams = $request->getQueryParams();
        $fecha_inicio = $queryParams['fecha_inicio'] ?? null;
        $fecha_fin = $queryParams['fecha_fin'] ?? null;
        $estado = $queryParams['estado'] ?? null;

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "SELECT p.*, u.nombre as repartidor_nombre 
                    FROM pedidos p 
                    LEFT JOIN usuarios u ON p.repartidor_id = u.id";
            
            $conditions = [];
            $params = [];

            if (!empty($fecha_inicio)) {
                $conditions[] = "DATE(p.created_at) >= :fecha_inicio";
                $params[':fecha_inicio'] = $fecha_inicio;
            }
            if (!empty($fecha_fin)) {
                $conditions[] = "DATE(p.created_at) <= :fecha_fin";
                $params[':fecha_fin'] = $fecha_fin;
            }
            if (!empty($estado) && $estado !== 'todos') {
                $conditions[] = "p.estado = :estado";
                $params[':estado'] = $estado;
            }

            if (count($conditions) > 0) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }

            $sql .= " ORDER BY p.id DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $pedidos = $stmt->fetchAll();

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $pedidos
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al obtener historial: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });
});

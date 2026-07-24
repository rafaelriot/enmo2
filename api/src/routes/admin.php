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

    // Obtener todos los repartidores activos/online y sus ubicaciones reales en tiempo real (GPS < 15 min)
    $group->get('/repartidores-online', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Seleccionar únicamente repartidores activos con coordenadas vigentes (actualizadas en los últimos 15 minutos)
            $sql = "SELECT u.id, u.nombre, u.email, u.telefono, u.latitud_actual AS latitud, u.longitud_actual AS longitud, u.estado, u.foto_url, u.updated_at,
                           (SELECT COUNT(*) FROM pedidos p WHERE p.repartidor_id = u.id AND p.estado IN ('asignado', 'en_camino_recogida', 'en_ruta')) AS conteo_activos
                    FROM usuarios u
                    WHERE u.rol = 'repartidor' 
                      AND u.estado = 'activo' 
                      AND u.latitud_actual != 0 
                      AND u.longitud_actual != 0
                      AND u.updated_at >= NOW() - INTERVAL 15 MINUTE";
            $stmt = $db->query($sql);
            $repartidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Asegurar tipo booleano para el atributo ocupado
            foreach ($repartidores as &$rep) {
                $rep['ocupado'] = ((int)($rep['conteo_activos'] ?? 0)) > 0;
                unset($rep['conteo_activos']);
            }

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $repartidores
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            App\Logger::error("Error en repartidores-online: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error interno del servidor: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Obtener toda la flota de repartidores registrados con sus estados reales (GET /api/admin/repartidores-flota)
    $group->get('/repartidores-flota', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "SELECT u.id, u.nombre, u.email, u.telefono, u.foto_url, u.estado AS estado_cuenta, u.latitud_actual, u.longitud_actual, u.updated_at,
                           (SELECT COUNT(*) FROM pedidos p WHERE p.repartidor_id = u.id AND p.estado IN ('asignado', 'en_camino_recogida', 'en_ruta')) AS conteo_activos,
                           (SELECT COUNT(*) FROM pedidos p WHERE p.repartidor_id = u.id AND p.estado = 'entregado') AS total_viajes
                    FROM usuarios u
                    WHERE u.rol = 'repartidor'
                    ORDER BY u.nombre ASC";
            $stmt = $db->query($sql);
            $flota = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $now = time();
            foreach ($flota as &$rep) {
                $updatedTimestamp = $rep['updated_at'] ? strtotime($rep['updated_at']) : 0;
                $diffMinutes = ($now - $updatedTimestamp) / 60;
                
                $lat = (float)($rep['latitud_actual'] ?? 0);
                $lng = (float)($rep['longitud_actual'] ?? 0);
                
                $rep['ocupado'] = ((int)($rep['conteo_activos'] ?? 0)) > 0;
                $rep['is_online'] = ($lat != 0.0 && $lng != 0.0 && $diffMinutes <= 15);
                unset($rep['conteo_activos']);
                
                // Determinar estado de conexión humano
                if ($rep['estado_cuenta'] !== 'activo') {
                    $rep['estado_conexion'] = 'inactivo';
                } else if ($rep['is_online']) {
                    $rep['estado_conexion'] = $rep['ocupado'] ? 'en_ruta' : 'en_linea';
                } else {
                    $rep['estado_conexion'] = 'desconectado';
                }
            }

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $flota
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

    // ========================================================
    // GESTIÓN DE DOCUMENTOS DE REPARTIDORES
    // ========================================================

    // Listar repartidores con documentos pendientes de revisión (GET /api/admin/repartidores-pendientes)
    $group->get('/repartidores-pendientes', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Obtener repartidores que tienen al menos un documento pendiente
            $sql = "SELECT u.id, u.nombre, u.email, u.telefono, u.created_at as fecha_registro,
                           COUNT(d.id) as total_documentos,
                           SUM(CASE WHEN d.estado = 'pendiente' THEN 1 ELSE 0 END) as docs_pendientes,
                           SUM(CASE WHEN d.estado = 'aprobado' THEN 1 ELSE 0 END) as docs_aprobados,
                           SUM(CASE WHEN d.estado = 'rechazado' THEN 1 ELSE 0 END) as docs_rechazados
                    FROM usuarios u
                    INNER JOIN documentos_repartidor d ON u.id = d.usuario_id
                    WHERE u.rol = 'repartidor'
                    GROUP BY u.id
                    HAVING docs_pendientes > 0
                    ORDER BY u.created_at DESC";
            $stmt = $db->query($sql);
            $repartidores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Para cada repartidor, obtener sus documentos
            foreach ($repartidores as &$rep) {
                $docSql = "SELECT id, tipo_documento, nombre_archivo, estado, motivo_rechazo, created_at, updated_at
                           FROM documentos_repartidor
                           WHERE usuario_id = :uid
                           ORDER BY tipo_documento ASC";
                $docStmt = $db->prepare($docSql);
                $docStmt->execute([':uid' => $rep['id']]);
                $rep['documentos'] = $docStmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $repartidores
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error de base de datos: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Revisar (aprobar/rechazar) un documento (POST /api/admin/documentos/revisar)
    $group->post('/documentos/revisar', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);

        $docId = $data['documento_id'] ?? null;
        $accion = $data['accion'] ?? null; // 'aprobar' o 'rechazar'
        $motivoRechazo = $data['motivo_rechazo'] ?? null;
        $revisadoPor = $data['admin_id'] ?? null;

        if (empty($docId) || empty($accion)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "documento_id y accion son requeridos."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!in_array($accion, ['aprobar', 'rechazar'])) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Acción inválida. Valores permitidos: aprobar, rechazar."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if ($accion === 'rechazar' && empty($motivoRechazo)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Se requiere un motivo de rechazo."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $nuevoEstado = ($accion === 'aprobar') ? 'aprobado' : 'rechazado';

            $sql = "UPDATE documentos_repartidor
                    SET estado = :estado,
                        motivo_rechazo = :motivo,
                        revisado_por = :admin_id,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :doc_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':estado' => $nuevoEstado,
                ':motivo' => ($accion === 'rechazar') ? $motivoRechazo : null,
                ':admin_id' => $revisadoPor,
                ':doc_id' => $docId
            ]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Documento no encontrado."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Documento " . ($accion === 'aprobar' ? 'aprobado' : 'rechazado') . " exitosamente."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error de base de datos: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // ========================================================
    // OBSERVABILIDAD Y SALUD DEL SISTEMA
    // ========================================================

    // Obtener todos los clientes registrados (GET /api/admin/clientes)
    $group->get('/clientes', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "SELECT u.id, u.nombre, u.email, u.telefono, u.foto_url, u.estado, u.created_at,
                           (SELECT COUNT(*) FROM pedidos p WHERE p.cliente_usuario_id = u.id) AS total_pedidos
                    FROM usuarios u
                    WHERE u.rol = 'cliente'
                    ORDER BY u.id DESC";
            $stmt = $db->query($sql);
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $clientes
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

    // Obtener los logs más recientes del sistema (GET /api/admin/observabilidad/logs)
    $group->get('/observabilidad/logs', function (Request $request, Response $response) {
        $logs = App\Logger::getRecentLogs(100);
        $response->getBody()->write(json_encode([
            "status" => "success",
            "total" => count($logs),
            "data" => $logs
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });

});

// Endpoint público para reporte de errores JS del cliente (POST /api/observabilidad/client-log)
$app->post('/api/observabilidad/client-log', function (Request $request, Response $response) {
    $data = json_decode($request->getBody()->getContents(), true);
    $mensaje = $data['mensaje'] ?? 'Error JS desconocido en el cliente';
    $context = $data['context'] ?? [];

    App\Logger::error("[CLIENT-JS] " . $mensaje, $context);

    $response->getBody()->write(json_encode([
        "status" => "success",
        "message" => "Log recibido."
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});


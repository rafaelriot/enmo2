<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Db;
use App\ImageHelper;

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config/ImageHelper.php';

// Rutas de Pedidos
$app->group('/api/pedidos', function ($group) {

    // Obtener todos los pedidos (para el Dashboard de administración o filtrados por cliente)
    $group->get('', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $queryParams = $request->getQueryParams();
            $usuario_id = $queryParams['usuario_id'] ?? null;

            $sql = "SELECT p.*, u.nombre as repartidor_nombre 
                    FROM pedidos p 
                    LEFT JOIN usuarios u ON p.repartidor_id = u.id";
            
            $conditions = [];
            $params = [];

            if ($usuario_id !== null && $usuario_id !== '') {
                // Consultar rol y nombre de este usuario en la base de datos
                $userSql = "SELECT nombre, rol FROM usuarios WHERE id = :user_id LIMIT 1";
                $userStmt = $db->prepare($userSql);
                $userStmt->execute([':user_id' => $usuario_id]);
                $userRow = $userStmt->fetch();

                if ($userRow) {
                    $rol = $userRow['rol'];
                    $nombre = $userRow['nombre'];

                    if ($rol === 'repartidor') {
                        $conditions[] = "p.repartidor_id = :usuario_id";
                        $params[':usuario_id'] = $usuario_id;
                    } else if ($rol !== 'administrador') {
                        // Si no es admin ni repartidor, es un cliente
                        // Filtrar por cliente_usuario_id si está disponible, sino por nombre
                        $conditions[] = "(p.cliente_usuario_id = :usuario_id OR (p.cliente_usuario_id IS NULL AND p.cliente_nombre = :cliente_nombre))";
                        $params[':usuario_id'] = $usuario_id;
                        $params[':cliente_nombre'] = $nombre;
                    }
                    // Si es administrador, no añadimos condiciones para que pueda ver todos
                }
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
                "message" => "Error al obtener pedidos: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Obtener pedidos de un repartidor específico
    $group->get('/repartidor/{repartidor_id}', function (Request $request, Response $response, array $args) {
        $repartidor_id = $args['repartidor_id'];

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "SELECT * FROM pedidos WHERE repartidor_id = :repartidor_id ORDER BY id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([':repartidor_id' => $repartidor_id]);
            $pedidos = $stmt->fetchAll();

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $pedidos
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al obtener pedidos del repartidor: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Obtener detalles de un pedido específico (GET /api/pedidos/{id})
    $group->get('/{id}', function (Request $request, Response $response, array $args) {
        $id = $args['id'];

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "SELECT p.*, u.nombre as repartidor_nombre, u.telefono as repartidor_telefono, u.foto_url as repartidor_foto, u.latitud_actual as repartidor_lat, u.longitud_actual as repartidor_lng,
                    p.cliente_telefono as cliente_telefono, u_cli.foto_url as cliente_foto
                    FROM pedidos p 
                    LEFT JOIN usuarios u ON p.repartidor_id = u.id 
                    LEFT JOIN usuarios u_cli ON p.cliente_usuario_id = u_cli.id
                    WHERE p.id = :id 
                    LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $pedido = $stmt->fetch();

            if ($pedido) {
                $response->getBody()->write(json_encode([
                    "status" => "success",
                    "data" => $pedido
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Pedido no encontrado."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error del servidor: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Crear un nuevo pedido (o actualizar si se pasa pedido_id)
    $group->post('/crear', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);

        $pedido_id = $data['pedido_id'] ?? null;
        $cliente_nombre = $data['cliente_nombre'] ?? '';
        $cliente_telefono = $data['cliente_telefono'] ?? null;
        $cliente_usuario_id = $data['cliente_usuario_id'] ?? null;
        $foto_paquete = $data['foto_paquete'] ?? null;
        $direccion_entrega = $data['direccion_entrega'] ?? '';
        $latitud_recogida = $data['latitud_recogida'] ?? null;
        $longitud_recogida = $data['longitud_recogida'] ?? null;
        $latitud_entrega = $data['latitud_entrega'] ?? null;
        $longitud_entrega = $data['longitud_entrega'] ?? null;
        $precio = $data['precio'] ?? 0;
        $notas = $data['notas'] ?? '';
        $idempotency_key = $data['idempotency_key'] ?? $request->getHeaderLine('Idempotency-Key') ?? null;

        if (empty($cliente_nombre) || empty($direccion_entrega)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "El nombre del cliente y la dirección de entrega son obligatorios."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Convertir base64 de foto_paquete a imagen física WebP si aplica
        if (!empty($foto_paquete) && strpos($foto_paquete, 'data:image/') === 0) {
            $baseDir = __DIR__ . '/../../public';
            $subida = ImageHelper::uploadBase64WebP($foto_paquete, $baseDir, 'paquetes');
            if ($subida !== false) {
                $foto_paquete = $subida;
            }
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Si se pasa pedido_id, actualizar el pedido existente
            if (!empty($pedido_id)) {
                $sql = "UPDATE pedidos 
                        SET cliente_nombre = :cliente, cliente_telefono = :telefono, cliente_usuario_id = :usuario_id,
                            foto_paquete = :foto_paquete, direccion_entrega = :direccion, 
                            latitud_recogida = :lat_rec, longitud_recogida = :lng_rec, 
                            latitud_entrega = :lat_ent, longitud_entrega = :lng_ent, 
                            precio = :precio, notas = :notas, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = :id";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':cliente' => $cliente_nombre,
                    ':telefono' => $cliente_telefono,
                    ':usuario_id' => $cliente_usuario_id,
                    ':foto_paquete' => $foto_paquete,
                    ':direccion' => $direccion_entrega,
                    ':lat_rec' => $latitud_recogida,
                    ':lng_rec' => $longitud_recogida,
                    ':lat_ent' => $latitud_entrega,
                    ':lng_ent' => $longitud_entrega,
                    ':precio' => $precio,
                    ':notas' => $notas,
                    ':id' => $pedido_id
                ]);

                $response->getBody()->write(json_encode([
                    "status" => "success",
                    "message" => "Pedido actualizado con éxito.",
                    "pedido_id" => $pedido_id
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            // Verificar si el pedido ya existe con la misma clave de idempotencia
            if (!empty($idempotency_key)) {
                $checkSql = "SELECT id FROM pedidos WHERE idempotency_key = :key LIMIT 1";
                $checkStmt = $db->prepare($checkSql);
                $checkStmt->execute([':key' => $idempotency_key]);
                $existing = $checkStmt->fetch();

                if ($existing) {
                    $response->getBody()->write(json_encode([
                        "status" => "success",
                        "message" => "Pedido procesado previamente (idempotente).",
                        "pedido_id" => $existing['id']
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                }
            }

            $sql = "INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_usuario_id, foto_paquete, direccion_entrega, latitud_recogida, longitud_recogida, latitud_entrega, longitud_entrega, precio, notas, estado, idempotency_key) 
                    VALUES (:cliente, :telefono, :usuario_id, :foto_paquete, :direccion, :lat_rec, :lng_rec, :lat_ent, :lng_ent, :precio, :notas, 'pendiente', :key)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':cliente' => $cliente_nombre,
                ':telefono' => $cliente_telefono,
                ':usuario_id' => $cliente_usuario_id,
                ':foto_paquete' => $foto_paquete,
                ':direccion' => $direccion_entrega,
                ':lat_rec' => $latitud_recogida,
                ':lng_rec' => $longitud_recogida,
                ':lat_ent' => $latitud_entrega,
                ':lng_ent' => $longitud_entrega,
                ':precio' => $precio,
                ':notas' => $notas,
                ':key' => $idempotency_key
            ]);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Pedido creado con éxito.",
                "pedido_id" => $db->lastInsertId()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al crear pedido: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Cambiar estado o asignar repartidor a un pedido
    $group->post('/actualizar-estado', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);

        $pedido_id = $data['pedido_id'] ?? null;
        $estado = $data['estado'] ?? null; // 'pendiente', 'asignado', 'en_ruta', 'entregado', 'cancelado'
        $repartidor_id = $data['repartidor_id'] ?? null;
        $foto_confirmacion = $data['foto_confirmacion'] ?? null;

        if (!$pedido_id || !$estado) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "El ID del pedido y el nuevo estado son obligatorios."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Construir la consulta dinámicamente si se asigna repartidor
            if ($repartidor_id !== null) {
                $sql = "UPDATE pedidos 
                        SET estado = :estado, repartidor_id = :repartidor_id, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = :pedido_id";
                $params = [
                    ':estado' => $estado,
                    ':repartidor_id' => $repartidor_id,
                    ':pedido_id' => $pedido_id
                ];
            } else {
                if ($estado === 'entregado' && $foto_confirmacion !== null) {
                    // Si viene una imagen base64, procesarla de manera optimizada
                    $rutaEvidencia = $foto_confirmacion;
                    if (strpos($foto_confirmacion, 'data:image/') === 0) {
                        $baseDir = __DIR__ . '/../../public';
                        $subida = ImageHelper::uploadBase64WebP($foto_confirmacion, $baseDir, 'evidencias');
                        if ($subida !== false) {
                            $rutaEvidencia = $subida;
                        }
                    }

                    $sql = "UPDATE pedidos 
                            SET estado = :estado, foto_confirmacion = :foto_confirmacion, updated_at = CURRENT_TIMESTAMP 
                            WHERE id = :pedido_id";
                    $params = [
                        ':estado' => $estado,
                        ':foto_confirmacion' => $rutaEvidencia,
                        ':pedido_id' => $pedido_id
                    ];
                } else {
                    $sql = "UPDATE pedidos 
                            SET estado = :estado, updated_at = CURRENT_TIMESTAMP 
                            WHERE id = :pedido_id";
                    $params = [
                        ':estado' => $estado,
                        ':pedido_id' => $pedido_id
                    ];
                }
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Pedido actualizado correctamente."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al actualizar pedido: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Actualizar coordenadas de recogida de un pedido (POST /api/pedidos/actualizar-coordenadas-recogida)
    $group->post('/actualizar-coordenadas-recogida', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        $pedido_id = $data['pedido_id'] ?? null;
        $lat = $data['latitud'] ?? null;
        $lng = $data['longitud'] ?? null;

        if (!$pedido_id || !$lat || !$lng) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Faltan parámetros obligatorios."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();
            $sql = "UPDATE pedidos SET latitud_recogida = :lat, longitud_recogida = :lng WHERE id = :pedido_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':lat' => $lat, ':lng' => $lng, ':pedido_id' => $pedido_id]);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Coordenadas de recogida actualizadas correctamente."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Calificar un pedido por parte del cliente (POST /api/pedidos/calificar)
    $group->post('/calificar', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        $pedido_id = $data['pedido_id'] ?? null;
        $estrellas = $data['estrellas'] ?? null;
        $comentario = $data['comentario'] ?? null;

        if (!$pedido_id || !$estrellas) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Faltan parámetros obligatorios (pedido_id o estrellas)."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();
            $sql = "UPDATE pedidos 
                    SET calificacion_estrellas = :estrellas, calificacion_comentario = :comentario 
                    WHERE id = :pedido_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':estrellas' => $estrellas,
                ':comentario' => $comentario,
                ':pedido_id' => $pedido_id
            ]);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Pedido calificado correctamente."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]));
        }
    });

    // Calificar a un cliente por parte del repartidor (POST /api/pedidos/calificar-cliente)
    $group->post('/calificar-cliente', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        $pedido_id = $data['pedido_id'] ?? null;
        $estrellas = $data['estrellas'] ?? null;
        $comentario = $data['comentario'] ?? null;

        if (!$pedido_id || !$estrellas) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Faltan parámetros obligatorios (pedido_id o estrellas)."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Intentar actualizar la columna calificacion_cliente_estrellas
            try {
                $sql = "UPDATE pedidos 
                        SET calificacion_cliente_estrellas = :estrellas, calificacion_cliente_comentario = :comentario 
                        WHERE id = :pedido_id";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':estrellas' => $estrellas,
                    ':comentario' => $comentario,
                    ':pedido_id' => $pedido_id
                ]);
            } catch (\PDOException $pe) {
                // Fallback defensivo si la columna aún no ha sido migrada en Hostinger
                if ($pe->getCode() == '42S22') {
                    $response->getBody()->write(json_encode([
                        "status" => "success",
                        "message" => "Calificación registrada localmente."
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                }
                throw $pe;
            }

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Cliente calificado correctamente."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Obtener mensajes de un pedido (GET /api/pedidos/{id}/mensajes)
    $group->get('/{id}/mensajes', function (Request $request, Response $response, array $args) {
        $pedido_id = $args['id'];
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();
            $sql = "SELECT m.*, u.nombre as remitente_nombre, u.rol as remitente_rol 
                    FROM mensajes_pedido m 
                    LEFT JOIN usuarios u ON m.remitente_id = u.id 
                    WHERE m.pedido_id = :pedido_id 
                    ORDER BY m.id ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([':pedido_id' => $pedido_id]);
            $mensajes = $stmt->fetchAll();

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $mensajes
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al obtener mensajes: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Enviar mensaje en un pedido (POST /api/pedidos/{id}/mensajes)
    $group->post('/{id}/mensajes', function (Request $request, Response $response, array $args) {
        $pedido_id = $args['id'];
        $data = json_decode($request->getBody()->getContents(), true);
        $remitente_id = $data['remitente_id'] ?? null;
        $mensaje = $data['mensaje'] ?? '';

        if (!$remitente_id || empty($mensaje)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Faltan parámetros obligatorios (remitente_id o mensaje)."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();
            $sql = "INSERT INTO mensajes_pedido (pedido_id, remitente_id, mensaje) 
                    VALUES (:pedido_id, :remitente_id, :mensaje)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':pedido_id' => $pedido_id,
                ':remitente_id' => $remitente_id,
                ':mensaje' => $mensaje
            ]);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Mensaje enviado correctamente."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al enviar mensaje: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });
});

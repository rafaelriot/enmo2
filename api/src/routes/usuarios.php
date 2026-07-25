<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Db;
use App\Mail;
use Firebase\JWT\JWT;

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config/Mail.php';
require_once __DIR__ . '/../config/Logger.php';

// Rutas de Usuarios y Repartidores
$app->group('/api/usuarios', function ($group) {

    // Iniciar Sesión (POST /api/usuarios/login)
    $group->post('/login', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "El correo/teléfono y la contraseña son requeridos."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Buscar usuario por correo electrónico o teléfono
            try {
                $sql = "SELECT id, nombre, email, foto_url, password, telefono, rol, estado 
                        FROM usuarios 
                        WHERE email = :email OR telefono = :telefono 
                        LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->execute([':email' => $email, ':telefono' => $email]);
                $user = $stmt->fetch();
            } catch (\PDOException $pe) {
                $sql = "SELECT id, nombre, email, password, telefono, rol, estado 
                        FROM usuarios 
                        WHERE email = :email OR telefono = :telefono 
                        LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->execute([':email' => $email, ':telefono' => $email]);
                $user = $stmt->fetch();
            }

            // Verificar la contraseña (usando password_verify para passwords hashed)
            if ($user && password_verify($password, $user['password'])) {
                // Quitar password de la respuesta
                unset($user['password']);

                // Generar Token JWT
                $secretKey = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? 'super-secret-key-change-in-production-123456');
                $payload = [
                    "iss" => "enmo2-api",
                    "aud" => "enmo2-app",
                    "iat" => time(),
                    "exp" => time() + (24 * 60 * 60), // Expira en 24 horas
                    "id" => $user['id'],
                    "nombre" => $user['nombre'],
                    "email" => $user['email'],
                    "rol" => $user['rol']
                ];
                $jwt = Firebase\JWT\JWT::encode($payload, $secretKey, 'HS256');

                $response->getBody()->write(json_encode([
                    "status" => "success",
                    "message" => "Inicio de sesión exitoso.",
                    "usuario" => $user,
                    "token" => $jwt
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Credenciales incorrectas."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error de base de datos: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Iniciar Sesión o Registrar con Google (POST /api/usuarios/google-oauth)
    $group->post('/google-oauth', function (Request $request, Response $response) {
        try {
            $rawBody = $request->getBody()->getContents();
            $data = !empty($rawBody) ? json_decode($rawBody, true) : [];
            
            $email = trim($data['email'] ?? '');
            $nombre = trim($data['nombre'] ?? '');
            $google_id = trim($data['google_id'] ?? '');
            $foto_url = trim($data['foto_url'] ?? '');

            if (empty($email) || empty($nombre)) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "El correo y nombre del perfil de Google son requeridos."
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $dbObj = new Db();
            $db = $dbObj->connect();

            // 1. Buscar si el correo ya existe
            $hasFotoCol = true;
            try {
                $sql = "SELECT id, nombre, email, foto_url, telefono, rol, estado FROM usuarios WHERE email = :email LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
            } catch (\PDOException $pe) {
                // Fallback si la columna foto_url aún no existe en el esquema de la BD
                $hasFotoCol = false;
                $sql = "SELECT id, nombre, email, telefono, rol, estado FROM usuarios WHERE email = :email LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
            }

            if ($user) {
                // Si existe la columna foto_url y viene una nueva foto de Google, actualizarla
                if ($hasFotoCol && !empty($foto_url) && ($user['foto_url'] ?? '') !== $foto_url) {
                    try {
                        $updateFotoSql = "UPDATE usuarios SET foto_url = :foto_url WHERE id = :id";
                        $updateFotoStmt = $db->prepare($updateFotoSql);
                        $updateFotoStmt->execute([':foto_url' => $foto_url, ':id' => $user['id']]);
                        $user['foto_url'] = $foto_url;
                    } catch (\Throwable $te) {}
                }

                // Generar Token JWT para Google login
                $secretKey = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? 'super-secret-key-change-in-production-123456');
                $payload = [
                    "iss" => "enmo2-api",
                    "aud" => "enmo2-app",
                    "iat" => time(),
                    "exp" => time() + (24 * 60 * 60),
                    "id" => $user['id'],
                    "nombre" => $user['nombre'],
                    "email" => $user['email'],
                    "rol" => !empty($user['rol']) ? $user['rol'] : 'cliente'
                ];
                $jwt = \Firebase\JWT\JWT::encode($payload, $secretKey, 'HS256');

                $response->getBody()->write(json_encode([
                    "status" => "success",
                    "message" => "Inicio de sesión con Google exitoso.",
                    "usuario" => $user,
                    "token" => $jwt
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            // 2. Si no existe, crear la cuenta como 'cliente'
            $telefonoDummy = '9990000000';
            $passwordDummy = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);

            try {
                if ($hasFotoCol) {
                    $insertSql = "INSERT INTO usuarios (nombre, email, foto_url, telefono, password, rol, estado)
                                  VALUES (:nombre, :email, :foto_url, :telefono, :password, 'cliente', 'activo')";
                    $insertStmt = $db->prepare($insertSql);
                    $insertStmt->execute([
                        ':nombre' => $nombre,
                        ':email' => $email,
                        ':foto_url' => !empty($foto_url) ? $foto_url : null,
                        ':telefono' => $telefonoDummy,
                        ':password' => $passwordDummy
                    ]);
                } else {
                    throw new \Exception("No foto_url column");
                }
            } catch (\Throwable $ie) {
                $hasFotoCol = false;
                $insertSql = "INSERT INTO usuarios (nombre, email, telefono, password, rol, estado)
                              VALUES (:nombre, :email, :telefono, :password, 'cliente', 'activo')";
                $insertStmt = $db->prepare($insertSql);
                $insertStmt->execute([
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':telefono' => $telefonoDummy,
                    ':password' => $passwordDummy
                ]);
            }

            $nuevoId = $db->lastInsertId();
            
            // Generar JWT para el usuario recién registrado
            $secretKey = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? 'super-secret-key-change-in-production-123456');
            $payload = [
                "iss" => "enmo2-api",
                "aud" => "enmo2-app",
                "iat" => time(),
                "exp" => time() + (24 * 60 * 60),
                "id" => $nuevoId,
                "nombre" => $nombre,
                "email" => $email,
                "rol" => "cliente"
            ];
            $jwt = \Firebase\JWT\JWT::encode($payload, $secretKey, 'HS256');

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Cuenta creada e inicio de sesión exitoso.",
                "usuario" => [
                    "id" => (int)$nuevoId,
                    "nombre" => $nombre,
                    "email" => $email,
                    "foto_url" => $hasFotoCol ? (!empty($foto_url) ? $foto_url : null) : null,
                    "telefono" => $telefonoDummy,
                    "rol" => "cliente",
                    "estado" => "activo"
                ],
                "token" => $jwt
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

        } catch (\Throwable $e) {
            try {
                if (class_exists('App\Logger')) {
                    App\Logger::error("Error en Google OAuth: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
            } catch (\Throwable $logEx) {}

            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al autenticar con Google: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Actualizar Ubicación GPS (POST /api/usuarios/location)
    $group->post('/location', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);

        $repartidor_id = $data['repartidor_id'] ?? null;
        $lat = $data['latitud'] ?? null;
        $lng = $data['longitud'] ?? null;

        if (!$repartidor_id || !$lat || !$lng) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "ID del repartidor, latitud y longitud son obligatorios."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Actualizar ubicación en la tabla usuarios
            $sql = "UPDATE usuarios 
                    SET latitud_actual = :lat, longitud_actual = :lng, ultima_conexion = CURRENT_TIMESTAMP 
                    WHERE id = :id AND rol = 'repartidor'";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':lat' => $lat,
                ':lng' => $lng,
                ':id' => $repartidor_id
            ]);

            // Guardar en el historial para auditoría de trayecto
            $sqlHistorial = "INSERT INTO historial_ubicaciones (repartidor_id, latitud, longitud) 
                             VALUES (:repartidor_id, :lat, :lng)";
            $stmtHistorial = $db->prepare($sqlHistorial);
            $stmtHistorial->execute([
                ':repartidor_id' => $repartidor_id,
                ':lat' => $lat,
                ':lng' => $lng
            ]);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Ubicación actualizada correctamente."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al guardar ubicación: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Listar Repartidores Disponibles (GET /api/usuarios/repartidores)
    $group->get('/repartidores', function (Request $request, Response $response) {
        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "SELECT id, nombre, email, telefono, latitud_actual, longitud_actual, estado, ultima_conexion 
                    FROM usuarios 
                    WHERE rol = 'repartidor' AND estado = 'activo'";
            
            $stmt = $db->query($sql);
            $repartidores = $stmt->fetchAll();

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $repartidores
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al obtener repartidores: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Actualizar datos del usuario (POST /api/usuarios/update)
    $group->post('/update', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);

        $id = $data['id'] ?? null;
        $nombre = $data['nombre'] ?? '';
        $email = $data['email'] ?? '';
        $telefono = $data['telefono'] ?? '';

        if (!$id || empty($nombre) || empty($email) || empty($telefono)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "El ID, nombre, correo y teléfono son obligatorios."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "UPDATE usuarios 
                    SET nombre = :nombre, email = :email, telefono = :telefono 
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':nombre' => $nombre,
                ':email' => $email,
                ':telefono' => $telefono,
                ':id' => $id
            ]);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Perfil actualizado correctamente."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al actualizar perfil: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Registrar nuevo usuario (cliente) (POST /api/usuarios/registro)
    $group->post('/registro', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);

        $nombre   = trim($data['nombre']   ?? '');
        $email    = trim($data['email']    ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $password = $data['password']      ?? '';
        $rol      = $data['rol']           ?? 'cliente'; // 'cliente' o 'repartidor'

        // Validación básica
        if (empty($nombre) || empty($email) || empty($password) || empty($telefono)) {
            $response->getBody()->write(json_encode([
                "status"  => "error",
                "message" => "Nombre, correo, teléfono y contraseña son obligatorios."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode([
                "status"  => "error",
                "message" => "El formato del correo electrónico no es válido."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (strlen($password) < 6) {
            $response->getBody()->write(json_encode([
                "status"  => "error",
                "message" => "La contraseña debe tener al menos 6 caracteres."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Solo roles permitidos desde el registro público
        if (!in_array($rol, ['cliente', 'repartidor'])) {
            $rol = 'cliente';
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Verificar que el email no esté en uso (sin importar el rol)
            $checkSql = "SELECT id, rol FROM usuarios WHERE email = :email LIMIT 1";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->execute([':email' => $email]);
            $userExist = $checkStmt->fetch();
            if ($userExist) {
                $response->getBody()->write(json_encode([
                    "status"  => "error",
                    "message" => "Este correo ya está registrado como " . ucfirst($userExist['rol']) . ". No es posible duplicar cuentas."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            // Verificar que el teléfono no esté en uso (sin importar el rol)
            $checkTelSql = "SELECT id, rol FROM usuarios WHERE telefono = :telefono LIMIT 1";
            $checkTelStmt = $db->prepare($checkTelSql);
            $checkTelStmt->execute([':telefono' => $telefono]);
            $telExist = $checkTelStmt->fetch();
            if ($telExist) {
                $response->getBody()->write(json_encode([
                    "status"  => "error",
                    "message" => "Este teléfono ya está registrado como " . ucfirst($telExist['rol']) . ". No es posible duplicar cuentas."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            // Hashear contraseña
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insertar usuario
            $insertSql = "INSERT INTO usuarios (nombre, email, telefono, password, rol, estado)
                          VALUES (:nombre, :email, :telefono, :password, :rol, 'activo')";
            $insertStmt = $db->prepare($insertSql);
            $insertStmt->execute([
                ':nombre'   => $nombre,
                ':email'    => $email,
                ':telefono' => $telefono,
                ':password' => $hashedPassword,
                ':rol'      => $rol
            ]);

            $nuevoId = $db->lastInsertId();

            $response->getBody()->write(json_encode([
                "status"  => "success",
                "message" => "Cuenta creada exitosamente.",
                "usuario" => [
                    "id"       => (int)$nuevoId,
                    "nombre"   => $nombre,
                    "email"    => $email,
                    "telefono" => $telefono,
                    "rol"      => $rol,
                    "estado"   => "activo"
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status"  => "error",
                "message" => "Error del servidor: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });







    // Solicitar Código de Recuperación (POST /api/usuarios/recuperar-solicitar)
    $group->post('/recuperar-solicitar', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        $email = trim($data['email'] ?? '');

        if (empty($email)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "El correo electrónico es obligatorio."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Buscar si el usuario existe
            $sql = "SELECT id, nombre FROM usuarios WHERE email = :email LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "El correo electrónico no se encuentra registrado."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Generar token numérico aleatorio de 6 dígitos
            $token = strval(rand(100000, 999999));
            $expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Guardar token en base de datos
            $updateSql = "UPDATE usuarios 
                          SET token_recuperacion = :token, token_expiracion = :expiracion 
                          WHERE email = :email";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([
                ':token' => $token,
                ':expiracion' => $expiracion,
                ':email' => $email
            ]);

            // Plantilla HTML del correo con estética premium enMo2
            $subject = "enMo2 - Código de verificación de contraseña";
            $bodyHtml = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 12px; background-color: #f9f9fc;'>
                    <h2 style='color: #006e25; font-size: 24px; font-weight: bold; margin-bottom: 20px;'>Recuperación de Contraseña</h2>
                    <p style='color: #3e4a3c; font-size: 16px; line-height: 1.5;'>Hola <strong>{$user['nombre']}</strong>,</p>
                    <p style='color: #3e4a3c; font-size: 16px; line-height: 1.5;'>Has solicitado restablecer tu contraseña en enMo2. Utiliza el siguiente código de verificación temporal de un solo uso:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <span style='display: inline-block; background-color: #006e25; color: #ffffff; font-family: monospace; font-size: 32px; font-weight: bold; letter-spacing: 6px; padding: 12px 30px; border-radius: 8px;'>{$token}</span>
                    </div>
                    <p style='color: #ae3200; font-size: 12px; font-weight: bold;'>* Este código tiene una validez máxima de 15 minutos.</p>
                    <hr style='border: none; border-top: 1px dashed #bdcab9; margin: 30px 0;'>
                    <p style='color: #6e7b6b; font-size: 12px; text-align: center;'>© 2026 enMo2 Logística de Velocidad. Por favor, no respondas a este mensaje.</p>
                </div>
            ";
            $altText = "Tu código de verificación de enMo2 es: {$token}. Válido por 15 minutos.";

            // Enviar correo electrónico real
            $enviado = Mail::send($email, $user['nombre'], $subject, $bodyHtml, $altText);

            if ($enviado) {
                $response->getBody()->write(json_encode([
                    "status" => "success",
                    "message" => "Se ha enviado un código de verificación real a tu correo electrónico."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Error al enviar el correo. Por favor, intente más tarde."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error de base de datos: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Cambiar la Contraseña con el Código (POST /api/usuarios/recuperar-restablecer)
    $group->post('/recuperar-restablecer', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        $email = trim($data['email'] ?? '');
        $codigo = trim($data['codigo'] ?? '');
        $newPassword = $data['password'] ?? '';

        if (empty($email) || empty($codigo) || empty($newPassword)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Todos los campos (correo, código y contraseña) son obligatorios."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (strlen($newPassword) < 6) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "La nueva contraseña debe tener al menos 6 caracteres."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // Buscar el token almacenado y su fecha de expiración
            $sql = "SELECT token_recuperacion, token_expiracion FROM usuarios WHERE email = :email LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "El usuario no existe."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Validar código
            if (empty($user['token_recuperacion']) || $user['token_recuperacion'] !== $codigo) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "El código de verificación ingresado no es válido."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Validar expiración
            $now = date('Y-m-d H:i:s');
            if ($now > $user['token_expiracion']) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "El código de verificación ha expirado (límite de 15 minutos)."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Actualizar la contraseña y limpiar tokens
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateSql = "UPDATE usuarios 
                          SET password = :password, token_recuperacion = NULL, token_expiracion = NULL 
                          WHERE email = :email";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([
                ':password' => $hashedPassword,
                ':email' => $email
            ]);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Contraseña restablecida exitosamente."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al actualizar contraseña: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // ========================================================
    // DOCUMENTOS DE REPARTIDOR
    // ========================================================

    // Subir un documento (POST /api/usuarios/documentos/upload)
    $group->post('/documentos/upload', function (Request $request, Response $response) {
        $uploadedFiles = $request->getUploadedFiles();
        $params = $request->getParsedBody() ?? [];

        // Fallback: si getParsedBody está vacío, leer de $_POST
        if (empty($params)) {
            $params = $_POST;
        }

        $usuarioId = $params['usuario_id'] ?? null;
        $tipoDocumento = $params['tipo_documento'] ?? null;

        // Validar campos requeridos
        if (empty($usuarioId) || empty($tipoDocumento)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "usuario_id y tipo_documento son requeridos."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validar tipo de documento
        $tiposPermitidos = ['ine', 'licencia', 'seguro'];
        if (!in_array($tipoDocumento, $tiposPermitidos)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Tipo de documento inválido. Valores permitidos: ine, licencia, seguro."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Obtener el archivo
        $archivo = $uploadedFiles['archivo'] ?? null;
        if (!$archivo || $archivo->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "No se recibió ningún archivo o hubo un error al subirlo."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validar tamaño (máximo 3 MB)
        $maxSize = 3 * 1024 * 1024; // 3 MB
        if ($archivo->getSize() > $maxSize) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "El archivo excede el tamaño máximo permitido de 3 MB."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validar tipo MIME
        $mimePermitidos = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $clientMediaType = $archivo->getClientMediaType();
        if (!in_array($clientMediaType, $mimePermitidos)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Formato de archivo no permitido. Solo se aceptan JPG, PNG, WEBP y PDF."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Generar nombre de archivo único
        $extension = pathinfo($archivo->getClientFilename(), PATHINFO_EXTENSION);
        $nombreArchivo = $tipoDocumento . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

        // Crear directorio si no existe
        $dirBase = __DIR__ . '/../../uploads/documentos/' . $usuarioId;
        if (!is_dir($dirBase)) {
            mkdir($dirBase, 0755, true);
        }

        $rutaCompleta = $dirBase . '/' . $nombreArchivo;
        $rutaRelativa = 'uploads/documentos/' . $usuarioId . '/' . $nombreArchivo;

        try {
            // Mover archivo
            $archivo->moveTo($rutaCompleta);

            $dbObj = new Db();
            $db = $dbObj->connect();

            // Eliminar documento anterior del mismo tipo si existe (limpiar archivo viejo)
            $oldSql = "SELECT ruta_archivo FROM documentos_repartidor WHERE usuario_id = :uid AND tipo_documento = :tipo";
            $oldStmt = $db->prepare($oldSql);
            $oldStmt->execute([':uid' => $usuarioId, ':tipo' => $tipoDocumento]);
            $oldDoc = $oldStmt->fetch();
            if ($oldDoc && !empty($oldDoc['ruta_archivo'])) {
                $oldPath = __DIR__ . '/../../' . $oldDoc['ruta_archivo'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            // INSERT o UPDATE
            $sql = "INSERT INTO documentos_repartidor (usuario_id, tipo_documento, nombre_archivo, ruta_archivo, estado, motivo_rechazo, revisado_por)
                    VALUES (:uid, :tipo, :nombre, :ruta, 'pendiente', NULL, NULL)
                    ON DUPLICATE KEY UPDATE
                        nombre_archivo = VALUES(nombre_archivo),
                        ruta_archivo = VALUES(ruta_archivo),
                        estado = 'pendiente',
                        motivo_rechazo = NULL,
                        revisado_por = NULL,
                        updated_at = CURRENT_TIMESTAMP";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':uid' => $usuarioId,
                ':tipo' => $tipoDocumento,
                ':nombre' => $archivo->getClientFilename(),
                ':ruta' => $rutaRelativa
            ]);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Documento subido exitosamente.",
                "documento" => [
                    "tipo" => $tipoDocumento,
                    "nombre" => $archivo->getClientFilename(),
                    "estado" => "pendiente"
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al guardar el documento: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Obtener documentos de un repartidor (GET /api/usuarios/documentos/{usuario_id})
    $group->get('/documentos/{usuario_id}', function (Request $request, Response $response, array $args) {
        $usuarioId = $args['usuario_id'];

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "SELECT id, tipo_documento, nombre_archivo, estado, motivo_rechazo, created_at, updated_at
                    FROM documentos_repartidor
                    WHERE usuario_id = :uid
                    ORDER BY tipo_documento ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([':uid' => $usuarioId]);
            $documentos = $stmt->fetchAll();

            // Determinar estado general de verificación
            $tiposRequeridos = ['ine', 'licencia', 'seguro'];
            $tiposSubidos = array_column($documentos, 'tipo_documento');
            $todosSubidos = count(array_intersect($tiposRequeridos, $tiposSubidos)) === count($tiposRequeridos);
            $todosAprobados = $todosSubidos && count(array_filter($documentos, fn($d) => $d['estado'] !== 'aprobado')) === 0;
            $algunoRechazado = count(array_filter($documentos, fn($d) => $d['estado'] === 'rechazado')) > 0;

            $estadoGeneral = 'sin_documentos';
            if ($todosAprobados) {
                $estadoGeneral = 'verificado';
            } elseif ($algunoRechazado) {
                $estadoGeneral = 'requiere_accion';
            } elseif (count($documentos) > 0) {
                $estadoGeneral = 'en_revision';
            }

            $response->getBody()->write(json_encode([
                "status" => "success",
                "estado_general" => $estadoGeneral,
                "documentos" => $documentos
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al obtener documentos: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Servir archivo de documento (GET /api/usuarios/documentos/archivo/{doc_id})
    $group->get('/documentos/archivo/{doc_id}', function (Request $request, Response $response, array $args) {
        $docId = $args['doc_id'];

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            $sql = "SELECT ruta_archivo, nombre_archivo FROM documentos_repartidor WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $docId]);
            $doc = $stmt->fetch();

            if (!$doc) {
                $response->getBody()->write(json_encode(["status" => "error", "message" => "Documento no encontrado."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $filePath = __DIR__ . '/../../' . $doc['ruta_archivo'];
            if (!file_exists($filePath)) {
                $response->getBody()->write(json_encode(["status" => "error", "message" => "Archivo no encontrado en el servidor."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $mimeType = mime_content_type($filePath);
            $stream = new \Slim\Psr7\Stream(fopen($filePath, 'r'));

            return $response
                ->withHeader('Content-Type', $mimeType)
                ->withHeader('Content-Disposition', 'inline; filename="' . $doc['nombre_archivo'] . '"')
                ->withBody($stream);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

});

<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Db;
use App\Mail;

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config/Mail.php';

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
            $sql = "SELECT id, nombre, email, password, telefono, rol, estado 
                    FROM usuarios 
                    WHERE email = :email OR telefono = :telefono 
                    LIMIT 1";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':email' => $email,
                ':telefono' => $email
            ]);
            $user = $stmt->fetch();

            // Verificar la contraseña (usando password_verify para passwords hashed)
            if ($user && password_verify($password, $user['password'])) {
                // Quitar password de la respuesta
                unset($user['password']);

                $response->getBody()->write(json_encode([
                    "status" => "success",
                    "message" => "Inicio de sesión exitoso.",
                    "usuario" => $user
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
                "message" => "Error del servidor: " . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Iniciar Sesión o Registrar con Google (POST /api/usuarios/google-oauth)
    $group->post('/google-oauth', function (Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        
        $email = trim($data['email'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $google_id = trim($data['google_id'] ?? '');

        if (empty($email) || empty($nombre)) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "El correo y nombre del perfil de Google son requeridos."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $dbObj = new Db();
            $db = $dbObj->connect();

            // 1. Buscar si el correo ya existe
            $sql = "SELECT id, nombre, email, telefono, rol, estado FROM usuarios WHERE email = :email LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Si existe el usuario, iniciar sesión automáticamente
                $response->getBody()->write(json_encode([
                    "status" => "success",
                    "message" => "Inicio de sesión con Google exitoso.",
                    "usuario" => $user
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            // 2. Si no existe, crear la cuenta como 'cliente'
            // Generar un número de teléfono dummy o dejarlo vacío (se puede actualizar después)
            $telefonoDummy = '9990000000';
            $passwordDummy = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);

            $insertSql = "INSERT INTO usuarios (nombre, email, telefono, password, rol, estado)
                          VALUES (:nombre, :email, :telefono, :password, 'cliente', 'activo')";
            $insertStmt = $db->prepare($insertSql);
            $insertStmt->execute([
                ':nombre' => $nombre,
                ':email' => $email,
                ':telefono' => $telefonoDummy,
                ':password' => $passwordDummy
            ]);

            $nuevoId = $db->lastInsertId();

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Registro con Google exitoso.",
                "usuario" => [
                    "id" => (int)$nuevoId,
                    "nombre" => $nombre,
                    "email" => $email,
                    "telefono" => $telefonoDummy,
                    "rol" => "cliente",
                    "estado" => "activo"
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Error al autenticar con Google: " . $e->getMessage()
            ]));
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

            // Verificar que el email no esté en uso
            $checkSql = "SELECT id FROM usuarios WHERE email = :email LIMIT 1";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->execute([':email' => $email]);
            if ($checkStmt->fetch()) {
                $response->getBody()->write(json_encode([
                    "status"  => "error",
                    "message" => "Este correo electrónico ya está registrado."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            // Verificar que el teléfono no esté en uso
            $checkTelSql = "SELECT id FROM usuarios WHERE telefono = :telefono LIMIT 1";
            $checkTelStmt = $db->prepare($checkTelSql);
            $checkTelStmt->execute([':telefono' => $telefono]);
            if ($checkTelStmt->fetch()) {
                $response->getBody()->write(json_encode([
                    "status"  => "error",
                    "message" => "Este número de teléfono ya está registrado."
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

            // Verificar que el email no esté en uso
            $checkSql = "SELECT id FROM usuarios WHERE email = :email LIMIT 1";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->execute([':email' => $email]);
            if ($checkStmt->fetch()) {
                $response->getBody()->write(json_encode([
                    "status"  => "error",
                    "message" => "Este correo electrónico ya está registrado."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            // Verificar que el teléfono no esté en uso
            $checkTelSql = "SELECT id FROM usuarios WHERE telefono = :telefono LIMIT 1";
            $checkTelStmt = $db->prepare($checkTelSql);
            $checkTelStmt->execute([':telefono' => $telefono]);
            if ($checkTelStmt->fetch()) {
                $response->getBody()->write(json_encode([
                    "status"  => "error",
                    "message" => "Este número de teléfono ya está registrado."
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

});

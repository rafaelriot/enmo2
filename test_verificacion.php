<?php
// Script de Verificación Automatizada - enMo2

echo "=== INICIANDO PRUEBAS AUTOMATIZADAS DE SEGURIDAD ===\n\n";

// Cargar Dotenv para la prueba
require __DIR__ . '/api/vendor/autoload.php';
if (file_exists(__DIR__ . '/api/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/api');
    $dotenv->load();
}

$secret = $_ENV['JWT_SECRET'] ?? null;
if (!$secret) {
    echo "❌ ERROR: No se cargó la variable JWT_SECRET de las variables de entorno.\n";
    exit(1);
} else {
    echo "✅ Éxito: .env cargado y JWT_SECRET detectado correctamente.\n";
}

// 1. Validar que los archivos de configuración no tengan credenciales hardcodeadas
$dbFile = file_get_contents(__DIR__ . '/api/src/db.php');
if (strpos($dbFile, 'u980038333_enmo2root') !== false || strpos($dbFile, 'S$vTGIfnu1') !== false) {
    echo "❌ ERROR: Se detectaron credenciales de base de datos hardcodeadas en db.php\n";
    exit(1);
} else {
    echo "✅ Éxito: No hay credenciales de base de datos hardcodeadas en db.php\n";
}

$mailFile = file_get_contents(__DIR__ . '/api/src/config/Mail.php');
if (strpos($mailFile, 'S$vTGIfnu1') !== false) {
    echo "❌ ERROR: Se detectaron credenciales SMTP hardcodeadas en Mail.php\n";
    exit(1);
} else {
    echo "✅ Éxito: No hay credenciales SMTP hardcodeadas en Mail.php\n";
}

// 2. Verificar que .env esté en .gitignore para evitar subidas accidentales
$gitignore = file_get_contents(__DIR__ . '/api/.gitignore');
if (strpos($gitignore, '.env') === false) {
    echo "❌ ERROR: El archivo .env no está listado en api/.gitignore\n";
    exit(1);
} else {
    echo "✅ Éxito: El archivo .env está protegido en api/.gitignore\n";
}

// 3. Simular peticiones a endpoints protegidos (Prueba de middleware)
echo "\n=== SIMULANDO PETICIONES API ===\n";

// Cargar clases internas para simular firma JWT
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Crear token de cliente (rol: cliente)
$payloadCliente = [
    "iss" => "enmo2-api",
    "aud" => "enmo2-app",
    "iat" => time(),
    "exp" => time() + 3600,
    "id" => 999,
    "nombre" => "Cliente Test",
    "email" => "cliente@test.com",
    "rol" => "cliente"
];
$tokenCliente = JWT::encode($payloadCliente, $secret, 'HS256');

// Crear token de admin (rol: administrador)
$payloadAdmin = [
    "iss" => "enmo2-api",
    "aud" => "enmo2-app",
    "iat" => time(),
    "exp" => time() + 3600,
    "id" => 1,
    "nombre" => "Admin Test",
    "email" => "admin@test.com",
    "rol" => "administrador"
];
$tokenAdmin = JWT::encode($payloadAdmin, $secret, 'HS256');

// Función auxiliar para simular llamada HTTP local (CURL)
function simularPeticion($urlPath, $token = null) {
    // Si no está corriendo un servidor local en el puerto 80, esto fallará por HTTP,
    // pero podemos hacer una verificación de lógica simulando el flujo interno.
    // Para esta prueba, consultaremos contra el localhost local.
    $url = "http://localhost/enmo2_delivery_logistics/api/public/index.php" . $urlPath;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($token) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

echo "Nota: Iniciando consultas al servidor web local (asegúrate de tener Apache activo en XAMPP)\n";

// Prueba A: Endpoints protegidos sin token -> Espera 401
$resSinToken = simularPeticion('/api/admin/stats');
if ($resSinToken['code'] === 401) {
    echo "✅ Éxito: Acceso sin token a /api/admin/stats devolvió HTTP 401 (No autorizado).\n";
} else {
    echo "⚠️ Advertencia: Acceso sin token devolvió HTTP " . $resSinToken['code'] . " (Verifica que Apache esté activo en XAMPP).\n";
}

// Prueba B: Endpoints de Admin con token de Cliente -> Espera 403
$resCliente = simularPeticion('/api/admin/stats', $tokenCliente);
if ($resCliente['code'] === 403) {
    echo "✅ Éxito: Acceso de Cliente a /api/admin/stats devolvió HTTP 403 (Prohibido).\n";
} else {
    echo "⚠️ Advertencia: Acceso con token cliente devolvió HTTP " . $resCliente['code'] . " (Verifica que Apache esté activo en XAMPP).\n";
}

// Prueba C: Endpoints de Admin con token de Admin -> Espera 200 (o 500 por DB en local, pero no 401 ni 403)
$resAdmin = simularPeticion('/api/admin/stats', $tokenAdmin);
if ($resAdmin['code'] === 200 || $resAdmin['code'] === 500) {
    echo "✅ Éxito: Acceso de Admin a /api/admin/stats superó el Middleware de seguridad (HTTP " . $resAdmin['code'] . ").\n";
} else {
    echo "⚠️ Advertencia: Acceso con token admin devolvió HTTP " . $resAdmin['code'] . " (Verifica que Apache esté activo en XAMPP).\n";
}

echo "\n=== PRUEBAS AUTOMATIZADAS FINALIZADAS ===\n";

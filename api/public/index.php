<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Slim\Factory\AppFactory;

// Cargar el autoloader de Composer
require __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno desde el archivo .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Crear la aplicación de Slim
$app = AppFactory::create();

// Detectar y configurar el Base Path de forma dinámica para XAMPP y Hostinger
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = str_replace('/api/public/index.php', '', $scriptName);
$app->setBasePath($basePath);

// Agregar Middleware de Enrutamiento
$app->addRoutingMiddleware();

// Middleware para manejo de errores (útil para desarrollo)
// Cambia a (false, false, false) en producción en Hostinger
$envProd = isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production';
$errorMiddleware = $app->addErrorMiddleware(!$envProd, !$envProd, !$envProd);

// Habilitar CORS para permitir peticiones desde la app móvil en otros subdominios/puertos
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Cargar las rutas de la aplicación
require __DIR__ . '/../src/JwtAuthMiddleware.php';
require __DIR__ . '/../src/routes/usuarios.php';
require __DIR__ . '/../src/routes/pedidos.php';
require __DIR__ . '/../src/routes/admin.php';

// Aplicar Middleware de Autenticación y Autorización de Administrador a las rutas de Administración
$app->add(function ($request, $handler) use ($app) {
    // Si la petición va dirigida al grupo de /api/admin, verificar que sea Administrador
    $path = $request->getUri()->getPath();
    if (strpos($path, '/api/admin') !== false) {
        $jwtMiddleware = new App\JwtAuthMiddleware(['administrador']);
        return $jwtMiddleware($request, $handler);
    }
    // Si la petición es de actualización de estado de pedido, requerir token (administrador o repartidor)
    if (strpos($path, '/api/pedidos/actualizar-estado') !== false) {
        $jwtMiddleware = new App\JwtAuthMiddleware(['administrador', 'repartidor']);
        return $jwtMiddleware($request, $handler);
    }
    // Si la petición es para crear pedido, requerir token (administrador o cliente)
    if (strpos($path, '/api/pedidos/crear') !== false) {
        $jwtMiddleware = new App\JwtAuthMiddleware(['administrador', 'cliente']);
        return $jwtMiddleware($request, $handler);
    }
    
    return $handler->handle($request);
});

// Ruta de bienvenida / Test de estado
$app->get('/api[/]', function ($request, $response, $args) {
    $response->getBody()->write(json_encode([
        "status" => "success",
        "message" => "¡Bienvenido a la API de enMo2 Logística de Velocidad!"
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Ejecutar la aplicación
$app->run();

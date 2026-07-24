<?php
// En producción: no mostrar errores PHP como HTML (rompe respuestas JSON)
// Los errores se registran en el log del servidor
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

use Slim\Factory\AppFactory;

// Cargar el autoloader de Composer
require __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno desde el archivo .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} else {
    // Auto-configurar para Hostinger si no existe .env
    $isHostinger = strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'hostinger') !== false
                || strpos($_SERVER['SERVER_NAME'] ?? '', 'hostingersite.com') !== false;
    if ($isHostinger) {
        $hostingerEnv = [
            'DB_HOST' => 'localhost',
            'DB_USER' => 'u980038333_enmo2root',
            'DB_PASS' => 'S$vTGIfnu1',
            'DB_NAME' => 'u980038333_enmo2',
            'SMTP_HOST' => 'smtp.hostinger.com',
            'SMTP_USER' => 'recuperacion@enmo2.com',
            'SMTP_PASS' => 'S$vTGIfnu1',
            'SMTP_PORT' => '465',
            'JWT_SECRET' => '9f7ef95a32b9845d4e12e753ac5631bb538d3fcad7023190df031d2ba5b106ae',
        ];
        foreach ($hostingerEnv as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
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
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Correlation-ID')
        ->withHeader('Access-Control-Expose-Headers', 'X-Correlation-ID, X-Response-Time-Ms')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Cargar e incorporar Middleware de Observabilidad Integral
require_once __DIR__ . '/../src/config/Logger.php';
require_once __DIR__ . '/../src/ObservabilityMiddleware.php';
$app->add(new App\ObservabilityMiddleware());

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

// Health-check endpoint para diagnóstico rápido
$app->get('/api/health', function ($request, $response, $args) {
    $checks = [
        'php_version' => phpversion(),
        'env_loaded' => !empty(getenv('DB_HOST')) || !empty($_ENV['DB_HOST']),
        'db_host' => getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'NOT SET'),
        'db_name' => getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'NOT SET'),
        'db_user' => getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'NOT SET'),
        'vendor_exists' => file_exists(__DIR__ . '/../vendor/autoload.php'),
    ];

    // Intentar conexión a BD
    try {
        $dbObj = new App\Db();
        $db = $dbObj->connect();
        $checks['db_connection'] = 'OK';
    } catch (\Exception $e) {
        $checks['db_connection'] = 'FAILED: ' . $e->getMessage();
    }

    $allOk = $checks['env_loaded'] && $checks['vendor_exists'] && $checks['db_connection'] === 'OK';

    $response->getBody()->write(json_encode([
        'status' => $allOk ? 'ok' : 'error',
        'checks' => $checks
    ], JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($allOk ? 200 : 500);
});

// Ejecutar la aplicación
$app->run();

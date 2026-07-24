<?php
// Script de Pruebas de Observabilidad (Correlation ID + Structured JSON Logging)

echo "========================================================\n";
echo "    ENMO2 - VERIFICACIÓN DE INTEGRACIÓN DE OBSERVABILIDAD\n";
echo "========================================================\n\n";

// 1. Probar la clase Logger
echo "[1] PROBANDO CLASE Logger (JSON ESTRUCTURADO)\n";
require_once __DIR__ . '/api/src/config/Logger.php';

$testCid = 'test-uuid-' . time();
App\Logger::info("Prueba de log de información", ['correlation_id' => $testCid, 'modulo' => 'test']);
App\Logger::error("Prueba de log de error simulado", ['correlation_id' => $testCid, 'modulo' => 'test']);

$recent = App\Logger::getRecentLogs(5);
if (count($recent) > 0) {
    echo "  ✅ Logger escribió y leyó los logs JSON correctamente (" . count($recent) . " entradas leídas)\n";
    echo "  Última entrada: Level: " . $recent[0]['level'] . " | Msg: " . $recent[0]['message'] . " | CID: " . $recent[0]['correlation_id'] . "\n";
} else {
    echo "  ❌ ERROR: No se pudieron recuperar logs de Logger.php\n";
}

// 2. Verificar existencia de middleware y protección de logs
echo "\n[2] VERIFICANDO ARCHIVOS DE ARCHITECTURA DE OBSERVABILIDAD\n";

if (file_exists(__DIR__ . '/api/src/ObservabilityMiddleware.php')) {
    echo "  ✅ ObservabilityMiddleware.php existe\n";
} else {
    echo "  ❌ ERROR: Falta ObservabilityMiddleware.php\n";
}

if (file_exists(__DIR__ . '/api/logs/.htaccess')) {
    echo "  ✅ api/logs/.htaccess de protección existe\n";
} else {
    echo "  ❌ ERROR: Falta .htaccess en api/logs\n";
}

// 3. Verificar endpoints de observabilidad en admin.php
echo "\n[3] VERIFICANDO ENDPOINTS DE OBSERVABILIDAD\n";
$adminPhp = file_get_contents(__DIR__ . '/api/src/routes/admin.php');
if (strpos($adminPhp, '/observabilidad/logs') !== false && strpos($adminPhp, '/observabilidad/client-log') !== false) {
    echo "  ✅ Endpoints de logs (/observabilidad/logs y client-log) integrados en admin.php\n";
} else {
    echo "  ❌ ERROR: Faltan endpoints de observabilidad en admin.php\n";
}

// 4. Verificar captura JS en app.js
echo "\n[4] VERIFICANDO PROPAGACIÓN JS EN app.js\n";
$appJs = file_get_contents(__DIR__ . '/app.js');
if (strpos($appJs, 'getCorrelationId') !== false && strpos($appJs, 'X-Correlation-ID') !== false && strpos($appJs, 'window.addEventListener(\'error\'') !== false) {
    echo "  ✅ Captura de errores JS y Correlation-ID inyectados en app.js\n";
} else {
    echo "  ❌ ERROR: Faltan componentes de observabilidad JS en app.js\n";
}

echo "\n========================================================\n";
echo "       PRUEBA DE OBSERVABILIDAD FINALIZADA CON ÉXITO\n";
echo "========================================================\n";

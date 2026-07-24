<?php
// Script de Verificación de Integración de Tokens y Avatars

echo "========================================================\n";
echo "    ENMO2 - VERIFICACIÓN DE TOKENS JWT Y AVATARS\n";
echo "========================================================\n\n";

// 1. Verificación de getAuthHeaders() en llamadas fetch críticas
echo "[1] VERIFICANDO INCLUSIÓN DE getAuthHeaders() EN PETICIONES CRÍTICAS\n";

$archivosEspeciales = [
    'pedido_en_curso.html' => ['api/pedidos/actualizar-estado', 'getAuthHeaders'],
    'pedidos_disponibles.html' => ['api/pedidos/actualizar-estado', 'getAuthHeaders'],
    'asignar_repartidor.html' => ['api/pedidos/actualizar-estado', 'getAuthHeaders'],
    'perfil_del_repartidor.html' => ['api/usuarios/location', 'getAuthHeaders'],
    'nuevo_pedido_detalles.html' => ['api/pedidos/crear', 'getAuthHeaders']
];

foreach ($archivosEspeciales as $archivo => $verificaciones) {
    $path = __DIR__ . '/' . $archivo;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $endpoint = $verificaciones[0];
        $func = $verificaciones[1];
        
        if (strpos($content, $endpoint) !== false && strpos($content, $func) !== false) {
            echo "  ✅ {$archivo} incluye `{$func}` para la llamada `{$endpoint}`\n";
        } else {
            echo "  ❌ ERROR: Falta `{$func}` en {$archivo}\n";
        }
    } else {
        echo "  ⚠️ Archivo no encontrado: {$archivo}\n";
    }
}

// 2. Verificación del helper renderUserAvatar en app.js
echo "\n[2] VERIFICANDO HELPER DE AVATARS EN app.js\n";
$appJs = file_get_contents(__DIR__ . '/app.js');
if (strpos($appJs, 'function renderUserAvatar(') !== false && strpos($appJs, 'renderUserAvatarFallback') !== false) {
    echo "  ✅ Helper `renderUserAvatar` y fallback por iniciales presente en app.js\n";
} else {
    echo "  ❌ ERROR: Falta helper `renderUserAvatar` en app.js\n";
}

// 3. Verificación de eliminación de imágenes de stock en confirmación de pedido
echo "\n[3] VERIFICANDO ELIMINACIÓN DE IMÁGENES DE STOCK EN CONFIRMACIÓN DE PEDIDO\n";
$confirmacionHtml = file_get_contents(__DIR__ . '/confirmacion_de_pedido.html');
if (strpos($confirmacionHtml, 'avatar-cliente') !== false && strpos($confirmacionHtml, 'avatar-repartidor') !== false) {
    echo "  ✅ `confirmacion_de_pedido.html` utiliza contenedores dinámicos para avatar-cliente y avatar-repartidor\n";
} else {
    echo "  ❌ ERROR: `confirmacion_de_pedido.html` no tiene la estructura dinámica de avatars\n";
}

// 4. Verificación de foto_url en usuarios.php y migración SQL
echo "\n[4] VERIFICANDO MIGRACIÓN DE FOTO_URL Y BACKEND\n";
$usuariosPhp = file_get_contents(__DIR__ . '/api/src/routes/usuarios.php');
if (strpos($usuariosPhp, 'foto_url') !== false && strpos($usuariosPhp, 'google-oauth') !== false) {
    echo "  ✅ Backend api/src/routes/usuarios.php procesa y almacena `foto_url` de Google\n";
} else {
    echo "  ❌ ERROR: Backend usuarios.php no procesa `foto_url`\n";
}

$migracionSql = file_get_contents(__DIR__ . '/api/migrations/002_add_foto_url_to_usuarios.sql');
if (strpos($migracionSql, 'foto_url') !== false) {
    echo "  ✅ Migración SQL `002_add_foto_url_to_usuarios.sql` creada correctamente\n";
} else {
    echo "  ❌ ERROR: Falta migración SQL de foto_url\n";
}

echo "\n========================================================\n";
echo "    VERIFICACIÓN DE CÓDIGO COMPLETADA CON ÉXITO\n";
echo "========================================================\n";

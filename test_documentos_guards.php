<?php
// Script de Pruebas Automatizadas - enMo2 (Documentos & Guards)

echo "========================================================\n";
echo "    ENMO2 - PRUEBAS AUTOMATIZADAS DE DOCUMENTOS Y GUARDS\n";
echo "========================================================\n\n";

$baseUrl = "https://mediumspringgreen-caribou-619682.hostingersite.com";

// 1. Verificación de archivos estáticos y guard de roles en HTML
echo "[1] VERIFICANDO GUARDS DE SEGURIDAD EN PÁGINAS DE ADMIN\n";
$paginasAdmin = [
    'dashboard_principal.html',
    'administracion_de_pedidos_y_flota.html',
    'panel_de_administracion_repartidores.html',
    'asignar_repartidor.html',
    'mapa_en_vivo.html',
    'historial_pedidos.html'
];

$todasConGuard = true;
foreach ($paginasAdmin as $pag) {
    $path = __DIR__ . '/' . $pag;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        if (strpos($content, 'usr.rol === \'repartidor\'') !== false && strpos($content, 'usr.rol === \'cliente\'') !== false) {
            echo "  ✅ Guard verificado en: {$pag}\n";
        } else {
            echo "  ❌ ERROR: Faltan las reglas de guard en: {$pag}\n";
            $todasConGuard = false;
        }
    } else {
        echo "  ⚠️ Archivo no encontrado: {$pag}\n";
    }
}

// 2. Verificación de sintaxis y endpoints en PHP
echo "\n[2] VERIFICANDO SINTAXIS Y ESTRUCTURA DE RUTAS\n";

$rutasUsuarios = file_get_contents(__DIR__ . '/api/src/routes/usuarios.php');
if (strpos($rutasUsuarios, '/documentos/upload') !== false && strpos($rutasUsuarios, '/documentos/{usuario_id}') !== false) {
    echo "  ✅ Endpoints de repartidor (/upload, /{usuario_id}) presentes en usuarios.php\n";
} else {
    echo "  ❌ ERROR: Faltan endpoints de documentos en usuarios.php\n";
}

$rutasAdmin = file_get_contents(__DIR__ . '/api/src/routes/admin.php');
if (strpos($rutasAdmin, '/repartidores-pendientes') !== false && strpos($rutasAdmin, '/documentos/revisar') !== false) {
    echo "  ✅ Endpoints de administración (/repartidores-pendientes, /documentos/revisar) presentes en admin.php\n";
} else {
    echo "  ❌ ERROR: Faltan endpoints de administración de documentos en admin.php\n";
}

// 3. Verificación de la migración SQL
echo "\n[3] VERIFICANDO ARCHIVO DE MIGRACIÓN DE BD\n";
$sqlFile = __DIR__ . '/api/migrations/001_create_documentos_repartidor.sql';
if (file_exists($sqlFile)) {
    $sqlContent = file_get_contents($sqlFile);
    if (strpos($sqlContent, 'CREATE TABLE IF NOT EXISTS documentos_repartidor') !== false && strpos($sqlContent, 'unique_doc_per_user') !== false) {
        echo "  ✅ Migración SQL creada correctamente con restricción UNIQUE\n";
    } else {
        echo "  ❌ ERROR: El archivo SQL no tiene la estructura requerida\n";
    }
} else {
    echo "  ❌ ERROR: Archivo de migración no encontrado\n";
}

// 4. Verificación de la carpeta de uploads protegida
echo "\n[4] VERIFICANDO PROTECCIÓN DE DIRECTORIO DE UPLOADS\n";
$htaccessFile = __DIR__ . '/api/uploads/documentos/.htaccess';
if (file_exists($htaccessFile)) {
    echo "  ✅ Archivo .htaccess de protección creado en api/uploads/documentos/\n";
} else {
    echo "  ❌ ERROR: Faltante el archivo .htaccess en api/uploads/documentos/\n";
}

echo "\n========================================================\n";
echo "PRUEBAS AUTOMÁTICAS DE ESTRUCTURA Y ARCHIVOS COMPLETADAS\n";
echo "========================================================\n";

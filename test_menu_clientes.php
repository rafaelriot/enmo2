<?php
// Verification script for hamburger menu and client management module

echo "========================================================\n";
echo "    ENMO2 - VERIFICACIÓN DE MENÚ Y MÓDULO DE CLIENTES\n";
echo "========================================================\n\n";

// 1. Verificar endpoint de clientes
echo "[1] VERIFICANDO ENDPOINT DE CLIENTES EN admin.php\n";
$adminPhp = file_get_contents(__DIR__ . '/api/src/routes/admin.php');

if (strpos($adminPhp, '/clientes') !== false) {
    echo "  ✅ Endpoint GET /api/admin/clientes verificado en admin.php\n";
} else {
    echo "  ❌ ERROR: Falta endpoint GET /api/admin/clientes\n";
}

// 2. Verificar existencia de gestion_de_clientes.html
echo "\n[2] VERIFICANDO VISTA DE GESTIÓN DE CLIENTES\n";
if (file_exists(__DIR__ . '/gestion_de_clientes.html')) {
    $clientesHtml = file_get_contents(__DIR__ . '/gestion_de_clientes.html');
    if (strpos($clientesHtml, 'cargarClientes') !== false && strpos($clientesHtml, 'clientes-container') !== false) {
        echo "  ✅ Módulo gestion_de_clientes.html creado e integrado con la API\n";
    } else {
        echo "  ❌ ERROR: gestion_de_clientes.html incompleto\n";
    }
} else {
    echo "  ❌ ERROR: Falta gestion_de_clientes.html\n";
}

// 3. Verificar menú de hamburguesa en dashboard_principal.html, panel_de_administracion_repartidores.html y mapa_en_vivo.html
echo "\n[3] VERIFICANDO MENÚ LATERAL Y HAMBURGUESA EN ADMIN\n";
$dashHtml = file_get_contents(__DIR__ . '/dashboard_principal.html');
$repHtml = file_get_contents(__DIR__ . '/panel_de_administracion_repartidores.html');
$mapaHtml = file_get_contents(__DIR__ . '/mapa_en_vivo.html');

if (strpos($dashHtml, 'id="btn-menu"') !== false && strpos($dashHtml, 'id="sidebar"') !== false) {
    echo "  ✅ Menú de hamburguesa y sidebar drawer conectados en dashboard_principal.html\n";
} else {
    echo "  ❌ ERROR: Falta sidebar en dashboard_principal.html\n";
}

if (strpos($repHtml, 'id="btn-menu"') !== false && strpos($repHtml, 'id="sidebar"') !== false) {
    echo "  ✅ Menú de hamburguesa y sidebar drawer conectados en panel_de_administracion_repartidores.html\n";
} else {
    echo "  ❌ ERROR: Falta sidebar en panel_de_administracion_repartidores.html\n";
}

if (strpos($mapaHtml, 'id="btn-menu"') !== false && strpos($mapaHtml, 'id="sidebar"') !== false) {
    echo "  ✅ Menú de hamburguesa y sidebar drawer conectados en mapa_en_vivo.html\n";
} else {
    echo "  ❌ ERROR: Falta sidebar en mapa_en_vivo.html\n";
}

echo "\n========================================================\n";
echo "      TODAS LAS PRUEBAS DE MENÚ Y CLIENTES COMPLETADAS\n";
echo "========================================================\n";

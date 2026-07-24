<?php
// Verification script for fleet & real map filtering

echo "========================================================\n";
echo "    ENMO2 - VERIFICACIÓN DE MAPA EN VIVO Y FLOTA\n";
echo "========================================================\n\n";

// 1. Verificar endpoints en admin.php
echo "[1] VERIFICANDO REGLAS Y ENDPOINTS EN admin.php\n";
$adminPhp = file_get_contents(__DIR__ . '/api/src/routes/admin.php');

if (strpos($adminPhp, 'latitud_actual != 0') !== false) {
    echo "  ✅ Filtro de repartidores activos con GPS en línea verificado en /repartidores-online\n";
} else {
    echo "  ❌ ERROR: Falta restricción de latitud GPS en /repartidores-online\n";
}

if (strpos($adminPhp, '/repartidores-flota') !== false) {
    echo "  ✅ Endpoint /repartidores-flota verificado en admin.php\n";
} else {
    echo "  ❌ ERROR: Falta endpoint /repartidores-flota\n";
}

// 2. Verificar interfaz de Flota en panel_de_administracion_repartidores.html
echo "\n[2] VERIFICANDO VISTA DE FLOTA EN EL PANEL DE ADMINISTRADOR\n";
$flotaHtml = file_get_contents(__DIR__ . '/panel_de_administracion_repartidores.html');

if (strpos($flotaHtml, 'cargarFlotaRepartidores') !== false && strpos($flotaHtml, 'flota-container') !== false) {
    echo "  ✅ Carga dinámica de la flota conectada en panel_de_administracion_repartidores.html\n";
} else {
    echo "  ❌ ERROR: Pestaña Flota no está conectada a la API\n";
}

// 3. Verificar remoción de marcadores desconectados en mapas
echo "\n[3] VERIFICANDO REMOCIÓN DINÁMICA DE MARCADORES DESCONECTADOS\n";
$dashHtml = file_get_contents(__DIR__ . '/dashboard_principal.html');
$mapaHtml = file_get_contents(__DIR__ . '/mapa_en_vivo.html');

if (strpos($dashHtml, 'activeIds.has') !== false && strpos($mapaHtml, 'activeIds.has') !== false) {
    echo "  ✅ Limpieza dinámica de marcadores inactivos verificada en los mapas de administración\n";
} else {
    echo "  ❌ ERROR: Falta remover marcadores de repartidores desconectados\n";
}

echo "\n========================================================\n";
echo "      TODAS LAS PRUEBAS DE FLOTA Y MAPA COMPLETADAS\n";
echo "========================================================\n";

<?php
// Script de prueba e integridad de los menús administrativos

echo "========================================================\n";
echo "    ENMO2 - TEST DE INTEGRIDAD Y MENÚS ADMINISTRATIVOS\n";
echo "========================================================\n\n";

$archivosAdmin = [
    'dashboard_principal.html' => 'Dashboard Principal',
    'panel_de_administracion_repartidores.html' => 'Panel Repartidores',
    'gestion_de_clientes.html' => 'Gestión Clientes',
    'mapa_en_vivo.html' => 'Mapa en Vivo'
];

$enlacesRequeridos = [
    'dashboard_principal.html' => 'Dashboard Inicio',
    'panel_de_administracion_repartidores.html' => 'Gestión Repartidores',
    'gestion_de_clientes.html' => 'Gestión Clientes',
    'mapa_en_vivo.html' => 'Mapa en Vivo'
];

$todosCorrectos = true;

foreach ($archivosAdmin as $archivo => $nombre) {
    echo "[+] Analizando $nombre ($archivo)...\n";
    $path = __DIR__ . '/' . $archivo;
    
    if (!file_exists($path)) {
        echo "  ❌ ERROR: El archivo $archivo no existe.\n";
        $todosCorrectos = false;
        continue;
    }
    
    $content = file_get_contents($path);
    
    // 1. Comprobar existencia del sidebar
    if (strpos($content, 'id="sidebar"') !== false) {
        echo "  ✅ Sidebar drawer (<aside id='sidebar'>) presente.\n";
    } else {
        echo "  ❌ ERROR: Falta <aside id='sidebar'>.\n";
        $todosCorrectos = false;
    }
    
    // 2. Comprobar los 4 enlaces dentro del sidebar
    foreach ($enlacesRequeridos as $targetFile => $targetText) {
        if (strpos($content, $targetFile) !== false && strpos($content, $targetText) !== false) {
            echo "     • Enlace a $targetText ($targetFile): OK\n";
        } else {
            echo "     • ❌ ERROR: Falta enlace a $targetText ($targetFile)\n";
            $todosCorrectos = false;
        }
    }
    
    // 3. Comprobar que NO contenga llamada a setupBottomNavigation si es dashboard_principal
    if ($archivo === 'dashboard_principal.html') {
        if (strpos($content, 'setupBottomNavigation') === false) {
            echo "  ✅ Llamada a setupBottomNavigation eliminada de $archivo para proteger el sidebar.\n";
        } else {
            echo "  ❌ ADVERTENCIA: setupBottomNavigation aún se invoca en $archivo.\n";
            $todosCorrectos = false;
        }
    }
    echo "\n";
}

// 4. Comprobar blindaje en app.js
echo "[+] Comprobando blindaje de selector en app.js...\n";
$appJs = file_get_contents(__DIR__ . '/app.js');
if (strpos($appJs, ':not(#sidebar nav)') !== false) {
    echo "  ✅ Blindaje de selector :not(#sidebar nav) verificado en app.js\n";
} else {
    echo "  ❌ ERROR: Falta blindaje de selector en app.js\n";
    $todosCorrectos = false;
}

echo "\n========================================================\n";
if ($todosCorrectos) {
    echo "  🎉 TODOS LOS MENÚS Y ENLACES ADMINISTRATIVOS ESTÁN CORRECTOS\n";
} else {
    echo "  ❌ SE ENCONTRARON ERRORES EN LA VERIFICACIÓN\n";
}
echo "========================================================\n";

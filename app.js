// Generar o recuperar Correlation ID para rastreo end-to-end por sesión
function getCorrelationId() {
    let cid = sessionStorage.getItem('correlation_id');
    if (!cid) {
        cid = 'client-' + Math.random().toString(36).substring(2, 11) + '-' + Date.now();
        sessionStorage.setItem('correlation_id', cid);
    }
    return cid;
}

// Helper para obtener headers de autenticación con JWT y Correlation-ID
function getAuthHeaders(extraHeaders = {}) {
    const token = localStorage.getItem('token');
    const headers = {
        'X-Correlation-ID': getCorrelationId(),
        ...extraHeaders
    };
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }
    return headers;
}

// Global Client Observability: Captura de errores no atrapados en el navegador
window.addEventListener('error', function(event) {
    const errorData = {
        mensaje: event.message || 'Error JS',
        context: {
            filename: event.filename,
            lineno: event.lineno,
            colno: event.colno,
            url: window.location.href,
            user: localStorage.getItem('usuario') ? JSON.parse(localStorage.getItem('usuario')).email : 'Anon'
        }
    };
    
    // Enviar silenciosamente al backend de observabilidad
    try {
        fetch('api/observabilidad/client-log', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(errorData)
        }).catch(() => {});
    } catch(e) {}
});

// Redireccionar al login si no tiene sesión activa
function checkAuth() {
    const usuarioStr = localStorage.getItem('usuario');
    if (!usuarioStr) {
        window.location.href = 'inicio_de_sesion.html';
        return null;
    }
    return JSON.parse(usuarioStr);
}

// Mostrar notificaciones flotantes toast mejoradas con animación
function mostrarToast(mensaje, esExito = true) {
    const viejo = document.getElementById('status-toast');
    if (viejo) viejo.remove();
    
    const toast = document.createElement('div');
    toast.id = 'status-toast';
    toast.className = `fixed top-6 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl text-white font-label-bold text-xs shadow-2xl z-[99999] transition-all duration-300 scale-90 opacity-0 ${esExito ? 'bg-primary border border-primary-container/20' : 'bg-error border border-error-container/20'}`;
    toast.textContent = mensaje;
    document.body.appendChild(toast);
    
    // Animar entrada
    setTimeout(() => {
        toast.classList.remove('scale-90', 'opacity-0');
        toast.classList.add('scale-100', 'opacity-100');
    }, 10);
    
    // Animar salida
    setTimeout(() => { 
        toast.classList.remove('scale-100', 'opacity-100');
        toast.classList.add('scale-90', 'opacity-0'); 
        setTimeout(() => toast.remove(), 300); 
    }, 4000);
}

// Inyectar variables de CSS para el Dark Mode dinámicamente
function injectThemeStyles() {
    const styleEl = document.createElement('style');
    styleEl.id = 'theme-variables-css';
    styleEl.textContent = `
        :root {
            --background: #f9f9fc;
            --on-background: #1a1c1e;
            --surface: #f9f9fc;
            --on-surface: #1a1c1e;
            --surface-variant: #e2e2e5;
            --on-surface-variant: #3e4a3c;
            --outline: #6e7b6b;
            --outline-variant: #bdcab9;
            --surface-container-lowest: #ffffff;
            --surface-container-low: #f3f3f6;
            --surface-container: #eeeef0;
            --surface-container-high: #e8e8ea;
            --surface-container-highest: #e2e2e5;
        }
        html.dark {
            --background: #111315;
            --on-background: #e2e2e5;
            --surface: #111315;
            --on-surface: #e2e2e5;
            --surface-variant: #42474e;
            --on-surface-variant: #c2c7cf;
            --outline: #8c9199;
            --outline-variant: #42474e;
            --surface-container-lowest: #1a1c1e;
            --surface-container-low: #222427;
            --surface-container: #2b2e31;
            --surface-container-high: #36393d;
            --surface-container-highest: #414549;
            color-scheme: dark;
        }
        body {
            transition: background-color 0.3s, color 0.3s;
        }
    `;
    document.head.appendChild(styleEl);
}

// Inicializar el tema (al cargar la página)
function initTheme() {
    injectThemeStyles();
    const savedTheme = localStorage.getItem('theme') || 'light';
    if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark');
        document.documentElement.classList.remove('light');
    } else {
        document.documentElement.classList.add('light');
        document.documentElement.classList.remove('dark');
    }
}

// Alternar entre modo claro y oscuro
function toggleDarkMode() {
    const isDark = document.documentElement.classList.contains('dark');
    if (isDark) {
        document.documentElement.classList.remove('dark');
        document.documentElement.classList.add('light');
        localStorage.setItem('theme', 'light');
        mostrarToast('Modo claro activado ☀️');
    } else {
        document.documentElement.classList.remove('light');
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
        mostrarToast('Modo oscuro activado 🌙');
    }
}

// Inicializar interacciones rápidas de botones (Snappy Micro-interactions)
function initMicroInteractions() {
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
        button.addEventListener('mousedown', () => {
            button.style.transform = 'scale(0.96)';
        });
        button.addEventListener('mouseup', () => {
            button.style.transform = 'scale(1)';
        });
        button.addEventListener('mouseleave', () => {
            button.style.transform = 'scale(1)';
        });
    });
}

// Configuración común de Sidebar
function setupSidebar(usuario) {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const btnOpen = document.getElementById('btn-menu-sidebar');
    const btnClose = document.getElementById('btn-close-sidebar');
    const btnLogout = document.getElementById('btn-logout-sidebar');

    if (!sidebar || !backdrop) return;

    function openSidebar() {
        backdrop.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            sidebar.classList.remove('-translate-x-full');
        }, 10);
    }
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('opacity-0');
        setTimeout(() => backdrop.classList.add('hidden'), 300);
    }

    if (btnOpen) btnOpen.addEventListener('click', openSidebar);
    if (btnClose) btnClose.addEventListener('click', closeSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);
    if (btnLogout) {
        btnLogout.addEventListener('click', () => {
            localStorage.removeItem('usuario');
            localStorage.removeItem('token');
            window.location.href = 'inicio_de_sesion.html';
        });
    }

    if (usuario) {
        // Nombre e iniciales
        const userNameEl = document.getElementById('sidebar-user-name');
        const userAvatarEl = document.getElementById('sidebar-user-avatar');
        const userRoleEl = document.getElementById('sidebar-user-role');
        
        if (userNameEl) userNameEl.textContent = usuario.nombre || 'Usuario';
        if (userAvatarEl) {
            const iniciales = (usuario.nombre || 'U').split(' ').map(p => p[0]).join('').substring(0, 2).toUpperCase();
            userAvatarEl.textContent = iniciales;
        }

        // Etiqueta del rol
        if (userRoleEl) {
            const rolLabels = { repartidor: '🏍️ Repartidor', cliente: '🛒 Cliente', administrador: '⚙️ Administrador' };
            userRoleEl.textContent = rolLabels[usuario.rol] || usuario.rol;
        }

        // Links de navegación según rol
        const nav = document.getElementById('sidebar-nav');
        if (nav) {
            let links = [];
            const currentPath = window.location.pathname;
            
            if (usuario.rol === 'repartidor') {
                links = [
                    { icon: 'home', label: 'Inicio', href: 'dashboard_repartidor.html', active: currentPath.includes('dashboard_repartidor') },
                    { icon: 'local_mall', label: 'Pedidos Disponibles', href: 'pedidos_disponibles.html', active: currentPath.includes('pedidos_disponibles') },
                    { icon: 'map', label: 'Seguimiento', href: 'pedido_en_curso.html', active: currentPath.includes('pedido_en_curso') },
                    { icon: 'person', label: 'Mi Perfil', href: 'perfil_del_repartidor.html', active: currentPath.includes('perfil_del_repartidor') },
                ];
            } else if (usuario.rol === 'administrador') {
                links = [
                    { icon: 'home', label: 'Dashboard Inicio', href: 'dashboard_principal.html', active: currentPath.includes('dashboard_principal') },
                    { icon: 'two_wheeler', label: 'Gestión Repartidores', href: 'panel_de_administracion_repartidores.html', active: currentPath.includes('panel_de_administracion_repartidores') },
                    { icon: 'group', label: 'Gestión Clientes', href: 'gestion_de_clientes.html', active: currentPath.includes('gestion_de_clientes') },
                    { icon: 'map', label: 'Mapa en Vivo', href: 'mapa_en_vivo.html', active: currentPath.includes('mapa_en_vivo') },
                ];
            } else {
                links = [
                    { icon: 'home', label: 'Inicio', href: 'inicio_cliente.html', active: currentPath.includes('inicio_cliente') },
                    { icon: 'map', label: 'Seguimiento', href: 'mapa_en_vivo.html', active: currentPath.includes('mapa_en_vivo') },
                    { icon: 'list_alt', label: 'Mis Pedidos', href: 'gestion_de_pedidos.html', active: currentPath.includes('gestion_de_pedidos') },
                ];
            }
            
            nav.innerHTML = links.map(l => `
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg ${l.active ? 'bg-primary-container/10 text-primary' : 'hover:bg-primary-container/10 hover:text-primary text-on-surface-variant'} transition-colors font-label-bold" href="${l.href}">
                    <span class="material-symbols-outlined">${l.icon}</span>
                    <span>${l.label}</span>
                </a>`).join('');
        }
    }
}

// Configuración común de navegación inferior
function setupBottomNavigation(usuario) {
    const bottomNav = document.querySelector('nav');
    if (!bottomNav || !usuario) return;

    const currentPath = window.location.pathname;

    if (usuario.rol === 'repartidor') {
        bottomNav.innerHTML = `
            <a class="flex flex-col items-center justify-center ${currentPath.includes('dashboard_repartidor') ? 'text-primary font-bold' : 'text-on-surface-variant'} px-4 py-1 hover:text-primary transition-colors active:scale-90" href="dashboard_repartidor.html">
                <span class="material-symbols-outlined">home</span>
                <span class="font-label-sm text-[10px]">Inicio</span>
            </a>
            <a class="flex flex-col items-center justify-center ${currentPath.includes('pedidos_disponibles') ? 'text-primary font-bold' : 'text-on-surface-variant'} px-4 py-1 hover:text-primary transition-colors active:scale-90" href="pedidos_disponibles.html">
                <span class="material-symbols-outlined">local_mall</span>
                <span class="font-label-sm text-[10px]">Disponibles</span>
            </a>
            <a class="flex flex-col items-center justify-center ${currentPath.includes('pedido_en_curso') ? 'bg-primary-container text-on-primary-container rounded-full px-5 py-2' : 'text-on-surface-variant px-4 py-1'} transition-all active:scale-90" href="pedido_en_curso.html">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">map</span>
                <span class="font-label-sm text-[10px]">Mapa</span>
            </a>
            <a class="flex flex-col items-center justify-center ${currentPath.includes('perfil_del_repartidor') ? 'text-primary font-bold' : 'text-on-surface-variant'} px-4 py-1 hover:text-primary transition-colors active:scale-90" href="perfil_del_repartidor.html">
                <span class="material-symbols-outlined">person</span>
                <span class="font-label-sm text-[10px]">Mi Perfil</span>
            </a>
        `;
    } else if (usuario.rol === 'administrador') {
        bottomNav.innerHTML = `
            <a class="flex flex-col items-center justify-center ${currentPath.includes('dashboard_principal') ? 'bg-primary-container text-on-primary-container rounded-full px-4 py-1' : 'text-on-surface-variant'} active:scale-90 transition-transform duration-200" href="dashboard_principal.html">
                <span class="material-symbols-outlined">home</span>
                <span class="font-label-sm text-label-sm">Inicio</span>
            </a>
            <a class="flex flex-col items-center justify-center ${currentPath.includes('mapa_en_vivo') ? 'bg-primary-container text-on-primary-container rounded-full px-4 py-1' : 'text-on-surface-variant'} hover:text-primary active:scale-90 transition-transform duration-200" href="mapa_en_vivo.html">
                <span class="material-symbols-outlined">map</span>
                <span class="font-label-sm text-label-sm">Mapa</span>
            </a>
            <a class="flex flex-col items-center justify-center ${currentPath.includes('gestion_de_pedidos') ? 'bg-primary-container text-on-primary-container rounded-full px-4 py-1' : 'text-on-surface-variant'} hover:text-primary active:scale-90 transition-transform duration-200" href="gestion_de_pedidos.html">
                <span class="material-symbols-outlined">list_alt</span>
                <span class="font-label-sm text-label-sm">Pedidos</span>
            </a>
        `;
    } else {
        bottomNav.innerHTML = `
            <a class="flex flex-col items-center justify-center ${currentPath.includes('inicio_cliente') ? 'bg-primary-container text-on-primary-container rounded-full px-4 py-1' : 'text-on-surface-variant'} active:scale-90 transition-transform duration-200" href="inicio_cliente.html">
                <span class="material-symbols-outlined">home</span>
                <span class="font-label-sm text-label-sm">Inicio</span>
            </a>
            <a class="flex flex-col items-center justify-center ${currentPath.includes('mapa_en_vivo') ? 'bg-primary-container text-on-primary-container rounded-full px-4 py-1' : 'text-on-surface-variant'} hover:text-primary active:scale-90 transition-transform duration-200" href="mapa_en_vivo.html">
                <span class="material-symbols-outlined">map</span>
                <span class="font-label-sm text-label-sm">Mapa</span>
            </a>
            <a class="flex flex-col items-center justify-center ${currentPath.includes('gestion_de_pedidos') ? 'bg-primary-container text-on-primary-container rounded-full px-4 py-1' : 'text-on-surface-variant'} hover:text-primary active:scale-90 transition-transform duration-200" href="gestion_de_pedidos.html">
                <span class="material-symbols-outlined">list_alt</span>
                <span class="font-label-sm text-label-sm">Mis Pedidos</span>
            </a>
        `;
    }
}

// Inicializar scripts transversales una vez cargado el DOM
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initMicroInteractions();
    
    // Configurar campos de formulario para efecto de foco
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', () => {
            input.parentElement.classList.add('shadow-md');
        });
        input.addEventListener('blur', () => {
            input.parentElement.classList.remove('shadow-md');
        });
    });

    // 🛡️ Registrar Service Worker con auto-actualización
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js')
            .then(reg => {
                console.log('Service Worker registrado con éxito:', reg.scope);
                
                // Detectar cuando hay una actualización disponible
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    if (newWorker) {
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'activated') {
                                console.log('[SW] Nueva versión activada, recargando...');
                                window.location.reload();
                            }
                        });
                    }
                });
            })
            .catch(err => console.error('Error registrando Service Worker:', err));

        // Escuchar mensajes del Service Worker (notificación de actualización)
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'SW_UPDATED') {
                console.log('[SW] Actualización detectada:', event.data.version);
                window.location.reload();
            }
        });
    }
});

/**
 * Renderiza el avatar de un usuario en un elemento contenedor dado.
 * Si el usuario tiene foto_url (ej. Google OAuth), muestra la imagen real.
 * De lo contrario, muestra las iniciales de su nombre o un ícono por defecto.
 * 
 * @param {HTMLElement|string} container - Elemento contenedor o ID
 * @param {Object} user - Objeto con datos de usuario (nombre, foto_url)
 * @param {string} defaultIcon - Nombre de ícono de Material Symbols (default: 'person')
 */
function renderUserAvatar(arg1, arg2, defaultIcon = 'person') {
    let el = null;
    let user = null;

    if (arg1 && (typeof arg1 === 'string' || arg1 instanceof HTMLElement)) {
        el = typeof arg1 === 'string' ? document.getElementById(arg1) : arg1;
        user = arg2;
    } else if (arg2 && (typeof arg2 === 'string' || arg2 instanceof HTMLElement)) {
        el = typeof arg2 === 'string' ? document.getElementById(arg2) : arg2;
        user = arg1;
    } else {
        el = typeof arg1 === 'string' ? document.getElementById(arg1) : (arg1 || arg2);
        user = (arg1 && arg1.nombre) ? arg1 : arg2;
    }

    if (!el) return;

    if (user && user.foto_url && user.foto_url.trim() !== '') {
        el.innerHTML = `<img src="${user.foto_url}" class="w-full h-full object-cover rounded-full" alt="Avatar" referrerpolicy="no-referrer" onerror="this.onerror=null; renderUserAvatarFallback(this.parentElement, '${user.nombre || ''}', '${defaultIcon}')"/>`;
        el.classList.remove('bg-primary-container', 'bg-surface-container-high', 'text-on-primary-container');
        el.classList.add('overflow-hidden', 'bg-surface-container-high');
    } else {
        renderUserAvatarFallback(el, user ? user.nombre : '', defaultIcon);
    }
}

function renderUserAvatarFallback(el, nombre, defaultIcon) {
    if (!el) return;
    if (nombre && nombre.trim() !== '') {
        const iniciales = nombre.trim().split(' ').map(p => p[0]).join('').substring(0, 2).toUpperCase();
        el.innerHTML = `<span class="font-bold text-sm leading-none select-none">${iniciales}</span>`;
        el.className = el.className.replace(/bg-[^\s]+/, '') + ' bg-primary-container text-on-primary-container flex items-center justify-center font-black';
    } else {
        el.innerHTML = `<span class="material-symbols-outlined text-xl">${defaultIcon}</span>`;
        el.className = el.className.replace(/bg-[^\s]+/, '') + ' bg-surface-container-high text-on-surface-variant flex items-center justify-center';
    }
}


<?php
// La sesión ya debe estar iniciada en la página que incluye este header.
// Si no, descomenta la siguiente línea:
// if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security check

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mokuso Manager</title>
    
    <!-- Fuentes e Iconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- SweetAlert2 para notificaciones -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* --- ESTILOS "DIGITAL DOJO" UNIFICADOS --- */
        :root {
            --color-bg: #0a0a0f;
            --color-surface: rgba(30, 32, 40, 0.75);
            --color-primary: #ff8c00;
            --color-primary-glow: rgba(255, 140, 0, 0.5);
            --color-secondary: #00bfff;
            --color-accent: #ff3cac;
            --color-success: #00BFA6;
            --color-error: #ff4747;
            --color-text-light: #f0f0f0;
            --color-text-muted: #a0a0a0;
            --color-border: rgba(97, 97, 97, 0.3);
            --border-radius: 22px;
            --backdrop-blur: 18px;
            --shadow: 0 12px 48px 0 rgba(0,0,0,0.45);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            min-height: 100vh; margin: 0; font-family: 'Poppins', sans-serif;
            color: var(--color-text-light); background: linear-gradient(135deg, #181824 0%, #23243a 100%);
            position: relative; overflow-x: hidden;
        }
        .background-blur { position: fixed; inset: 0; z-index: -1; pointer-events: none; }
        .blur-circle {
            position: absolute; border-radius: 50%; filter: blur(100px);
            opacity: 0.35; animation: float 12s infinite alternate ease-in-out;
        }
        .blur1 { width: 420px; height: 420px; background: var(--color-primary-glow); top: 10%; left: 5%; animation-delay: 0s;}
        .blur2 { width: 320px; height: 320px; background: var(--color-secondary); top: 60%; left: 60%; animation-delay: 2s;}
        .blur3 { width: 220px; height: 220px; background: var(--color-accent); top: 70%; left: 10%; animation-delay: 4s;}
        @keyframes float { from { transform: scale(1) translateY(0); } to { transform: scale(1.1) translateY(-30px); } }

        /* --- MENÚ DE NAVEGACIÓN LATERAL (SIDEBAR) --- */
        .sidebar {
            background: var(--color-surface); border-right: 1.5px solid var(--color-border);
            border-top-right-radius: var(--border-radius); border-bottom-right-radius: var(--border-radius);
            box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur)); -webkit-backdrop-filter: blur(var(--backdrop-blur));
            width: 260px; height: 100vh; position: fixed; top: 0; left: 0;
            padding: 1.5rem; z-index: 100;
            display: flex; flex-direction: column; transition: transform 0.3s ease-in-out;
        }
        .sidebar-header {
            text-align: center; margin-bottom: 2.5rem;
        }
        .sidebar-header .logo { height: 50px; filter: drop-shadow(0 0 15px var(--color-primary-glow)); }
        .sidebar-header h2 { font-family: 'Orbitron', sans-serif; font-size: 1.5rem; color: var(--color-text-light); margin: 0.5rem 0 0 0; }
        
        .sidebar-nav { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav li { margin-bottom: 0.75rem; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1rem;
            color: var(--color-text-muted); text-decoration: none; border-radius: 12px;
            font-weight: 500; font-size: 1rem; transition: background-color 0.3s, color 0.3s, box-shadow 0.3s;
        }
        .sidebar-nav a:hover { background-color: rgba(255,255,255,0.05); color: var(--color-text-light); }
        .sidebar-nav a.active {
            background-color: var(--color-primary); color: #101012; font-weight: 700;
            box-shadow: 0 5px 20px var(--color-primary-glow);
        }
        .sidebar-nav a svg { width: 24px; height: 24px; stroke-width: 2; } /* Icon size */
        
        .sidebar-footer { margin-top: auto; text-align: center; }

        /* --- CONTENIDO PRINCIPAL --- */
        .main-content-wrapper {
            margin-left: 260px; /* Space for sidebar */
            width: calc(100% - 260px);
            position: relative;
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
        }

        /* --- RESPONSIVE FOR SIDEBAR --- */
        @media (max-width: 992px) {
            .sidebar { 
                transform: translateX(-100%); 
                /* Opcional: Si quieres un botón para mostrar/ocultar en móvil, esta es la base */
            }
            .main-content-wrapper { 
                margin-left: 0; 
                width: 100%; 
            }
        }
        
        /* --- ESTILOS SWEETALERT2 --- */
        .swal2-popup { background-color: var(--color-surface) !important; border: 1.5px solid var(--color-border) !important; border-radius: var(--border-radius) !important; color: var(--color-text-light) !important; box-shadow: var(--shadow) !important; }
        .swal2-title { color: var(--color-text-light) !important; }
        .swal2-html-container { color: var(--color-text-muted) !important; }
        .swal2-confirm { background: linear-gradient(90deg, var(--color-primary), #e07b00) !important; border-radius: 10px !important; box-shadow: none !important; color: #101012 !important; font-weight: 600 !important; }
        .swal2-cancel { background-color: transparent !important; border: 2px solid var(--color-border) !important; color: var(--color-text-muted) !important; border-radius: 10px !important; font-weight: 600 !important; }
        
        /* Botones Ghost (útiles para tenerlos globalmente) */
        .btn-ghost { background: transparent; border: 2px solid var(--color-border); color: var(--color-text-muted); padding: 0.75rem 1.2rem; font-size: 0.9rem; font-weight: 600; border-radius: 10px; text-decoration: none; transition: color 0.3s, border-color 0.3s, transform 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-ghost:hover { color: var(--color-primary); border-color: var(--color-primary); transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="background-blur">
    <div class="blur-circle blur1"></div>
    <div class="blur-circle blur2"></div>
    <div class="blur-circle blur3"></div>
</div>

<aside class="sidebar">
    <div class="sidebar-header">
        <img src="/MOKUSO/assets/img/logo2.png" alt="Logo Mokuso" class="logo">
        <h2>Mokuso</h2>
    </div>
    
    <ul class="sidebar-nav">
        <li>
            <a href="/MOKUSO/dashboard/index.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"></path><path d="M12 20V4"></path><path d="M6 20V14"></path></svg> 
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="/MOKUSO/alumnos/index.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> 
                <span>Alumnos</span>
            </a>
        </li>
        <li>
            <a href="/MOKUSO/asistencias/index.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>
                <span>Asistencias</span>
            </a>
        </li>
        <li>
            <a href="/MOKUSO/pagos/">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg> 
                <span>Registrar Pago</span>
            </a>
        </li>
        <li>
            <a href="/MOKUSO/logros/">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V6.5A2.5 2.5 0 1 0 9.5 9M12 8v4M12 17.5v-1.5M4.8 11.2c-1.3 2.5-1.3 5.5 0 8.1M19.2 19.3c1.3-2.5 1.3-5.5 0-8.1M12 2v2M2 12h2M20 12h2"></path></svg> 
                <span>Añadir Logro</span>
            </a>
        </li>

        <!-- agreguemos un boton para ir al cronometro que esta en la raiz del proyhecto con este nombre gym_timer.html -->
        <li>
            <a href="/MOKUSO/gym_timer.html">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> 
                <span>Cronómetro</span>
            </a>    

    </ul>

    <div class="sidebar-footer">
        <a href="/MOKUSO/config/logout.php" class="btn-ghost" style="width: 100%;">
            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
        </a>
    </div>
</aside>

<main class="main-content-wrapper">
    <!-- El contenido específico de cada página (ej. dashboard.php, alumnos/index.php) irá aquí -->

    <script>
        // Script para resaltar el enlace de navegación activo
        document.addEventListener("DOMContentLoaded", function() {
            const currentLocation = window.location.pathname;
            const navLinks = document.querySelectorAll('.sidebar-nav a');

            navLinks.forEach(link => {
                // Comprobar si el href del enlace está incluido en la URL actual
                // Esto hace que /alumnos/index.php coincida con el enlace /alumnos/
                if (currentLocation.includes(link.getAttribute('href'))) {
                    link.classList.add('active');
                }
            });
        });
    </script>
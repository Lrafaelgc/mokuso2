<?php
session_start();
// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

include '../config/db.php';

// --- KPI 1: Total de Logros ---
$result_total = $conn->query("SELECT COUNT(id) as total FROM logros");
$total_assoc = $result_total->fetch_assoc();
$total_logros = isset($total_assoc['total']) ? $total_assoc['total'] : 0;

// --- KPI 2: Alumno con más logros ---
$sql_destacado = "
    SELECT a.nombre, a.apellidos, COUNT(l.id) as total_logros
    FROM logros l
    JOIN alumnos a ON l.alumno_id = a.id
    GROUP BY l.alumno_id
    ORDER BY total_logros DESC
    LIMIT 1";
$result_destacado = $conn->query($sql_destacado);
$alumno_destacado = $result_destacado->fetch_assoc();

// --- KPI 3: Logro más reciente ---
$sql_reciente = "
    SELECT l.logro, l.fecha_logro, a.nombre, a.apellidos
    FROM logros l
    JOIN alumnos a ON l.alumno_id = a.id
    ORDER BY l.fecha_logro DESC, l.id DESC
    LIMIT 1";
$result_reciente = $conn->query($sql_reciente);
$logro_reciente = $result_reciente->fetch_assoc();

// --- Widget: Últimos 5 Logros ---
$sql_ultimos_logros = "
    SELECT l.logro, l.fecha_logro, a.nombre, a.apellidos
    FROM logros l
    JOIN alumnos a ON l.alumno_id = a.id
    ORDER BY l.fecha_logro DESC, l.id DESC
    LIMIT 5";
$result_ultimos_logros = $conn->query($sql_ultimos_logros);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Logros - Mokuso Manager</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <style>
        /* --- ESTILOS "DIGITAL DOJO" UNIFICADOS --- */
        :root {
            --color-bg: #0a0a0f; --color-surface: rgba(30, 32, 40, 0.75);
            --color-primary: #ff8c00; --color-primary-glow: rgba(255, 140, 0, 0.5);
            --color-secondary: #00bfff; --color-accent: #ff3cac;
            --color-success: #00BFA6; --color-error: #ff4747; --color-warning: #ffd700;
            --color-text-light: #f0f0f0; --color-text-muted: #a0a0a0;
            --color-border: rgba(97, 97, 97, 0.3);
            --border-radius: 22px; --backdrop-blur: 18px; --shadow: 0 12px 48px 0 rgba(0,0,0,0.45);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            min-height: 100vh; margin: 0; font-family: 'Poppins', sans-serif;
            color: var(--color-text-light); background: linear-gradient(135deg, #181824 0%, #23243a 100%);
            position: relative; overflow-x: hidden;
        }

        /* Fondo Animado */
        .background-blur { position: fixed; inset: 0; z-index: -1; pointer-events: none; }
        .blur-circle { position: absolute; border-radius: 50%; filter: blur(100px); opacity: 0.35; animation: float 12s infinite alternate ease-in-out; }
        .blur1 { width: 420px; height: 420px; background: var(--color-primary-glow); top: 10%; left: 5%; }
        .blur2 { width: 320px; height: 320px; background: var(--color-secondary); top: 60%; left: 60%; animation-delay: 2s; }
        .blur3 { width: 220px; height: 220px; background: var(--color-accent); top: 70%; left: 10%; animation-delay: 4s; }
        @keyframes float { from { transform: scale(1) translateY(0); } to { transform: scale(1.1) translateY(-30px); } }

        /* --- SIDEBAR (IGUAL QUE TU HEADER REFERENCIA) --- */
        .sidebar { background: var(--color-surface); border-right: 1.5px solid var(--color-border); border-radius: 0 var(--border-radius) var(--border-radius) 0; box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur)); width: 260px; height: 100vh; position: fixed; top: 0; left: 0; padding: 1.5rem; z-index: 100; display: flex; flex-direction: column; }
        .sidebar-header { text-align: center; margin-bottom: 2.5rem; }
        .sidebar-header .logo { height: 50px; filter: drop-shadow(0 0 15px var(--color-primary-glow)); }
        .sidebar-header h2 { font-family: 'Orbitron', sans-serif; font-size: 1.5rem; color: var(--color-text-light); margin: 0.5rem 0 0 0; }
        .sidebar-nav { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav li { margin-bottom: 0.75rem; }
        .sidebar-nav a { display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1rem; color: var(--color-text-muted); text-decoration: none; border-radius: 12px; font-weight: 500; font-size: 1rem; transition: all 0.3s; }
        .sidebar-nav a:hover { background-color: rgba(255,255,255,0.05); color: var(--color-text-light); }
        .sidebar-nav a.active { background-color: var(--color-primary); color: #101012; font-weight: 700; box-shadow: 0 5px 20px var(--color-primary-glow); }
        .sidebar-nav a svg { width: 24px; height: 24px; stroke-width: 2; }
        .sidebar-footer { margin-top: auto; text-align: center; }

        /* --- CONTENIDO PRINCIPAL --- */
        .main-content-wrapper { margin-left: 260px; width: calc(100% - 260px); position: relative; }
        .main-container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; z-index: 1; }

        /* Header de Página */
        .page-header { margin-bottom: 2.5rem; border-bottom: 1px solid var(--color-border); padding-bottom: 1rem; display: flex; justify-content: space-between; align-items: flex-end; }
        .page-header h1 { font-family: 'Orbitron', sans-serif; color: var(--color-primary); font-size: 2.2rem; margin: 0; letter-spacing: 1px; }
        .page-header p { margin: 0; color: var(--color-text-muted); font-size: 0.9rem; }

        /* KPI Grid */
        .kpi-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-bottom: 2.5rem; }
        .kpi-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--border-radius); padding: 1.5rem; position: relative; overflow: hidden; backdrop-filter: blur(10px); transition: transform 0.3s; display: flex; align-items: center; gap: 1.2rem; }
        .kpi-card:hover { transform: translateY(-5px); }
        .kpi-icon-box { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
        
        /* Estilos específicos KPI */
        .kpi-total .kpi-icon-box { background: rgba(255, 140, 0, 0.15); color: var(--color-primary); border: 1px solid var(--color-primary); }
        .kpi-star { border-color: var(--color-warning); }
        .kpi-star .kpi-icon-box { background: rgba(255, 215, 0, 0.15); color: var(--color-warning); border: 1px solid var(--color-warning); }
        .kpi-recent .kpi-icon-box { background: rgba(0, 191, 255, 0.15); color: var(--color-secondary); border: 1px solid var(--color-secondary); }

        .kpi-content h3 { margin: 0 0 5px 0; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: var(--color-text-muted); }
        .kpi-content .value { font-size: 1.8rem; font-weight: 700; color: var(--color-text-light); line-height: 1.1; }
        .kpi-content .subtext { font-size: 0.85rem; color: var(--color-text-muted); margin-top: 5px; display: block;}

        /* Botones de Acción */
        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .action-btn { background: rgba(255,255,255,0.03); border: 1px solid var(--color-border); padding: 1.5rem; border-radius: var(--border-radius); text-decoration: none; color: var(--color-text-light); text-align: center; transition: 0.3s; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; }
        .action-btn i { font-size: 2rem; margin-bottom: 10px; color: var(--color-primary); transition: 0.3s; }
        .action-btn span { font-weight: 600; font-size: 1rem; }
        .action-btn:hover { background: var(--color-primary); border-color: var(--color-primary); color: #000; box-shadow: 0 0 20px var(--color-primary-glow); transform: translateY(-3px); }
        .action-btn:hover i { color: #000; transform: scale(1.1); }
        
        .action-btn.secondary:hover { background: var(--color-secondary); border-color: var(--color-secondary); box-shadow: 0 0 20px rgba(0, 191, 255, 0.5); }

        /* Widget Lista */
        .widget-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--border-radius); padding: 0; overflow: hidden; backdrop-filter: blur(10px); }
        .widget-header { padding: 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); }
        .widget-header h3 { margin: 0; font-family: 'Orbitron'; font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
        
        .list-container { padding: 1rem; }
        .logro-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); transition: 0.2s; border-radius: 12px; margin-bottom: 5px; }
        .logro-item:last-child { border-bottom: none; margin-bottom: 0; }
        .logro-item:hover { background: rgba(255,255,255,0.05); }
        
        .logro-info { display: flex; align-items: center; gap: 1rem; }
        .logro-avatar { width: 40px; height: 40px; background: var(--color-surface); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--color-primary); border: 1px solid var(--color-border); }
        .logro-details strong { display: block; font-size: 0.95rem; }
        .logro-details span { font-size: 0.85rem; color: var(--color-secondary); }
        .logro-date { font-size: 0.8rem; color: var(--color-text-muted); background: rgba(0,0,0,0.3); padding: 4px 10px; border-radius: 20px; }

        .btn-ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text-muted); padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 8px; text-decoration: none; transition: 0.3s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }

        @media (max-width: 992px) { .sidebar { display: none; } .main-content-wrapper { margin-left: 0; width: 100%; } }
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
            <a href="/MOKUSO/logros/" class="active"> <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V6.5A2.5 2.5 0 1 0 9.5 9M12 8v4M12 17.5v-1.5M4.8 11.2c-1.3 2.5-1.3 5.5 0 8.1M19.2 19.3c1.3-2.5 1.3-5.5 0-8.1M12 2v2M2 12h2M20 12h2"></path></svg> 
                <span>Añadir Logro</span>
            </a>
        </li>
        <li>
            <a href="/MOKUSO/gym_timer.html">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> 
                <span>Cronómetro</span>
            </a>    
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="/MOKUSO/config/logout.php" class="btn-ghost" style="width: 100%; justify-content: center;">
            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
        </a>
    </div>
</aside>

<main class="main-content-wrapper">
    <div class="main-container">
        
        <div class="page-header">
            <div>
                <h1>Centro de Logros</h1>
                <p>Gestiona y premia el avance de tus alumnos</p>
            </div>
            </div>

        <div class="kpi-grid">
            <div class="kpi-card kpi-total">
                <div class="kpi-icon-box"><i class="fas fa-medal"></i></div>
                <div class="kpi-content">
                    <h3>Total Otorgados</h3>
                    <div class="value"><?php echo $total_logros; ?></div>
                    <span class="subtext">Logros en la historia</span>
                </div>
            </div>

            <div class="kpi-card kpi-star">
                <div class="kpi-icon-box"><i class="fas fa-trophy"></i></div>
                <div class="kpi-content">
                    <h3>Alumno Destacado</h3>
                    <div class="value" style="font-size: 1.4rem;">
                        <?php echo $alumno_destacado ? htmlspecialchars($alumno_destacado['nombre']) : 'N/A'; ?>
                    </div>
                    <span class="subtext">
                        <?php echo $alumno_destacado ? $alumno_destacado['total_logros'] . ' medallas ganadas' : '-'; ?>
                    </span>
                </div>
            </div>

            <div class="kpi-card kpi-recent">
                <div class="kpi-icon-box"><i class="fas fa-history"></i></div>
                <div class="kpi-content">
                    <h3>Último Logro</h3>
                    <div class="value" style="font-size: 1.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;">
                        <?php echo $logro_reciente ? htmlspecialchars($logro_reciente['logro']) : 'N/A'; ?>
                    </div>
                    <span class="subtext">
                        <?php echo $logro_reciente ? 'Para ' . htmlspecialchars($logro_reciente['nombre']) : '-'; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="action-grid">
            <a href="agregar_logro.php" class="action-btn">
                <i class="fas fa-plus-circle"></i>
                <span>Otorgar Nuevo Logro</span>
            </a>
            <a href="ver_logros.php" class="action-btn secondary">
                <i class="fas fa-list-ul"></i>
                <span>Ver Historial Completo</span>
            </a>
        </div>

        <div class="widget-card">
            <div class="widget-header">
                <h3><i class="fas fa-award" style="color:var(--color-primary);"></i> Actividad Reciente</h3>
                <a href="ver_logros.php" class="btn-ghost">Ver Todos <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="list-container">
                <?php if ($result_ultimos_logros && $result_ultimos_logros->num_rows > 0): ?>
                    <?php while($logro = $result_ultimos_logros->fetch_assoc()): ?>
                    <div class="logro-item">
                        <div class="logro-info">
                            <div class="logro-avatar">
                                <i class="fas fa-medal"></i>
                            </div>
                            <div class="logro-details">
                                <strong><?php echo htmlspecialchars($logro['nombre'] . ' ' . $logro['apellidos']); ?></strong>
                                <span><?php echo htmlspecialchars($logro['logro']); ?></span>
                            </div>
                        </div>
                        <div class="logro-date">
                            <i class="far fa-clock"></i> <?php echo date("d/m/Y", strtotime($logro['fecha_logro'])); ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center; padding: 2rem; color: var(--color-text-muted);">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No hay logros registrados aún.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

</body>
</html>
<?php $conn->close(); ?>
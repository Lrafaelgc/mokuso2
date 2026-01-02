<?php
// Incluir header si tienes lógica de sesión ahí, sino iniciamos sesión aquí
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}
include '../config/db.php'; // Para el automator script
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comando Central - Mokuso Elite</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* --- TEMA MOKUSO ELITE --- */
        :root {
            --primary: #ff6600; 
            --primary-glow: rgba(255, 102, 0, 0.3);
            --bg-main: #121214; --surface: #202024; --border: #323238;
            --text-white: #e1e1e6; --text-muted: #a8a8b3;
            --success: #04d361; --danger: #ff3e3e; --info: #00bfff; --warning: #fad733;
            --radius: 16px;
            --border-radius: 22px; --backdrop-blur: 18px; --shadow: 0 12px 48px 0 rgba(0,0,0,0.45);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            background-color: var(--bg-main); color: var(--text-white); font-family: 'Poppins', sans-serif;
            margin: 0; min-height: 100vh; overflow-x: hidden;
        }

        /* FONDO ANIMADO */
        .background-blur { position: fixed; inset: 0; z-index: -1; pointer-events: none; }
        .blur-circle { position: absolute; border-radius: 50%; filter: blur(100px); opacity: 0.25; animation: float 12s infinite alternate; }
        .b1 { top: -10%; left: 20%; width: 40vw; height: 40vw; background: var(--primary); }
        .b2 { bottom: 10%; right: -10%; width: 35vw; height: 35vw; background: var(--info); animation-delay: -5s; }
        @keyframes float { from {transform: translateY(0);} to {transform: translateY(40px);} }

        /* --- SIDEBAR (MENÚ LATERAL) --- */
        .sidebar { background: var(--surface); border-right: 1.5px solid var(--border); border-radius: 0 var(--radius) var(--radius) 0; box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur)); width: 260px; height: 100vh; position: fixed; top: 0; left: 0; padding: 1.5rem; z-index: 100; display: flex; flex-direction: column; }
        .sidebar-header { text-align: center; margin-bottom: 2.5rem; }
        .sidebar-header .logo { height: 50px; filter: drop-shadow(0 0 15px var(--primary-glow)); }
        .sidebar-header h2 { font-family: 'Orbitron', sans-serif; font-size: 1.5rem; color: var(--text-white); margin: 0.5rem 0 0 0; }
        .sidebar-nav { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav li { margin-bottom: 0.75rem; }
        
        /* Estilos de enlaces del menú */
        .sidebar-nav a { display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1rem; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 500; font-size: 1rem; transition: all 0.3s; }
        .sidebar-nav a:hover { background-color: rgba(255,255,255,0.05); color: var(--text-white); }
        .sidebar-nav a.active { background-color: var(--primary); color: #101012; font-weight: 700; box-shadow: 0 5px 20px var(--primary-glow); }
        .sidebar-nav a i { width: 20px; text-align: center; font-size: 1.1rem; }
        .sidebar-footer { margin-top: auto; text-align: center; }

        /* --- CONTENIDO PRINCIPAL --- */
        .main-content-wrapper { margin-left: 260px; width: calc(100% - 260px); position: relative; }
        .main-container { width: 100%; max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; z-index: 1; }

        /* RESPONSIVE: OCULTAR SIDEBAR EN MÓVIL */
        @media (max-width: 992px) { 
            .sidebar { display: none; } 
            .main-content-wrapper { margin-left: 0; width: 100%; } 
        }

        /* HEADER */
        .page-header {
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;
            margin-bottom: 2.5rem; border-bottom: 1px solid var(--border); padding-bottom: 1.5rem;
        }
        .header-title h1 { font-family: 'Orbitron', sans-serif; font-size: 2.2rem; margin: 0; display: flex; align-items: center; gap: 12px; color: var(--primary); }
        .header-title p { color: var(--text-muted); margin: 5px 0 0 0; font-size: 0.9rem; }

        /* --- NUEVO CSS: ACCIONES RÁPIDAS (BOTONES) --- */
        .header-controls { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .quick-actions { display: flex; gap: 10px; }
        
        .btn-quick {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text-white);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-quick:hover {
            background: var(--primary);
            color: #101012;
            border-color: var(--primary);
            box-shadow: 0 0 15px var(--primary-glow);
            transform: translateY(-2px);
            font-weight: 600;
        }

        /* BARRA DE FILTROS */
        .filter-bar {
            background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
            padding: 0.8rem 1.2rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        }
        .date-group { display: flex; align-items: center; gap: 10px; }
        .date-input {
            background: #18181b; border: 1px solid var(--border); color: var(--text-white);
            padding: 8px 12px; border-radius: 8px; font-family: inherit; outline: none; color-scheme: dark;
        }
        .btn-filter {
            background: var(--primary); color: #000; border: none; padding: 8px 20px; border-radius: 8px;
            font-weight: 600; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 8px;
        }
        .btn-filter:hover { transform: translateY(-2px); box-shadow: 0 0 15px var(--primary-glow); }

        /* Responsive para Header */
        @media (max-width: 768px) {
            .header-controls { width: 100%; flex-direction: column-reverse; align-items: stretch; }
            .quick-actions { display: grid; grid-template-columns: 1fr 1fr 1fr; width: 100%; }
            .btn-quick { justify-content: center; padding: 10px; }
            .btn-quick span { display: none; } 
            .btn-quick i { font-size: 1.2rem; margin: 0; }
        }

        /* GRID DE KPIs */
        .kpi-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;
        }
        .kpi-card {
            background: rgba(32, 32, 36, 0.7); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 1.5rem; backdrop-filter: blur(10px); transition: 0.3s; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between; height: 140px;
            text-decoration: none;
        }
        .kpi-card:hover { transform: translateY(-5px); border-color: var(--text-muted); }
        
        .kpi-card.income { border-top: 4px solid var(--success); }
        .kpi-card.expense { border-top: 4px solid var(--danger); }
        .kpi-card.balance { border-top: 4px solid var(--info); }
        .kpi-card.students { border-top: 4px solid var(--warning); }

        .kpi-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .kpi-label { color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .kpi-icon { font-size: 1.5rem; opacity: 0.7; }
        
        .kpi-value { font-size: 2rem; font-weight: 700; color: var(--text-white); margin-top: auto; }
        .kpi-card.income .kpi-value { color: var(--success); }
        .kpi-card.expense .kpi-value { color: var(--danger); }

        /* GRID DASHBOARD */
        .dashboard-grid {
            display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;
        }
        @media(max-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr; } }

        /* TARJETAS DE WIDGETS */
        .widget-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 1.5rem; height: 100%; display: flex; flex-direction: column;
        }
        .widget-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border); padding-bottom: 10px;
        }
        .widget-title { font-family: 'Orbitron', sans-serif; font-size: 1.2rem; margin: 0; display: flex; align-items: center; gap: 10px; color: var(--text-white); }
        
        /* LISTAS DE ALUMNOS */
        .student-list { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .student-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px; border-radius: 10px; margin-bottom: 8px; background: rgba(255,255,255,0.03);
            transition: 0.2s; text-decoration: none; color: var(--text-white);
        }
        .student-item:hover { background: rgba(255,255,255,0.07); transform: translateX(5px); }
        
        .student-info strong { display: block; font-size: 0.95rem; }
        .student-info small { color: var(--text-muted); font-size: 0.8rem; }
        
        .status-tag { font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; font-weight: 700; }
        .tag-warn { color: var(--warning); background: rgba(250, 215, 51, 0.15); border: 1px solid var(--warning); }
        .tag-danger { color: var(--danger); background: rgba(255, 62, 62, 0.15); border: 1px solid var(--danger); }

        /* LOADER */
        .loader-container { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; }
        .loader {
            width: 40px; height: 40px; border: 4px solid var(--surface); border-top: 4px solid var(--primary);
            border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 10px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* Botón cerrar sesión sidebar */
        .btn-ghost { background: transparent; border: 2px solid var(--border); color: var(--text-muted); padding: 0.75rem 1.2rem; font-size: 0.9rem; font-weight: 600; border-radius: 10px; text-decoration: none; transition: color 0.3s, border-color 0.3s, transform 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-ghost:hover { color: var(--primary); border-color: var(--primary); transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="background-blur">
    <div class="blur-circle b1"></div>
    <div class="blur-circle b2"></div>
</div>

<aside class="sidebar">
    <div class="sidebar-header">
        <img src="/MOKUSO/assets/img/logo2.png" alt="Logo Mokuso" class="logo">
        <h2>Mokuso</h2>
    </div>
    <ul class="sidebar-nav">
        <li><a href="/MOKUSO/dashboard/index.php" class="active"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a></li>
        <li><a href="/MOKUSO/alumnos/index.php"><i class="fas fa-users"></i> <span>Alumnos</span></a></li>
        <li><a href="/MOKUSO/asistencias/index.php"><i class="fas fa-calendar-check"></i> <span>Asistencias</span></a></li>
        <li><a href="/MOKUSO/pagos/index.php"><i class="fas fa-dollar-sign"></i> <span>Pagos</span></a></li>
        <li><a href="/MOKUSO/logros/index.php"><i class="fas fa-trophy"></i> <span>Logros</span></a></li>
        <li><a href="/MOKUSO/usuarios/index.php"><i class="fas fa-user-cog"></i> <span>Usuarios</span></a></li>
        <li><a href="/MOKUSO/gym_timer.html"><i class="fas fa-stopwatch"></i> <span>Cronómetro</span></a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="/MOKUSO/config/logout.php" class="btn-ghost" style="width: 100%; justify-content: center;"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
    </div>
</aside>

<main class="main-content-wrapper">
    <div class="main-container">
        
        <header class="page-header">
            <div class="header-title">
                <h1><i class="ph-fill ph-command"></i> Centro de Comando</h1>
                <p>Resumen administrativo del Dojo</p>
            </div>

            <div class="header-controls">
                <div class="quick-actions">
                    <a href="/MOKUSO/alumnos/ver_alumnos.php" class="btn-quick" title="Ver Alumnos">
                        <i class="fas fa-users"></i> <span>Alumnos</span>
                    </a>
                    <a href="/MOKUSO/asistencias/" class="btn-quick" title="Ver Asistencias">
                        <i class="fas fa-calendar-check"></i> <span>Asistencias</span>
                    </a>
                    <a href="/MOKUSO/usuarios/index.php" class="btn-quick" title="Configurar Usuarios">
                        <i class="fas fa-user-cog"></i> <span>Usuarios</span>
                    </a>
                </div>

                <div class="filter-bar">
                    <div class="date-group">
                        <span style="color:var(--text-muted); font-size:0.9rem;">Periodo:</span>
                        <input type="date" id="fecha_inicio" class="date-input" value="<?php echo date('Y-m-01'); ?>">
                        <span style="color:var(--text-muted);">-</span>
                        <input type="date" id="fecha_fin" class="date-input" value="<?php echo date('Y-m-t'); ?>">
                    </div>
                    <button id="btn-aplicar-filtro" class="btn-filter">
                        <i class="ph-bold ph-funnel"></i> Filtrar
                    </button>
                </div>
            </div>
        </header>

        <div id="dashboard-content">
            <div class="loader-container">
                <div class="loader"></div>
                <p style="color:var(--text-muted)">Analizando datos del Dojo...</p>
            </div>
        </div>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
    let chartTendencia = null;
    let chartComposicion = null;

    async function cargarDatos(inicio, fin) {
        const container = document.getElementById('dashboard-content');
        
        try {
            const response = await fetch(`/MOKUSO/dashboard/api_stats.php?inicio=${inicio}&fin=${fin}`);
            if (!response.ok) throw new Error('Error en la red');
            const data = await response.json();
            
            if (data.error) throw new Error(data.message);

            renderDashboard(data, inicio, fin);

        } catch (error) {
            console.error(error);
            container.innerHTML = `
                <div class="widget-card" style="border-color:var(--danger); text-align:center;">
                    <i class="ph-duotone ph-warning" style="font-size:3rem; color:var(--danger);"></i>
                    <h3 style="margin:1rem 0; color:var(--text-white);">Error de Conexión</h3>
                    <p style="color:var(--text-muted)">No se pudo cargar la información estadística.</p>
                    <small style="color:var(--text-muted)">Verifica que el archivo <code>api_stats.php</code> exista.</small>
                </div>`;
        }
    }

    function renderDashboard(data, inicio, fin) {
        const container = document.getElementById('dashboard-content');
        
        // Generar HTML de KPIs
        const kpisHtml = `
            <div class="kpi-grid">
                <a href="/MOKUSO/pagos/index.php" class="kpi-card income">
                    <div class="kpi-top">
                        <span class="kpi-label">Ingresos</span>
                        <i class="ph-fill ph-trend-up kpi-icon" style="color:var(--success)"></i>
                    </div>
                    <span class="kpi-value">${formatter.format(data.kpis.ingresos_periodo)}</span>
                </a>
                <a href="/MOKUSO/pagos/index.php" class="kpi-card expense">
                    <div class="kpi-top">
                        <span class="kpi-label">Gastos</span>
                        <i class="ph-fill ph-trend-down kpi-icon" style="color:var(--danger)"></i>
                    </div>
                    <span class="kpi-value">${formatter.format(data.kpis.gastos_periodo)}</span>
                </a>
                <div class="kpi-card balance">
                    <div class="kpi-top">
                        <span class="kpi-label">Balance Neto</span>
                        <i class="ph-fill ph-scales kpi-icon" style="color:var(--info)"></i>
                    </div>
                    <span class="kpi-value" style="color:${data.kpis.balance_periodo >= 0 ? 'var(--info)' : 'var(--danger)'}">
                        ${formatter.format(data.kpis.balance_periodo)}
                    </span>
                </div>
                <div class="kpi-card students">
                    <div class="kpi-top">
                        <span class="kpi-label">Alumnos Activos</span>
                        <i class="ph-fill ph-users-three kpi-icon" style="color:var(--warning)"></i>
                    </div>
                    <span class="kpi-value" style="color:var(--text-white)">${data.kpis.alumnos_activos}</span>
                </div>
            </div>
        `;

        // Generar HTML del Grid Principal
        const gridHtml = `
            <div class="dashboard-grid">
                <div class="left-col" style="display:flex; flex-direction:column; gap:2rem;">
                    <div class="widget-card">
                        <div class="widget-header">
                            <h3 class="widget-title"><i class="ph-bold ph-chart-line-up"></i> Flujo de Caja</h3>
                        </div>
                        <div style="height:300px;"><canvas id="chartTendencia"></canvas></div>
                    </div>
                    
                    <div class="widget-card">
                        <div class="widget-header">
                            <h3 class="widget-title"><i class="ph-bold ph-chart-pie-slice"></i> Fuentes de Ingreso</h3>
                        </div>
                        <div style="height:250px; display:flex; justify-content:center;"><canvas id="chartComposicion"></canvas></div>
                    </div>
                </div>

                <div class="right-col" style="display:flex; flex-direction:column; gap:2rem;">
                    <div class="widget-card">
                        <div class="widget-header">
                            <h3 class="widget-title" style="font-size:1rem; color:var(--danger);"><i class="ph-fill ph-siren"></i> Riesgo de Abandono</h3>
                            <small class="text-muted">>15 días sin asistir</small>
                        </div>
                        <ul class="student-list">
                            ${data.widgets.alumnos_en_riesgo.slice(0,5).map(a => `
                                <li>
                                    <a href="/MOKUSO/alumnos/index.php?alumno_id=${a.id}" class="student-item">
                                        <div class="student-info">
                                            <strong>${a.nombre} ${a.apellidos}</strong>
                                            <small>Última vez: ${a.ultima_asistencia ? new Date(a.ultima_asistencia + 'T12:00:00Z').toLocaleDateString('es-ES') : 'Nunca'}</small>
                                        </div>
                                        <span class="status-tag tag-danger">ALERTA</span>
                                    </a>
                                </li>
                            `).join('') || '<li style="text-align:center; padding:20px; color:var(--text-muted);">No hay alumnos en riesgo.</li>'}
                        </ul>
                    </div>

                    <div class="widget-card">
                        <div class="widget-header">
                            <h3 class="widget-title" style="font-size:1rem; color:var(--warning);"><i class="ph-fill ph-clock-countdown"></i> Próximos a Vencer</h3>
                        </div>
                        <ul class="student-list">
                            ${data.widgets.vencimientos_proximos.slice(0,5).map(a => `
                                <li>
                                    <a href="/MOKUSO/alumnos/index.php?alumno_id=${a.id}" class="student-item">
                                        <div class="student-info">
                                            <strong>${a.nombre} ${a.apellidos}</strong>
                                            <small style="color:var(--warning);">Vence: ${new Date(a.fecha_vencimiento_membresia + 'T12:00:00Z').toLocaleDateString('es-ES')}</small>
                                        </div>
                                        <i class="ph-bold ph-caret-right" style="color:var(--text-muted)"></i>
                                    </a>
                                </li>
                            `).join('') || '<li style="text-align:center; padding:20px; color:var(--text-muted);">Todo al día.</li>'}
                        </ul>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = kpisHtml + gridHtml;

        // Inicializar Gráficos
        initCharts(data);
    }

    function initCharts(data) {
        const textColor = '#a8a8b3';
        const gridColor = '#323238';

        // 1. TENDENCIA
        if(chartTendencia) chartTendencia.destroy();
        const ctx1 = document.getElementById('chartTendencia').getContext('2d');
        
        const gradGreen = ctx1.createLinearGradient(0,0,0,300);
        gradGreen.addColorStop(0, 'rgba(4, 211, 97, 0.3)'); gradGreen.addColorStop(1, 'rgba(4, 211, 97, 0)');
        
        const gradRed = ctx1.createLinearGradient(0,0,0,300);
        gradRed.addColorStop(0, 'rgba(255, 62, 62, 0.3)'); gradRed.addColorStop(1, 'rgba(255, 62, 62, 0)');

        chartTendencia = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: data.charts.tendencia_financiera.map(i => {
                    const d = new Date(i.fecha + 'T12:00:00Z');
                    return d.toLocaleDateString('es-ES', {day:'numeric', month:'short'});
                }),
                datasets: [
                    { label: 'Ingresos', data: data.charts.tendencia_financiera.map(i=>i.ingresos), borderColor: '#04d361', backgroundColor: gradGreen, fill: true, tension: 0.4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 6 },
                    { label: 'Gastos', data: data.charts.tendencia_financiera.map(i=>i.gastos), borderColor: '#ff3e3e', backgroundColor: gradRed, fill: true, tension: 0.4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 6 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { labels: { color: textColor } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: textColor } },
                    y: { grid: { color: gridColor }, ticks: { color: textColor } }
                }
            }
        });

        // 2. COMPOSICIÓN
        if(chartComposicion) chartComposicion.destroy();
        const ctx2 = document.getElementById('chartComposicion');
        if(ctx2 && data.charts.composicion_ingresos.length > 0) {
            chartComposicion = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: data.charts.composicion_ingresos.map(i => i.concepto),
                    datasets: [{
                        data: data.charts.composicion_ingresos.map(i => i.total),
                        backgroundColor: ['#ff6600', '#04d361', '#00bfff', '#fad733', '#ff3e3e'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'right', labels: { color: textColor, boxWidth: 12 } } 
                    },
                    cutout: '70%'
                }
            });
        }
    }

    document.getElementById('btn-aplicar-filtro').addEventListener('click', () => {
        const ini = document.getElementById('fecha_inicio').value;
        const fin = document.getElementById('fecha_fin').value;
        if(ini && fin) cargarDatos(ini, fin);
    });

    const iniDef = document.getElementById('fecha_inicio').value;
    const finDef = document.getElementById('fecha_fin').value;
    cargarDatos(iniDef, finDef);
});
</script>

</body>
</html>
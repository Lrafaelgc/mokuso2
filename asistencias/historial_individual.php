<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

include '../config/db.php';

// Validar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: lista_asistencias.php");
    exit();
}

$alumno_id = intval($_GET['id']);

// --- 1. DATOS DEL ALUMNO ---
$sql_alumno = "SELECT a.*, g.nombre as grupo_nombre, n.nombre as nivel_nombre 
               FROM alumnos a 
               LEFT JOIN grupos g ON a.grupo_id = g.id 
               LEFT JOIN niveles n ON a.nivel_id = n.id 
               WHERE a.id = ?";
$stmt = $conn->prepare($sql_alumno);
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();

if (!$alumno) {
    echo "Alumno no encontrado.";
    exit();
}

// --- 2. ESTADÍSTICAS GENERALES ---
// Total Histórico
$sql_total = "SELECT COUNT(*) as total FROM asistencias WHERE alumno_id = $alumno_id";
$total_asis = $conn->query($sql_total)->fetch_assoc()['total'];

// Asistencias este mes
$mes_actual = date('Y-m');
$sql_mes = "SELECT COUNT(*) as total FROM asistencias WHERE alumno_id = $alumno_id AND DATE_FORMAT(fecha_asistencia, '%Y-%m') = '$mes_actual'";
$mes_asis = $conn->query($sql_mes)->fetch_assoc()['total'];

// Calcular Promedio Mensual (últimos 6 meses)
$sql_promedio = "SELECT COUNT(*) / 6 as promedio FROM asistencias WHERE alumno_id = $alumno_id AND fecha_asistencia > DATE_SUB(NOW(), INTERVAL 6 MONTH)";
$res_prom = $conn->query($sql_promedio)->fetch_assoc();
$promedio = number_format($res_prom['promedio'], 1);

// --- 3. DATOS PARA LA GRÁFICA (Últimos 12 meses) ---
// Compatible con PHP 5.5
$sql_chart = "SELECT DATE_FORMAT(fecha_asistencia, '%Y-%m') as mes_anio, 
                     DATE_FORMAT(fecha_asistencia, '%M') as nombre_mes, 
                     COUNT(*) as total 
              FROM asistencias 
              WHERE alumno_id = $alumno_id 
              AND fecha_asistencia >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY mes_anio 
              ORDER BY mes_anio ASC";
$res_chart = $conn->query($sql_chart);

$chart_labels = [];
$chart_data = [];

// Configuración de idioma para fechas en PHP si es posible, sino array manual
$meses_es = [
    'January'=>'Ene', 'February'=>'Feb', 'March'=>'Mar', 'April'=>'Abr', 'May'=>'May', 'June'=>'Jun',
    'July'=>'Jul', 'August'=>'Ago', 'September'=>'Sep', 'October'=>'Oct', 'November'=>'Nov', 'December'=>'Dic'
];

while($row = $res_chart->fetch_assoc()) {
    // Convertir nombre de mes a español
    $nombre_mes_ingles = $row['nombre_mes'];
    $nombre_corto = isset($meses_es[$nombre_mes_ingles]) ? $meses_es[$nombre_mes_ingles] : $nombre_mes_ingles;
    
    $chart_labels[] = $nombre_corto . " " . date('y', strtotime($row['mes_anio']));
    $chart_data[] = $row['total'];
}

// --- 4. HISTORIAL COMPLETO (Para la tabla) ---
$sql_historial = "SELECT fecha_asistencia, DATE_FORMAT(fecha_asistencia, '%W') as dia_semana 
                  FROM asistencias 
                  WHERE alumno_id = $alumno_id 
                  ORDER BY fecha_asistencia DESC";
$res_historial = $conn->query($sql_historial);

// Array dias español
$dias_es = [
    'Monday'=>'Lunes', 'Tuesday'=>'Martes', 'Wednesday'=>'Miércoles', 
    'Thursday'=>'Jueves', 'Friday'=>'Viernes', 'Saturday'=>'Sábado', 'Sunday'=>'Domingo'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial - <?php echo htmlspecialchars($alumno['nombre']); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

    <style>
        :root {
            --primary: #ff6600; --bg-main: #121214; --surface: #202024; --border: #323238;
            --text-white: #e1e1e6; --text-gray: #a8a8b3; --success: #04d361; --radius: 16px;
        }
        body { background-color: var(--bg-main); color: var(--text-white); font-family: 'Poppins', sans-serif; margin: 0; }
        
        .main-container { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        
        /* HEADER NAV */
        .nav-header { display: flex; align-items: center; gap: 15px; margin-bottom: 2rem; }
        .btn-back { 
            background: var(--surface); border: 1px solid var(--border); color: var(--text-gray);
            padding: 8px 16px; border-radius: 10px; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.3s;
        }
        .btn-back:hover { border-color: var(--text-white); color: var(--text-white); }

        /* PERFIL HERO */
        .profile-hero {
            background: linear-gradient(135deg, var(--surface) 0%, #2a2a30 100%);
            border: 1px solid var(--border); border-radius: var(--radius); padding: 2rem;
            display: flex; flex-wrap: wrap; align-items: center; gap: 2rem; margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .hero-avatar { 
            width: 120px; height: 120px; border-radius: 50%; object-fit: cover; 
            border: 4px solid var(--bg-main); box-shadow: 0 0 20px rgba(255, 102, 0, 0.2);
        }
        .hero-info h1 { margin: 0; font-family: 'Orbitron', sans-serif; font-size: 2rem; }
        .hero-badges { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; background: rgba(255,255,255,0.1); }
        .badge.rank { color: var(--primary); border: 1px solid var(--primary); background: rgba(255, 102, 0, 0.1); }
        .badge.group { color: #00bfff; border: 1px solid #00bfff; background: rgba(0, 191, 255, 0.1); }

        /* GRID DE ESTADÍSTICAS */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; text-align: center; }
        .stat-value { font-size: 2.5rem; font-weight: 700; color: var(--text-white); display: block; line-height: 1; margin-bottom: 5px; }
        .stat-label { color: var(--text-gray); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }

        /* GRÁFICA Y TABLA (Layout 2 columnas en PC) */
        .content-split { display: grid; grid-template-columns: 1fr; gap: 2rem; }
        @media (min-width: 992px) { .content-split { grid-template-columns: 1fr 1fr; } }

        .content-box { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; }
        .box-title { font-family: 'Orbitron', sans-serif; margin-top: 0; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 10px; }

        /* DataTables Custom Theme */
        table.dataTable { color: var(--text-white) !important; background: transparent !important; border-collapse: collapse !important; width: 100% !important; }
        table.dataTable thead th { color: var(--primary) !important; border-bottom: 1px solid var(--border) !important; }
        table.dataTable tbody td { border-bottom: 1px solid var(--border) !important; padding: 12px !important; }
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: var(--text-gray) !important; margin-top: 10px; }
        .dataTables_wrapper input, .dataTables_wrapper select { background: var(--bg-main); border: 1px solid var(--border); color: white; border-radius: 4px; padding: 4px; }
    </style>
</head>
<body>

<div class="main-container">
    
    <div class="nav-header">
        <a href="lista_asistencias.php" class="btn-back"><i class="ph-bold ph-arrow-left"></i> Volver a Lista</a>
        <span style="color:var(--text-gray)">/ Detalle de Asistencia</span>
    </div>

    <div class="profile-hero">
        <img src="/MOKUSO/assets/img/uploads/<?php echo !empty($alumno['foto_perfil']) ? $alumno['foto_perfil'] : 'default.png'; ?>" class="hero-avatar">
        <div class="hero-info">
            <h1><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></h1>
            <div class="hero-badges">
                <span class="badge rank"><i class="ph-fill ph-medal"></i> <?php echo htmlspecialchars($alumno['nivel_nombre'] ?: 'Sin Grado'); ?></span>
                <span class="badge group"><i class="ph-fill ph-users"></i> <?php echo htmlspecialchars($alumno['grupo_nombre'] ?: 'General'); ?></span>
                <span class="badge" style="background: <?php echo ($alumno['estado_membresia'] == 'activa') ? 'var(--success)' : '#ff3e3e'; ?>; color: #000;">
                    <?php echo strtoupper($alumno['estado_membresia']); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-value" style="color:var(--primary)"><?php echo $total_asis; ?></span>
            <span class="stat-label">Total Clases</span>
        </div>
        <div class="stat-card">
            <span class="stat-value" style="color:#00bfff"><?php echo $mes_asis; ?></span>
            <span class="stat-label">Este Mes</span>
        </div>
        <div class="stat-card">
            <span class="stat-value" style="color:var(--success)"><?php echo $promedio; ?></span>
            <span class="stat-label">Promedio / Mes</span>
        </div>
    </div>

    <div class="content-split">
        
        <div class="content-box">
            <h3 class="box-title"><i class="ph-bold ph-chart-bar"></i> Rendimiento Anual</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <div class="content-box">
            <h3 class="box-title"><i class="ph-bold ph-clock-counter-clockwise"></i> Bitácora de Fechas</h3>
            <table id="historyTable" class="display">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Día</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($asis = $res_historial->fetch_assoc()): 
                        $dia_ing = $asis['dia_semana'];
                        $dia_esp = isset($dias_es[$dia_ing]) ? $dias_es[$dia_ing] : $dia_ing;
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo date("d/m/Y", strtotime($asis['fecha_asistencia'])); ?></td>
                        <td style="color:var(--text-gray)"><?php echo $dia_esp; ?></td>
                        <td><span style="color:var(--success); font-size:0.8rem;"><i class="ph-fill ph-check-circle"></i> Asistió</span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
    // 1. DataTables
    $(document).ready( function () {
        $('#historyTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
            pageLength: 7,
            lengthChange: false, // Ocultar selector de cantidad para ahorrar espacio
            dom: 'tp', // Solo mostrar Tabla (t) y Paginación (p), ocultar search default
            ordering: false // Ya viene ordenado por SQL
        });
    });

    // 2. Chart.js
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    // Gradiente bonito
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(255, 102, 0, 0.5)');
    gradient.addColorStop(1, 'rgba(255, 102, 0, 0.0)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Clases Asistidas',
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: gradient,
                borderColor: '#ff6600',
                borderWidth: 2,
                borderRadius: 4,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { 
                    backgroundColor: '#202024', titleColor: '#fff', bodyColor: '#fff', 
                    borderColor: '#323238', borderWidth: 1 
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#323238' },
                    ticks: { color: '#a8a8b3', stepSize: 1 }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#e1e1e6' }
                }
            }
        }
    });
</script>

</body>
</html>
<?php $conn->close(); ?>
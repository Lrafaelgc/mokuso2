<?php
session_start();
// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

include '../config/db.php';

// --- KPI: Cálculos Rápidos para el Encabezado ---
$hoy = date('Y-m-d');
$mes_actual = date('m');
$ano_actual = date('Y');

// 1. Asistencias de HOY
$sql_hoy = "SELECT COUNT(*) as total FROM asistencias WHERE fecha_asistencia = '$hoy'";
$res_hoy = $conn->query($sql_hoy)->fetch_assoc();
$kpi_hoy = $res_hoy['total'];

// 2. Alumnos "En Riesgo" (Más de 15 días sin venir)
$hace_15_dias = date('Y-m-d', strtotime('-15 days'));
$sql_riesgo = "SELECT COUNT(*) as total FROM alumnos 
               WHERE estado_membresia = 'activa' 
               AND (fecha_ultima_inactividad IS NULL OR fecha_ultima_inactividad < '$hace_15_dias')
               AND id NOT IN (SELECT alumno_id FROM asistencias WHERE fecha_asistencia >= '$hace_15_dias')";
$res_riesgo = $conn->query($sql_riesgo)->fetch_assoc();
$kpi_riesgo = $res_riesgo['total'];

// --- Consulta Principal ---
$hace_30_dias = date('Y-m-d', strtotime('-30 days'));

$sql_tabla = "SELECT 
                a.id, a.nombre, a.apellidos, a.foto_perfil, a.grupo_id, g.nombre as grupo_nombre,
                (SELECT COUNT(ast.id) FROM asistencias ast WHERE ast.alumno_id = a.id AND ast.fecha_asistencia BETWEEN '$hace_30_dias' AND '$hoy') as asistencias_mes,
                (SELECT MAX(ast2.fecha_asistencia) FROM asistencias ast2 WHERE ast2.alumno_id = a.id) as ultima_asistencia
              FROM alumnos a 
              LEFT JOIN grupos g ON a.grupo_id = g.id
              WHERE a.estado_membresia IN ('activa', 'exento', 'pendiente')
              ORDER BY ultima_asistencia DESC"; // Ordenar por los que vinieron recientemente

$result_alumnos = $conn->query($sql_tabla);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Asistencias - Mokuso Elite</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

    <style>
        /* --- TEMA MOKUSO ELITE --- */
        :root {
            --primary: #ff6600; 
            --primary-glow: rgba(255, 102, 0, 0.25);
            --bg-main: #121214;
            --surface: #202024;
            --border: #323238;
            --text-white: #e1e1e6;
            --text-gray: #a8a8b3;
            --success: #04d361;
            --danger: #ff3e3e;
            --warning: #fad733;
            --radius: 12px;
        }

        body {
            background-color: var(--bg-main);
            font-family: 'Poppins', sans-serif;
            color: var(--text-white);
            margin: 0; padding: 0;
            background-image: radial-gradient(circle at 90% 10%, rgba(4, 211, 97, 0.05) 0%, transparent 40%);
        }

        .main-container { padding: 2rem; max-width: 1400px; margin: 0 auto; }

        /* HEADER & KPIS */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .page-header h1 { font-family: 'Orbitron', sans-serif; color: var(--text-white); margin: 0; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--success); }

        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .kpi-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 1.2rem;
            display: flex; align-items: center; gap: 1rem; position: relative; overflow: hidden;
        }
        .kpi-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--primary); }
        .kpi-card.success::before { background: var(--success); }
        .kpi-card.danger::before { background: var(--danger); }
        
        .kpi-icon { width: 45px; height: 45px; background: rgba(255,255,255,0.05); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .kpi-data h3 { margin: 0; font-size: 1.8rem; font-weight: 700; line-height: 1; }
        .kpi-data p { margin: 0; font-size: 0.85rem; color: var(--text-gray); }

        /* TABLA CUSTOM (Dark Glass) */
        .table-wrapper {
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
            padding: 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.3); overflow-x: auto;
        }

        table.dataTable { 
            width: 100% !important; background: transparent !important; 
            border-collapse: collapse !important; border-bottom: none !important; color: var(--text-white) !important;
        }
        
        /* Encabezados */
        table.dataTable thead th {
            background: rgba(0,0,0,0.2) !important; color: var(--text-gray) !important;
            font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;
            border-bottom: 1px solid var(--border) !important; padding: 1rem !important;
        }

        /* Celdas */
        table.dataTable tbody td {
            background: transparent !important; border-bottom: 1px solid var(--border) !important;
            padding: 1rem !important; vertical-align: middle;
        }
        
        /* Hover Fila */
        table.dataTable tbody tr:hover td { background: rgba(255,255,255,0.02) !important; }

        /* Elementos Internos de la Tabla */
        .student-flex { display: flex; align-items: center; gap: 12px; }
        .table-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border); }
        
        .progress-bar-container { width: 100px; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; margin-top: 5px; }
        .progress-bar-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
        
        /* DataTables Controls Overrides */
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter, 
        .dataTables_wrapper .dataTables_info, 
        .dataTables_wrapper .dataTables_paginate { color: var(--text-gray) !important; margin-bottom: 1rem; }

        .dataTables_wrapper .dataTables_filter input {
            background: var(--bg-main); border: 1px solid var(--border); color: var(--text-white);
            border-radius: 8px; padding: 6px 12px; outline: none;
        }
        .dataTables_wrapper .dataTables_length select {
            background: var(--bg-main); border: 1px solid var(--border); color: var(--text-white);
            border-radius: 6px; padding: 4px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-white) !important; background: transparent !important; border: 1px solid var(--border) !important;
            border-radius: 6px !important; margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; font-weight: bold;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: rgba(255,255,255,0.1) !important; border-color: var(--text-white) !important; color: white !important;
        }

        /* Botones Acción */
        .btn-action {
            width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 8px; color: var(--text-gray); border: 1px solid var(--border); transition: all 0.2s;
        }
        .btn-action:hover { color: var(--text-white); border-color: var(--primary); background: rgba(255, 102, 0, 0.1); }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--success), #00b34d); color: #000;
            padding: 10px 20px; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(4, 211, 97, 0.3); }

        .btn-secondary {
            background: transparent; border: 1px solid var(--border); color: var(--text-gray);
            padding: 10px 20px; border-radius: 10px; font-weight: 600; text-decoration: none;
        }
        .btn-secondary:hover { border-color: var(--text-white); color: var(--text-white); }

        /* Badge estado */
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
    </style>
</head>
<body>

<div class="main-container">
    
    <div class="page-header">
        <h1><i class="ph-fill ph-calendar-check"></i> Centro de Asistencia</h1>
        <div style="display:flex; gap:10px;">
            <a href="/MOKUSO/dashboard/index.php" class="btn-secondary"><i class="ph-bold ph-arrow-left"></i> Inicio</a>
            <a href="tomar_asistencia.php" class="btn-primary"><i class="ph-bold ph-check-circle"></i> Tomar Lista Hoy</a>
        </div>
    </div>

    <div class="kpi-row">
        <div class="kpi-card success">
            <div class="kpi-icon" style="color:var(--success)"><i class="ph-fill ph-users-three"></i></div>
            <div class="kpi-data">
                <h3><?php echo $kpi_hoy; ?></h3>
                <p>Asistencias Hoy</p>
            </div>
        </div>
        <div class="kpi-card danger">
            <div class="kpi-icon" style="color:var(--danger)"><i class="ph-fill ph-warning"></i></div>
            <div class="kpi-data">
                <h3><?php echo $kpi_riesgo; ?></h3>
                <p>En Riesgo (>15 días)</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color:var(--primary)"><i class="ph-fill ph-calendar"></i></div>
            <div class="kpi-data">
                <h3 style="font-size: 1.2rem;"><?php echo date('d M'); ?></h3>
                <p>Fecha Actual</p>
            </div>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="attendanceTable" class="display">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Grupo</th>
                    <th>Frecuencia (30 días)</th>
                    <th>Última Visita</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result_alumnos->fetch_assoc()): 
                    // Cálculos para la barra de progreso
                    // Supongamos que 12 clases al mes es el 100% (3 por semana)
                    $porcentaje = min(100, ($row['asistencias_mes'] / 12) * 100);
                    
                    // Color de la barra
                    $barColor = 'var(--danger)';
                    if($porcentaje > 30) $barColor = 'var(--warning)';
                    if($porcentaje > 60) $barColor = 'var(--success)';

                    // Cálculo días inactivo
                    $dias_sin_venir = 999;
                    if($row['ultima_asistencia']) {
                        $diff = date_diff(date_create($row['ultima_asistencia']), date_create($hoy));
                        $dias_sin_venir = $diff->format("%a");
                    }
                    
                    $foto = !empty($row['foto_perfil']) ? $row['foto_perfil'] : 'default.png';
                ?>
                <tr>
                    <td>
                        <div class="student-flex">
                            <img src="/MOKUSO/assets/img/uploads/<?php echo $foto; ?>" class="table-avatar" onerror="this.src='/MOKUSO/assets/img/uploads/default.png'">
                            <div>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($row['nombre'].' '.$row['apellidos']); ?></div>
                                <?php if($dias_sin_venir > 15 && $dias_sin_venir < 900): ?>
                                    <span style="font-size:0.7rem; color:var(--danger); display:flex; align-items:center; gap:3px;">
                                        <i class="ph-fill ph-warning-circle"></i> Ausente <?php echo $dias_sin_venir; ?> días
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--text-gray);"><?php echo htmlspecialchars($row['grupo_nombre'] ?: 'General'); ?></td>
                    <td>
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:2px;">
                            <span><?php echo $row['asistencias_mes']; ?> clases</span>
                            <span style="color:var(--text-gray)"><?php echo round($porcentaje); ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?php echo $porcentaje; ?>%; background: <?php echo $barColor; ?>;"></div>
                        </div>
                    </td>
                    <td>
                        <?php if($row['ultima_asistencia']): ?>
                            <span style="font-weight:500;"><?php echo date("d M, Y", strtotime($row['ultima_asistencia'])); ?></span>
                            <?php if($row['ultima_asistencia'] == $hoy): ?>
                                <span style="font-size:0.7rem; color:var(--success); background:rgba(4,211,97,0.1); padding:2px 6px; border-radius:4px; margin-left:5px;">HOY</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:var(--text-gray); font-style:italic;">Nunca</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex; gap:5px;">
                            <a href="/MOKUSO/alumnos/perfil.php?alumno_id=<?php echo $row['id']; ?>" class="btn-action" title="Ver Perfil">
                                <i class="ph-bold ph-user"></i>
                            </a>
                            <a href="historial_individual.php?id=<?php echo $row['id']; ?>" class="btn-action" title="Historial Detallado">
                                <i class="ph-bold ph-clock-counter-clockwise"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready( function () {
        $('#attendanceTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
            },
            responsive: true,
            order: [[ 3, "desc" ]], // Ordenar por fecha (columna 3) descendente
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
            dom: '<"dt-header"f>rt<"dt-footer"p>', // Layout simple
            initComplete: function() {
                // Personalización extra del input de búsqueda
                $('.dataTables_filter input').attr('placeholder', 'Buscar alumno...');
            }
        });
    });
</script>

</body>
</html>

<?php $conn->close(); ?>
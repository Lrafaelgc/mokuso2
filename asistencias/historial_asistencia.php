<?php
// Se asume que session_start() está en header.php
include '../templates/header.php';
include '../config/db.php';

// Validar que se haya pasado un ID de alumno
$alumno_id = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;
if ($alumno_id <= 0) {
    header("Location: lista_asistencias.php");
    exit();
}

// --- CÁLCULOS PARA KPIs Y TABLA ---
// Obtener información del alumno
$stmt_alumno = $conn->prepare("SELECT nombre, apellidos FROM alumnos WHERE id = ?");
$stmt_alumno->bind_param("i", $alumno_id);
$stmt_alumno->execute();
$alumno = $stmt_alumno->get_result()->fetch_assoc();
if (!$alumno) { echo "Alumno no encontrado."; exit; }
$nombre_completo = htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']);

// Obtener todas las asistencias para la tabla
$stmt_asistencias = $conn->prepare("SELECT id, fecha_asistencia FROM asistencias WHERE alumno_id = ? ORDER BY fecha_asistencia DESC");
$stmt_asistencias->bind_param("i", $alumno_id);
$stmt_asistencias->execute();
$asistencias_result = $stmt_asistencias->get_result();
$asistencias = [];
while($row = $asistencias_result->fetch_assoc()) {
    $asistencias[] = $row;
}

// Calcular KPIs
$kpi_total_asistencias = count($asistencias);
$kpi_primera_asistencia = $kpi_total_asistencias > 0 ? end($asistencias)['fecha_asistencia'] : null;
$kpi_ultima_asistencia = $kpi_total_asistencias > 0 ? $asistencias[0]['fecha_asistencia'] : null;

// Calcular Frecuencia en los últimos 90 días
$hace_90_dias = date('Y-m-d', strtotime('-90 days'));
$stmt_frecuencia = $conn->prepare("SELECT COUNT(id) as total FROM asistencias WHERE alumno_id = ? AND fecha_asistencia >= ?");
$stmt_frecuencia->bind_param("is", $alumno_id, $hace_90_dias);
$stmt_frecuencia->execute();
$asistencias_90_dias = $stmt_frecuencia->get_result()->fetch_assoc()['total'];
// Hay aprox. 12.8 semanas en 90 días.
$frecuencia_semanal = ($asistencias_90_dias > 0) ? round($asistencias_90_dias / 12.8, 1) : 0;

$frecuencia_texto = 'Esporádica';
$frecuencia_clase = 'warning';
if ($frecuencia_semanal >= 2) {
    $frecuencia_texto = 'Consistente';
    $frecuencia_clase = 'success';
} elseif ($frecuencia_semanal >= 1) {
    $frecuencia_texto = 'Regular';
    $frecuencia_clase = 'info';
}

// DATOS PARA EL GRÁFICO DE BARRAS (asistencias por mes en el último año)
$hace_un_ano = date('Y-m-d', strtotime('-1 year'));
$stmt_chart = $conn->prepare("
    SELECT DATE_FORMAT(fecha_asistencia, '%Y-%m') as mes, COUNT(id) as total 
    FROM asistencias 
    WHERE alumno_id = ? AND fecha_asistencia >= ?
    GROUP BY mes ORDER BY mes ASC
");
$stmt_chart->bind_param("is", $alumno_id, $hace_un_ano);
$stmt_chart->execute();
$chart_result = $stmt_chart->get_result();
$chart_data = [];
while($row = $chart_result->fetch_assoc()) {
    $chart_data[] = $row;
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap5.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

<style>
    :root {
        --color-primary: #8a9ce6; --color-success: #38c172; --color-danger: #e3342f;
        --color-warning: #f0b400; --color-info: #3498db; --color-surface: #2c2f33;
        --color-background: #23272a; --color-text: #dcddde; --color-text-muted: #99aab5;
        --color-border: #40444b;
    }
    body { background-color: var(--color-background); color: var(--color-text); font-family: 'Poppins', sans-serif; }
    .profile-header { background-color: var(--color-surface); padding: 2rem 2.5rem; border-radius: 16px; margin-bottom: 2.5rem; }
    .profile-header h1 { font-weight: 700; font-size: 2.5rem; margin-bottom: 0.5rem; }
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 2rem; }
    .kpi-item { background-color: var(--color-background); padding: 1rem; border-radius: 10px; }
    .kpi-item .kpi-title { font-size: 0.9rem; color: var(--color-text-muted); display: block; margin-bottom: 0.5rem; }
    .kpi-item .kpi-value { font-size: 1.5rem; font-weight: 600; }
    .kpi-value.success { color: var(--color-success); }
    .kpi-value.warning { color: var(--color-warning); }
    .kpi-value.info { color: var(--color-info); }
    
    .content-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 2rem; }
    .chart-container { grid-column: span 12; background-color: var(--color-surface); padding: 2.5rem; border-radius: 16px; }
    .data-table-container { grid-column: span 12; background-color: var(--color-surface); padding: 2.5rem; border-radius: 16px; box-shadow: 0 12px 40px rgba(0,0,0,0.2); }
    @media (min-width: 1200px) {
        .chart-container { grid-column: span 5; }
        .data-table-container { grid-column: span 7; }
    }
</style>

<div class="container my-5">
    <div class="profile-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1><?php echo $nombre_completo; ?></h1>
            <a href="lista_asistencias.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Volver al Resumen</a>
        </div>
        <p class="text-primary fs-5">Perfil de Asistencia</p>

        <div class="kpi-grid">
            <div class="kpi-item"><span class="kpi-title">Total de Asistencias</span><span class="kpi-value"><?php echo $kpi_total_asistencias; ?></span></div>
            <div class="kpi-item"><span class="kpi-title">Primera Asistencia</span><span class="kpi-value"><?php echo $kpi_primera_asistencia ? date("d/m/Y", strtotime($kpi_primera_asistencia)) : 'N/A'; ?></span></div>
            <div class="kpi-item"><span class="kpi-title">Asistencia Reciente</span><span class="kpi-value"><?php echo $kpi_ultima_asistencia ? date("d/m/Y", strtotime($kpi_ultima_asistencia)) : 'N/A'; ?></span></div>
            <div class="kpi-item"><span class="kpi-title">Frecuencia (Últ. 90 días)</span><span class="kpi-value <?php echo $frecuencia_clase; ?>"><?php echo $frecuencia_texto; ?></span></div>
        </div>
    </div>

    <div class="content-grid">
        <div class="chart-container">
            <h3 class="mb-4">Asistencias por Mes (Último Año)</h3>
            <canvas id="attendanceChart" style="height: 350px;"></canvas>
        </div>
        <div class="data-table-container">
            <h3 class="mb-4">Registro Detallado</h3>
            <table id="historialTable" class="table table-hover table-dark table-striped">
                <thead>
                    <tr>
                        <th>Fecha de Asistencia</th>
                        <th>Día de la Semana</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($asistencias as $asistencia): ?>
                    <tr>
                        <td><strong><?php echo date("d F, Y", strtotime($asistencia['fecha_asistencia'])); ?></strong></td>
                        <td><?php echo ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][date('w', strtotime($asistencia['fecha_asistencia']))]; ?></td>
                        <td class="action-buttons text-end">
                            <a href="#" class="delete-btn" data-id="<?php echo $asistencia['id']; ?>" title="Eliminar Registro"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.0.7/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
    var table = $('#historialTable').DataTable({ /* ... configuración de DataTables ... */ });
    $('#historialTable').on('click', '.delete-btn', function(e) { /* ... lógica de borrado AJAX ... */ });

    // --- LÓGICA DEL GRÁFICO DE BARRAS ---
    const chartData = <?php echo json_encode($chart_data); ?>;
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    // Preparar las etiquetas de los últimos 12 meses
    const labels = [];
    const dataPoints = [];
    const date = new Date();
    date.setDate(1); // Empezar desde el primer día del mes actual

    for (let i = 0; i < 12; i++) {
        const monthLabel = date.toLocaleString('es-ES', { month: 'short', year: '2-digit' });
        const monthKey = date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2);
        labels.unshift(monthLabel); // Añadir al principio para ordenar del más antiguo al más nuevo

        const dataPoint = chartData.find(d => d.mes === monthKey);
        dataPoints.unshift(dataPoint ? dataPoint.total : 0);

        date.setMonth(date.getMonth() - 1);
    }
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Asistencias',
                data: dataPoints,
                backgroundColor: 'rgba(138, 156, 230, 0.6)',
                borderColor: 'var(--color-primary)',
                borderWidth: 2,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { color: '#ccc', stepSize: 1 }, grid: { color: 'rgba(255, 255, 255, 0.1)' } },
                x: { ticks: { color: '#ccc' }, grid: { display: false } }
            }
        }
    });
});
</script>
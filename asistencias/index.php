<?php
session_start();
// Security check: Only allow teachers or admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

include '../config/db.php';
include '../templates/header.php'; // Incluir el header que contiene <html>, <head>, fonts, icons, background

// --- KPI Calculations ---
$hoy = date('Y-m-d');
$stmt_hoy = $conn->prepare("SELECT COUNT(id) as total FROM asistencias WHERE fecha_asistencia = ?");
$stmt_hoy->bind_param("s", $hoy);
$stmt_hoy->execute();
$result_hoy = $stmt_hoy->get_result()->fetch_assoc();
$kpi_asistencias_hoy = isset($result_hoy['total']) ? $result_hoy['total'] : 0;

$result_activos = $conn->query("SELECT COUNT(id) as total FROM alumnos WHERE estado_membresia IN ('activa', 'exento')")->fetch_assoc();
$kpi_total_activos = isset($result_activos['total']) ? $result_activos['total'] : 0;

$kpi_porcentaje_hoy = ($kpi_total_activos > 0) ? round(($kpi_asistencias_hoy / $kpi_total_activos) * 100) : 0;

// --- LÓGICA CORREGIDA: Obtener asistencias de HOY y agruparlas por GRUPO ---
$asistencias_hoy_por_grupo = [];
$sql_asistencia_hoy = "SELECT 
                           alu.nombre AS alumno_nombre,
                           alu.apellidos AS alumno_apellidos,
                           g.nombre AS grupo_nombre
                       FROM asistencias ast
                       JOIN alumnos alu ON ast.alumno_id = alu.id
                       LEFT JOIN grupos g ON alu.grupo_id = g.id
                       WHERE ast.fecha_asistencia = ?
                       ORDER BY g.nombre, alu.apellidos, alu.nombre";
$stmt_asistencia_hoy = $conn->prepare($sql_asistencia_hoy);
$stmt_asistencia_hoy->bind_param("s", $hoy);
$stmt_asistencia_hoy->execute();
$result_asistencia_hoy = $stmt_asistencia_hoy->get_result();

if ($result_asistencia_hoy) {
    while ($row = $result_asistencia_hoy->fetch_assoc()) {
        // --- ESTA ES LA LÍNEA CORREGIDA ---
        $grupo = isset($row['grupo_nombre']) ? $row['grupo_nombre'] : 'Sin Grupo Asignado';
        $asistencias_hoy_por_grupo[$grupo][] = $row;
    }
}


// Get student list for the modal
$lista_alumnos_modal_result = $conn->query("SELECT id, nombre, apellidos FROM alumnos WHERE estado_membresia IN ('activa', 'exento') ORDER BY apellidos, nombre ASC");
$lista_alumnos_modal = [];
if ($lista_alumnos_modal_result) {
    while ($row = $lista_alumnos_modal_result->fetch_assoc()) {
        $lista_alumnos_modal[] = $row;
    }
}
?>

<style>
    /* --- ESTILOS "DIGITAL DOJO" PARA EL CENTRO DE ASISTENCIAS --- */
    /* :root, body, background-blur, sidebar, etc. se heredan de header.php */

    .main-content-wrapper { margin-left: 260px; width: calc(100% - 260px); position: relative; }
    .main-container {
        width: 100%; max-width: 1200px; margin: 0 auto;
        padding: 2rem 1.5rem; position: relative; z-index: 1;
    }
    .page-header h1 {
        font-family: 'Orbitron', sans-serif; color: var(--color-primary);
        font-size: 2.5rem; margin: 0 0 2.5rem 0; text-align: center;
        letter-spacing: 2px; text-shadow: 0 0 18px var(--color-primary-glow);
    }

    .kpi-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); margin-bottom: 2.5rem; }
    .kpi-card { background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--border-radius); box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur)); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; text-decoration: none; transition: transform 0.3s, box-shadow 0.3s; position: relative; overflow: hidden; }
    .kpi-card::before { content: ''; position: absolute; top: -50px; left: -50px; width: 120px; height: 120px; background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%); transition: transform 0.5s; }
    .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 10px 32px var(--color-primary-glow); }
    .kpi-card:hover::before { transform: scale(1.5); }
    .kpi-icon { font-size: 2.5rem; padding: 0.5rem; flex-shrink: 0; color: var(--color-primary); }
    .kpi-text .kpi-title { color: var(--color-text-muted); text-transform: uppercase; font-size: 0.9rem; margin-bottom: 0.25rem; }
    .kpi-text .kpi-value { font-size: 2.2rem; font-weight: 700; line-height: 1.2; color: var(--color-text-light); }
    .kpi-card.info .kpi-icon { color: var(--color-secondary); } .kpi-card.info .kpi-value { color: var(--color-secondary); }
    .kpi-card.success .kpi-icon { color: var(--color-success); } .kpi-card.success .kpi-value { color: var(--color-success); }
    .kpi-card.neutral .kpi-icon { color: var(--color-text-muted); }

    .action-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-bottom: 2.5rem; }
    .action-card { background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--border-radius); box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur)); padding: 1.5rem; text-align: center; text-decoration: none; color: var(--color-text-light); transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 150px; }
    .action-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px var(--color-primary-glow); border-color: var(--color-primary); }
    .action-card i { font-size: 2.5rem; margin-bottom: 1rem; color: var(--color-primary); transition: color 0.3s; }
    .action-card:hover i { color: var(--color-secondary); }
    .action-card span { font-size: 1.1rem; font-weight: 600; }
    .action-card.modal-trigger { cursor: pointer; }

    .widget-card { background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--border-radius); box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur)); padding: 1.5rem; margin-bottom: 2rem; }
    .widget-header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 1rem; margin-bottom: 1.5rem; border-bottom: 1.5px solid var(--color-border); }
    .widget-header h3 { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; margin: 0; }
    .widget-list { list-style: none; padding: 0; margin: 0; }
    .widget-list li:last-child { margin-bottom: 0; }
    .widget-list .list-item { display: flex; justify-content: space-between; align-items: center; padding: 0.9rem 1rem; border-radius: 12px; background-color: rgba(0,0,0,0.1); margin-bottom: 0.5rem; }
    .widget-list .list-text { flex-grow: 1; margin-right: 1rem; }
    .widget-list .list-label { font-weight: 500; display: block; }
    .widget-list .list-meta { text-align: right; flex-shrink: 0; }
    .widget-list .muted { color: var(--color-text-muted); font-size: 0.9em; }

    /* --- NUEVO ESTILO PARA ENCABEZADOS DE GRUPO --- */
    .list-group-header {
        font-family: 'Orbitron', sans-serif;
        color: var(--color-primary);
        font-size: 1.1rem;
        font-weight: 600;
        padding: 1rem 1rem 0.5rem;
        margin-top: 1rem;
        border-bottom: 1px solid var(--color-border);
        margin-bottom: 0.75rem;
    }
    .list-group-header:first-child { margin-top: 0; }

    /* --- BOTONES Y MODAL --- */
    .btn-primary, .btn-secondary { font-weight: 600; padding: 0.6rem 1.2rem; border-radius: 10px; transition: all 0.2s; }
    .btn-primary { background-color: var(--color-primary); border-color: var(--color-primary); color: #101012; }
    .btn-primary:hover { background-color: #e07b00; border-color: #e07b00; transform: translateY(-1px); }
    .btn-secondary { background-color: transparent; border: 2px solid var(--color-border); color: var(--color-text-muted); }
    .btn-secondary:hover { color: var(--color-text-light); border-color: var(--color-text-muted); background-color: rgba(255,255,255,0.05); }
    .btn-ghost { background: transparent; border: 2px solid var(--color-border); color: var(--color-text-muted); padding: 0.75rem 1.2rem; font-size: 0.9rem; font-weight: 600; border-radius: 10px; text-decoration: none; transition: color 0.3s, border-color 0.3s, transform 0.2s; }
    .btn-ghost:hover { color: var(--color-primary); border-color: var(--color-primary); transform: translateY(-2px); }
    .modal { position: fixed; top: 0; left: 0; z-index: 1055; display: none; width: 100%; height: 100%; overflow-x: hidden; overflow-y: auto; outline: 0; background-color: rgba(10, 10, 15, 0.7); }
    .modal.fade { transition: opacity 0.3s ease; opacity: 0; } .modal.show { display: block; opacity: 1; }
    .modal-dialog { position: relative; width: auto; margin: 0.5rem; pointer-events: none; }
    @media (min-width: 576px) { .modal-dialog { max-width: 500px; margin: 1.75rem auto; } }
    .modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    @media (min-width: 576px) { .modal-dialog-centered { min-height: calc(100% - 3.5rem); } }
    .modal-content { pointer-events: auto; background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--border-radius); box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur));}
    .modal-header { border-bottom: 1.5px solid var(--color-border); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
    .modal-header .modal-title { color: var(--color-primary); font-family: 'Orbitron', sans-serif; font-size: 1.5rem; text-shadow: 0 0 10px var(--color-primary-glow); margin: 0; }
    .btn-close { background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23a0a0a0'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat; opacity: 0.7; transition: opacity 0.3s, transform 0.3s; border: none; padding: 0.5rem; }
    .btn-close:hover { opacity: 1; transform: scale(1.1); }
    .modal-body { padding: 1.5rem; }
    .modal-footer { border-top: 1.5px solid var(--color-border); padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.5rem; }
    .form-label { color: var(--color-text-muted); margin-bottom: 0.5rem; font-weight: 500; }
    .form-control, .form-select { background: rgba(0,0,0,0.2); border: 1.5px solid var(--color-border); border-radius: 10px; color: var(--color-text-light); padding: 0.75rem 1rem; width: 100%; transition: border-color 0.2s, box-shadow 0.2s; }
    .form-control:focus, .form-select:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); background: rgba(0,0,0,0.3); }
    .form-select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23a0a0a0'%3E%3Cpath fill-rule='evenodd' d='M8 11.646l-4.854-4.853.708-.708L8 10.23l4.146-4.145.708.708L8 11.646z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.7rem center; background-size: 1em; padding-right: 2.5rem; }
    input[type="date"] { color-scheme: dark; }
    input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.8); cursor: pointer; }
    
    @media (max-width: 992px) { .sidebar { display: none; } .main-content-wrapper { margin-left: 0; width: 100%; } }
</style>

<div class="main-container">
    <div class="page-header">
        <h1>Centro de Asistencias</h1>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card info"><div class="kpi-icon"><i class="fas fa-calendar-day"></i></div><div class="kpi-text"><div class="kpi-title">Asistencias Hoy</div><div class="kpi-value"><?php echo $kpi_asistencias_hoy; ?></div></div></div>
        <div class="kpi-card success"><div class="kpi-icon"><i class="fas fa-percentage"></i></div><div class="kpi-text"><div class="kpi-title">% Participación Hoy</div><div class="kpi-value"><?php echo $kpi_porcentaje_hoy; ?>%</div></div></div>
        <div class="kpi-card neutral"><div class="kpi-icon"><i class="fas fa-users"></i></div><div class="kpi-text"><div class="kpi-title">Alumnos Activos</div><div class="kpi-value"><?php echo $kpi_total_activos; ?></div></div></div>
    </div>

    <div class="action-grid">
        <a href="lista_asistencias.php" class="action-card"><i class="fas fa-list-ul"></i><span>Ver Lista Completa</span></a>
        <a href="tomar_asistencia.php" class="action-card"><i class="fas fa-check-circle"></i><span>Tomar Asistencia Hoy</span></a>
        <div class="action-card modal-trigger" data-bs-toggle="modal" data-bs-target="#addAttendanceModal"><i class="fas fa-plus-circle"></i><span>Añadir Registro Manual</span></div>
    </div>

    <div class="widget-card">
        <div class="widget-header">
            <h3>Asistencia de Hoy</h3>
            <a href="lista_asistencias.php" class="btn-ghost">Ver Todo</a>
        </div>
        <ul class="widget-list">
             <?php if (!empty($asistencias_hoy_por_grupo)): ?>
                <?php foreach($asistencias_hoy_por_grupo as $nombre_grupo => $alumnos_del_grupo): ?>
                    <li class="list-group-header"><?php echo htmlspecialchars($nombre_grupo); ?></li>
                    <?php foreach($alumnos_del_grupo as $alumno_asistencia): ?>
                        <li>
                            <div class="list-item">
                                <span class="list-text">
                                    <span class="list-label"><?php echo htmlspecialchars($alumno_asistencia['alumno_nombre'] . ' ' . $alumno_asistencia['alumno_apellidos']); ?></span>
                                </span>
                                <span class="list-meta muted">
                                    <i class="fas fa-check-circle text-success"></i> Presente
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                 <li><span class="muted p-2">Aún no hay asistencias registradas hoy.</span></li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Modal para Añadir Registro Manual -->
<div class="modal fade" id="addAttendanceModal" tabindex="-1" aria-labelledby="addAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="lista_asistencias.php" autocomplete="off"> 
                <div class="modal-header">
                    <h5 class="modal-title" id="addAttendanceModalLabel">Añadir Registro Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_alumno_id" class="form-label">Alumno</label>
                        <select class="form-select" id="modal_alumno_id" name="alumno_id" required>
                            <option value="" disabled selected>Selecciona un alumno...</option>
                            <?php foreach ($lista_alumnos_modal as $alumno_modal): ?>
                                <option value="<?php echo htmlspecialchars($alumno_modal['id']); ?>">
                                    <?php echo htmlspecialchars($alumno_modal['nombre'] . ' ' . $alumno_modal['apellidos']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="modal_fecha_asistencia" class="form-label">Fecha de Asistencia</label>
                        <input type="date" class="form-control" id="modal_fecha_asistencia" name="fecha_asistencia" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" name="guardar_asistencia"><i class="fas fa-save me-1"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> 

<?php include '../templates/footer.php'; ?>

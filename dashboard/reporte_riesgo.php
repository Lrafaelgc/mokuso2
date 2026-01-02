<?php 
include '../templates/header.php';
include '../config/db.php';

// CONSULTA ACTUALIZADA: Ahora también obtenemos teléfono y dirección
$sql = "SELECT a.id, a.nombre, a.apellidos, a.telefono, a.direccion,
               MAX(ast.fecha_asistencia) as ultima_asistencia, 
               IF(MAX(ast.fecha_asistencia) IS NOT NULL, DATEDIFF(CURDATE(), MAX(ast.fecha_asistencia)), 9999) as dias_inactivo
        FROM alumnos a
        LEFT JOIN asistencias ast ON a.id = ast.alumno_id
        WHERE a.estado_membresia = 'activa'
        GROUP BY a.id
        HAVING dias_inactivo > 15
        ORDER BY dias_inactivo DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$resultado_completo = $stmt->get_result();

$alumnos_riesgo = [];
while($row = $resultado_completo->fetch_assoc()) {
    $alumnos_riesgo[] = $row;
}

$kpi_riesgo_critico = 0;
$kpi_riesgo_moderado = 0;
$kpi_total_riesgo = count($alumnos_riesgo);

foreach ($alumnos_riesgo as $alumno) {
    if ($alumno['dias_inactivo'] > 30) $kpi_riesgo_critico++;
    elseif ($alumno['dias_inactivo'] > 15) $kpi_riesgo_moderado++;
}
?>

<style>
    /* --- ESTILOS DE DISEÑO DE ÉLITE --- */
    :root {
        --color-critical: #e74c3c;
        --color-moderate: #f39c12;
        --color-neutral: #3498db;
        --color-surface-light: #343a43;
    }
    .page-header h1 { font-size: 2.5rem; font-weight: 700; }
    .page-header p { font-size: 1.1rem; }
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
    .kpi-card { background-color: var(--color-surface); border-radius: 12px; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-left: 5px solid; }
    .kpi-icon { width: 50px; height: 50px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; }
    .kpi-icon svg { width: 24px; height: 24px; color: #fff; }
    .kpi-content .kpi-value { font-size: 2.5rem; font-weight: 700; line-height: 1; }
    .kpi-content .kpi-title { font-size: 1rem; color: var(--color-text-muted); }
    .kpi-card.critical { border-color: var(--color-critical); color: var(--color-critical); }
    .kpi-card.critical .kpi-icon { background: var(--color-critical); }
    .kpi-card.moderate { border-color: var(--color-moderate); color: var(--color-moderate); }
    .kpi-card.moderate .kpi-icon { background: var(--color-moderate); }
    .kpi-card.neutral { border-color: var(--color-neutral); color: var(--color-neutral); }
    .kpi-card.neutral .kpi-icon { background: var(--color-neutral); }
    .dataTables_wrapper { background-color: var(--color-surface); padding: 2rem; border-radius: 12px; }
    .dataTables_length label, .dataTables_filter label { font-size: 1rem; color: var(--color-text-muted); }
    .dataTables_length select, .dataTables_filter input { background-color: var(--color-background); border: 1px solid var(--color-border); border-radius: 8px; color: var(--color-text); padding: 0.5rem 1rem; margin: 0 0.5rem; }
    .dataTables_paginate .paginate_button { background: transparent !important; border: 1px solid var(--color-border) !important; border-radius: 8px !important; color: var(--color-text) !important; margin: 0 5px !important; }
    .dataTables_paginate .paginate_button.current, .dataTables_paginate .paginate_button:hover { background: var(--color-primary) !important; border-color: var(--color-primary) !important; }
    #tablaRiesgo { width: 100% !important; border-collapse: separate; border-spacing: 0 10px; }
    #tablaRiesgo thead th { border: none; text-transform: uppercase; color: var(--color-text-muted); font-size: 0.8rem; letter-spacing: 1px; }
    #tablaRiesgo tbody tr { background-color: var(--color-surface-light); border-radius: 8px; transition: background-color 0.2s ease, transform 0.2s ease; }
    #tablaRiesgo tbody tr:hover { background-color: #3c424d; transform: scale(1.01); }
    #tablaRiesgo tbody td { border: none; padding: 1.25rem 1rem; vertical-align: middle; }
    #tablaRiesgo tbody td:first-child { border-top-left-radius: 8px; border-bottom-left-radius: 8px; display: flex; align-items: center; gap: 15px; }
    #tablaRiesgo tbody td:last-child { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
    .risk-indicator { width: 6px; height: 30px; border-radius: 3px; flex-shrink: 0; }
    .risk-critical { background-color: var(--color-critical); }
    .risk-moderate { background-color: var(--color-moderate); }
    .status-text { font-weight: 500; }
    .status-critical { color: var(--color-critical); }
    .status-moderate { color: var(--color-moderate); }
    .action-buttons a { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: all 0.2s ease; }
    .action-buttons a svg { width: 16px; height: 16px; }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); backdrop-filter: blur(5px); }
    .modal-content { background-color: var(--color-surface); margin: 15% auto; padding: 2rem; border-top: 5px solid var(--color-primary); border-radius: 12px; width: 90%; max-width: 500px; position: relative; }
    .close-button { color: #aaa; position: absolute; top: 1rem; right: 1.5rem; font-size: 28px; font-weight: bold; cursor: pointer; }
    .modal-content h3 { margin-top: 0; }
    .modal-info p { margin: 0.5rem 0 1.5rem 0; font-size: 1.1rem; color: var(--color-text-muted); }
    .modal-info strong { color: var(--color-text); }
</style>

<div class="container">
    <div class="page-header"><h1>Centro de Intervención</h1><p class="text-muted">Alumnos con riesgo de abandono por inactividad.</p></div>

    <div class="kpi-grid">
        <div class="kpi-card critical"><div class="kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div><div class="kpi-content"><div class="kpi-value"><?php echo $kpi_riesgo_critico; ?></div><div class="kpi-title">Riesgo Crítico (>30 días)</div></div></div>
        <div class="kpi-card moderate"><div class="kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg></div><div class="kpi-content"><div class="kpi-value"><?php echo $kpi_riesgo_moderado; ?></div><div class="kpi-title">Riesgo Moderado (15-30 días)</div></div></div>
        <div class="kpi-card neutral"><div class="kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></div><div class="kpi-content"><div class="kpi-value"><?php echo $kpi_total_riesgo; ?></div><div class="kpi-title">Total en Riesgo</div></div></div>
    </div>
    
    <table id="tablaRiesgo" class="display">
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Última Asistencia</th>
                <th>Estado de Inactividad</th>
                <th style="width: 250px;">Acciones</th>
                <th class="hidden-sort">Días Inactivo</th> 
            </tr>
        </thead>
        <tbody>
            <?php foreach($alumnos_riesgo as $row): ?>
                <tr>
                    <td>
                        <?php $risk_class = ($row['dias_inactivo'] > 30) ? 'risk-critical' : 'risk-moderate'; ?>
                        <div class="risk-indicator <?php echo $risk_class; ?>"></div>
                        <strong><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellidos']); ?></strong>
                    </td>
                    <td><?php echo $row['ultima_asistencia'] ? date("d/m/Y", strtotime($row['ultima_asistencia'])) : 'Nunca ha asistido'; ?></td>
                    <td></td>
                    <td class="action-buttons">
                        <a href="#" class="btn btn-primary btn-sm contact-btn" 
                           data-name="<?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellidos']); ?>"
                           data-phone="<?php echo htmlspecialchars(isset($row['telefono']) ? $row['telefono'] : 'No disponible'); ?>"
                           data-address="<?php echo htmlspecialchars(isset($row['direccion']) ? $row['direccion'] : 'No disponible'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                            <span>Contactar</span>
                        </a>
                        <a href="/MOKUSO/alumnos/perfil.php?alumno_id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5.9c1.16 0 2.1.94 2.1 2.1s-.94 2.1-2.1 2.1S9.9 9.16 9.9 8s.94-2.1 2.1-2.1m0 9c2.97 0 6.1 1.46 6.1 2.1v1.1H5.9V17c0-.64 3.13-2.1 6.1-2.1M12 4C9.79 4 8 5.79 8 8s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 9c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/></svg>
                            <span>Ver Perfil</span>
                        </a>
                    </td>
                    <td><?php echo $row['dias_inactivo']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="contactModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h3>Información de Contacto</h3>
        <h2 id="modalStudentName" style="margin-bottom: 2rem;"></h2>
        <div class="modal-info">
            <strong>Teléfono:</strong><p id="modalStudentPhone"></p>
            <strong>Dirección:</strong><p id="modalStudentAddress"></p>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    // Inicialización de DataTables
    var table = $('#tablaRiesgo').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        "order": [[ 4, "desc" ]],
        "pageLength": 10,
        "columnDefs": [
            { "targets": 4, "visible": false },
            { "targets": 3, "orderable": false }
        ],
        "createdRow": function( row, data, dataIndex ) {
            var dias = parseInt(data[4]);
            var statusText = 'Inactivo por ' + dias + ' día(s)';
            var statusClass = (dias > 30) ? 'status-critical' : 'status-moderate';
            if (dias >= 9999) {
                statusText = 'Sin registro de asistencias';
            }
            $('td', row).eq(2).html('<span class="status-text ' + statusClass + '">' + statusText + '</span>');
        }
    });

    // --- LÓGICA DEL MODAL CORREGIDA ---
    const modal = $('#contactModal');
    const closeBtn = $('.close-button');

    // Se delega el evento al elemento de la tabla, que es más estable.
    $('#tablaRiesgo').on('click', '.contact-btn', function(event) {
        event.preventDefault();
        
        const name = $(this).data('name');
        const phone = $(this).data('phone');
        const address = $(this).data('address');

        $('#modalStudentName').text(name);
        $('#modalStudentPhone').text(phone);
        $('#modalStudentAddress').text(address);

        modal.show(); // Usamos .show() de jQuery para mostrarlo
    });

    // Cerrar el modal
    closeBtn.on('click', function() {
        modal.hide();
    });

    $(window).on('click', function(event) {
        if ($(event.target).is(modal)) {
            modal.hide();
        }
    });
});
</script>

<?php include '../templates/footer.php'; ?>
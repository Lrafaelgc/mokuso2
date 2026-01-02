<?php 
include '../templates/header.php';
include '../config/db.php';

// --- CÁLCULOS PARA LOS KPIs Y LA TABLA ---
$fecha_limite = date('Y-m-d', strtotime('+30 days'));
$sql = "SELECT id, nombre, apellidos, fecha_vencimiento_membresia, 
               DATEDIFF(fecha_vencimiento_membresia, CURDATE()) as dias_restantes
        FROM alumnos 
        WHERE estado_membresia IN ('activa', 'exento') 
          AND fecha_vencimiento_membresia <= ?
        ORDER BY dias_restantes ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $fecha_limite);
$stmt->execute();
$resultado_completo = $stmt->get_result();

$alumnos_vencimiento = [];
while($row = $resultado_completo->fetch_assoc()) {
    $alumnos_vencimiento[] = $row;
}

$kpi_vencidos = 0;
$kpi_vencen_semana = 0;
$kpi_total_periodo = count($alumnos_vencimiento);

foreach ($alumnos_vencimiento as $alumno) {
    if ($alumno['dias_restantes'] < 0) $kpi_vencidos++;
    if ($alumno['dias_restantes'] >= 0 && $alumno['dias_restantes'] <= 7) $kpi_vencen_semana++;
}
?>

<style>
    /* --- ESTILOS DE DISEÑO DE ÉLITE --- */
    :root {
        --color-danger: #e74c3c;
        --color-warning: #f1c40f;
        --color-info: #3498db;
        --color-surface-light: #343a43;
    }
    .page-header h1 { font-size: 2.5rem; font-weight: 700; }
    .page-header p { font-size: 1.1rem; }

    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
    .kpi-card { background-color: var(--color-surface); border-radius: 12px; padding: 1.5rem; position: relative; overflow: hidden; }
    .kpi-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
    .kpi-icon { width: 50px; height: 50px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; }
    .kpi-icon svg { width: 24px; height: 24px; color: #fff; }
    .kpi-content .kpi-value { font-size: 2.5rem; font-weight: 700; line-height: 1; }
    .kpi-content .kpi-title { font-size: 1rem; color: var(--color-text-muted); }
    .kpi-card .progress-bar { position: absolute; bottom: 0; left: 0; height: 6px; background-color: currentColor; width: 100%; }
    .kpi-card .progress-bar > div { height: 100%; background-color: var(--color-text); opacity: 0.3; }
    .kpi-card.danger { color: var(--color-danger); }
    .kpi-card.danger .kpi-icon { background: var(--color-danger); }
    .kpi-card.warning { color: var(--color-warning); }
    .kpi-card.warning .kpi-icon { background: var(--color-warning); }
    .kpi-card.info { color: var(--color-info); }
    .kpi-card.info .kpi-icon { background: var(--color-info); }

    .dataTables_wrapper { background-color: var(--color-surface); padding: 2rem; border-radius: 12px; }
    .dataTables_length label, .dataTables_filter label { font-size: 1rem; color: var(--color-text-muted); }
    .dataTables_length select, .dataTables_filter input { background-color: var(--color-background); border: 1px solid var(--color-border); border-radius: 8px; color: var(--color-text); padding: 0.5rem 1rem; margin: 0 0.5rem; }
    .dataTables_paginate .paginate_button { background: transparent !important; border: 1px solid var(--color-border) !important; border-radius: 8px !important; color: var(--color-text) !important; margin: 0 5px !important; }
    .dataTables_paginate .paginate_button.current, .dataTables_paginate .paginate_button:hover { background: var(--color-primary) !important; border-color: var(--color-primary) !important; }

    #tablaVencimientos { width: 100% !important; border-collapse: separate; border-spacing: 0 10px; }
    #tablaVencimientos thead th { border: none; text-transform: uppercase; color: var(--color-text-muted); font-size: 0.8rem; letter-spacing: 1px; }
    #tablaVencimientos tbody tr { background-color: var(--color-surface-light); border-radius: 8px; transition: background-color 0.2s ease, transform 0.2s ease; }
    #tablaVencimientos tbody tr:hover { background-color: #3c424d; transform: scale(1.01); }
    #tablaVencimientos tbody td { border: none; padding: 1.25rem 1rem; vertical-align: middle; }
    #tablaVencimientos tbody td:first-child { border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
    #tablaVencimientos tbody td:last-child { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
    
    .status-text { font-weight: 500; }
    .status-danger { color: var(--color-danger); }
    .status-warning { color: var(--color-warning); }
    .status-info { color: var(--color-info); }

    /* ================================================== */
    /* === BLOQUE AÑADIDO PARA CORREGIR LOS ICONOS === */
    /* ================================================== */
    .action-buttons a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    .action-buttons svg {
        width: 18px;
        height: 18px;
        color: white; /* Asegura que el ícono sea blanco */
    }
    /* ================================================== */

</style>

<div class="container">
    <div class="page-header">
        <h1>Centro de Retención</h1>
        <p class="text-muted">Gestión proactiva de membresías a punto de expirar.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card danger">
            <div class="kpi-header">
                <div class="kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div>
                <div class="kpi-content"><div class="kpi-value"><?php echo $kpi_vencidos; ?></div><div class="kpi-title">Membresías Vencidas</div></div>
            </div>
            <div class="progress-bar"><div style="width: <?php echo $kpi_total_periodo > 0 ? ($kpi_vencidos / $kpi_total_periodo) * 100 : 0; ?>%;"></div></div>
        </div>
        <div class="kpi-card warning">
            <div class="kpi-header">
                <div class="kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg></div>
                <div class="kpi-content"><div class="kpi-value"><?php echo $kpi_vencen_semana; ?></div><div class="kpi-title">Vencen esta Semana</div></div>
            </div>
            <div class="progress-bar"><div style="width: <?php echo $kpi_total_periodo > 0 ? ($kpi_vencen_semana / $kpi_total_periodo) * 100 : 0; ?>%;"></div></div>
        </div>
        <div class="kpi-card info">
            <div class="kpi-header">
                <div class="kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg></div>
                <div class="kpi-content"><div class="kpi-value"><?php echo $kpi_total_periodo; ?></div><div class="kpi-title">Total en Periodo (30 Días)</div></div>
            </div>
            <div class="progress-bar"><div style="width: 100%;"></div></div>
        </div>
    </div>
    
    <table id="tablaVencimientos" class="display">
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Fecha de Vencimiento</th>
                <th>Estado</th>
                <th style="width: 120px;">Acciones</th>
                <th class="hidden-sort">Días</th> 
            </tr>
        </thead>
        <tbody>
            <?php foreach($alumnos_vencimiento as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellidos']); ?></strong></td>
                    <td><?php echo date("d/m/Y", strtotime($row['fecha_vencimiento_membresia'])); ?></td>
                    <td></td>
                    <td class="action-buttons">
                        <a href="/MOKUSO/pagos/registrar_pago.php?alumno_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="Renovar Membresía"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg></a>
                        <a href="/MOKUSO/alumnos/perfil.php?alumno_id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm" title="Ver Perfil del Alumno"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5.9c1.16 0 2.1.94 2.1 2.1s-.94 2.1-2.1 2.1S9.9 9.16 9.9 8s.94-2.1 2.1-2.1m0 9c2.97 0 6.1 1.46 6.1 2.1v1.1H5.9V17c0-.64 3.13-2.1 6.1-2.1M12 4C9.79 4 8 5.79 8 8s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 9c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/></svg></a>
                    </td>
                    <td><?php echo $row['dias_restantes']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#tablaVencimientos').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        "order": [[ 4, "asc" ]], 
        "pageLength": 10,
        "columnDefs": [
            { "targets": 4, "visible": false }, 
            { "targets": 3, "orderable": false }
        ],
        "createdRow": function( row, data, dataIndex ) {
            var dias = parseInt(data[4]); 
            var statusText = '';
            var statusClass = 'status-info';

            if (dias < 0) {
                statusText = 'Vencido hace ' + Math.abs(dias) + ' día(s)';
                statusClass = 'status-danger';
            } else if (dias == 0) {
                statusText = 'Vence Hoy';
                statusClass = 'status-warning';
            } else {
                statusText = 'Vence en ' + dias + ' día(s)';
                if (dias <= 7) statusClass = 'status-warning';
            }
            
            $('td', row).eq(2).html('<span class="status-text ' + statusClass + '">' + statusText + '</span>');
        }
    });
});
</script>

<?php include '../templates/footer.php'; ?>
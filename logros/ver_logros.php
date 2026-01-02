<?php
session_start();
// Security check: Only allow teachers or admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

include '../config/db.php';

// Consulta para obtener todos los logros
$sql = "SELECT 
            l.id,
            l.logro,
            l.fecha_logro,
            CONCAT(a.nombre, ' ', a.apellidos) AS nombre_completo_alumno
        FROM logros l
        JOIN alumnos a ON l.alumno_id = a.id
        ORDER BY l.fecha_logro DESC";

$result = $conn->query($sql);

$logros = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $logros[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Logros - Mokuso Manager</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap5.css">

    <style>
        :root {
            --color-bg: #0a0a0f; --color-surface: rgba(30, 32, 40, 0.75); --color-primary: #ff8c00;
            --color-primary-glow: rgba(255, 140, 0, 0.5); --color-secondary: #00bfff; --color-accent: #ff3cac;
            --color-success: #00BFA6; --color-error: #ff4747; --color-text-light: #f0f0f0;
            --color-text-muted: #a0a0a0; --color-border: rgba(97, 97, 97, 0.3); --border-radius: 22px;
            --backdrop-blur: 18px; --shadow: 0 12px 48px 0 rgba(0,0,0,0.45);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            min-height: 100vh; margin: 0; font-family: 'Poppins', sans-serif;
            color: var(--color-text-light); background: linear-gradient(135deg, #181824 0%, #23243a 100%);
            position: relative; overflow-x: hidden;
        }
        .background-blur { position: fixed; inset: 0; z-index: -1; pointer-events: none; }
        .blur-circle { position: absolute; border-radius: 50%; filter: blur(100px); opacity: 0.35; animation: float 12s infinite alternate ease-in-out; }
        .blur1 { width: 420px; height: 420px; background: var(--color-primary-glow); top: 10%; left: 5%; animation-delay: 0s;}
        .blur2 { width: 320px; height: 320px; background: var(--color-secondary); top: 60%; left: 60%; animation-delay: 2s;}
        .blur3 { width: 220px; height: 220px; background: var(--color-accent); top: 70%; left: 10%; animation-delay: 4s;}
        @keyframes float { from { transform: scale(1) translateY(0); } to { transform: scale(1.1) translateY(-30px); } }

        .sidebar { background: var(--color-surface); border-right: 1.5px solid var(--color-border); border-radius: 0 var(--border-radius) var(--border-radius) 0; box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur)); width: 260px; height: 100vh; position: fixed; top: 0; left: 0; padding: 1.5rem; z-index: 100; display: flex; flex-direction: column; }
        .sidebar-header { text-align: center; margin-bottom: 2.5rem; }
        .sidebar-header .logo { height: 50px; filter: drop-shadow(0 0 15px var(--color-primary-glow)); }
        .sidebar-header h2 { font-family: 'Orbitron', sans-serif; font-size: 1.5rem; color: var(--color-text-light); margin: 0.5rem 0 0 0; }
        .sidebar-nav { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav li { margin-bottom: 0.75rem; }
        .sidebar-nav a { display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1rem; color: var(--color-text-muted); text-decoration: none; border-radius: 12px; font-weight: 500; font-size: 1rem; transition: all 0.3s; }
        .sidebar-nav a:hover { background-color: rgba(255,255,255,0.05); color: var(--color-text-light); }
        .sidebar-nav a.active { background-color: var(--color-primary); color: #101012; font-weight: 700; box-shadow: 0 5px 20px var(--color-primary-glow); }
        .sidebar-nav a i, .sidebar-nav a svg { width: 24px; height: 24px; text-align: center; }
        .sidebar-footer { margin-top: auto; text-align: center; }
        
        .main-content-wrapper { margin-left: 260px; width: calc(100% - 260px); position: relative; }
        .main-container { width: 100%; max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; z-index: 1; }
        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; }
        .page-header h1 { font-family: 'Orbitron', sans-serif; color: var(--color-primary); font-size: 2.5rem; margin: 0; letter-spacing: 2px; text-shadow: 0 0 18px var(--color-primary-glow); }
        
        .btn-primary-gradient { padding: 0.75rem 1.5rem; font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; background: linear-gradient(90deg, var(--color-primary), #e07b00 60%, var(--color-secondary) 100%); border: none; border-radius: 10px; color: #101012; cursor: pointer; transition: transform 0.18s, box-shadow 0.18s; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-primary-gradient:hover { transform: translateY(-2px); box-shadow: 0 5px 20px var(--color-primary-glow); }
        .btn-ghost { background: transparent; border: 2px solid var(--color-border); color: var(--color-text-muted); padding: 0.75rem 1.2rem; font-size: 0.9rem; font-weight: 600; border-radius: 10px; text-decoration: none; transition: color 0.3s, border-color 0.3s, transform 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-ghost:hover { color: var(--color-primary); border-color: var(--color-primary); transform: translateY(-2px); }

        .data-table-container { background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--border-radius); box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur)); padding: 2rem; overflow: hidden; }
        
        div.dt-container .row:first-child, div.dt-container .row:last-child { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.5rem; }
        div.dt-container .dt-length label, div.dt-container .dt-search label { color: var(--color-text-muted); font-weight: 500; }
        div.dt-container .dt-length select, div.dt-container .dt-search input { background: rgba(0,0,0,0.2); border: 1.5px solid var(--color-border); border-radius: 10px; color: var(--color-text-light); padding: 0.5rem 1rem; margin: 0 0.5rem; }
        div.dt-container .dt-length select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23a0a0a0'%3E%3Cpath fill-rule='evenodd' d='M8 11.646l-4.854-4.853.708-.708L8 10.23l4.146-4.145.708.708L8 11.646z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.7rem center; background-size: 1em; padding-right: 2.5rem; }
        div.dt-container .dt-search input { padding-left: 2.5rem; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23a0a0a0'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: left 0.8rem center; background-size: 1em; }
        div.dt-container .dt-info { color: var(--color-text-muted); padding-top: 0.5rem; }
        div.dt-container .page-item .page-link { background-color: transparent; border: 2px solid var(--color-border); color: var(--color-text-muted); margin: 0 0.2rem; border-radius: 10px; transition: all 0.3s; box-shadow: none; }
        div.dt-container .page-item .page-link:hover { color: var(--color-primary); border-color: var(--color-primary); background-color: rgba(255, 140, 0, 0.1); }
        div.dt-container .page-item.active .page-link { background-color: var(--color-primary); border-color: var(--color-primary); color: #101012; box-shadow: 0 0 10px var(--color-primary-glow); }
        div.dt-container .page-item.disabled .page-link { opacity: 0.5; pointer-events: none; }

        .table { --bs-table-bg: transparent; --bs-table-color: var(--color-text-light); --bs-table-border-color: var(--color-border); --bs-table-striped-bg: rgba(255, 255, 255, 0.02); --bs-table-striped-color: var(--color-text-light); --bs-table-hover-bg: rgba(255, 255, 255, 0.05); --bs-table-hover-color: var(--color-text-light); }
        table.dataTable { width: 100% !important; border-collapse: separate; border-spacing: 0; margin: 0 !important; }
        .table th, .table td { padding: 1rem; vertical-align: middle; }
        .table thead th { font-family: 'Orbitron', sans-serif; font-weight: 700; color: var(--color-primary); text-transform: uppercase; font-size: 0.9rem; letter-spacing: 1px; background-color: rgba(0,0,0,0.2); border-bottom-width: 2px !important; border-color: var(--color-primary) !important; white-space: nowrap; }
        .table thead th:first-child { border-top-left-radius: 12px; } .table thead th:last-child { border-top-right-radius: 12px; }
        .table tbody tr:last-child td { border-bottom: none; }
        
        @media (max-width: 992px) { .sidebar { display: none; } .main-content-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

<div class="background-blur">
    <div class="blur-circle blur1"></div> <div class="blur-circle blur2"></div> <div class="blur-circle blur3"></div>
</div>

<aside class="sidebar">
    <div class="sidebar-header"><img src="/MOKUSO/assets/img/logo2.png" alt="Logo Mokuso" class="logo"><h2>Mokuso</h2></div>
    <ul class="sidebar-nav">
        <li><a href="/MOKUSO/dashboard/index.php"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a></li>
        <li><a href="/MOKUSO/alumnos/index.php"><i class="fas fa-users"></i> <span>Alumnos</span></a></li>
        <li><a href="/MOKUSO/asistencias/index.php"><i class="fas fa-calendar-check"></i> <span>Asistencias</span></a></li>
        <li><a href="/MOKUSO/pagos/registrar_pago.php"><i class="fas fa-dollar-sign"></i> <span>Pagos</span></a></li>
        <li><a href="/MOKUSO/logros/agregar_logro.php" class="active"><i class="fas fa-trophy"></i> <span>Logros</span></a></li>
    </ul>
    <div class="sidebar-footer"><a href="/MOKUSO/config/logout.php" class="btn-ghost" style="width: 100%;"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></div>
</aside>

<main class="main-content-wrapper">
    <div class="main-container">
        <div class="page-header">
            <h1>Historial de Logros</h1>
            <div class="header-actions">
                <a href="index.php" class="btn-ghost"><i class="fas fa-arrow-left me-1"></i> Volver al Centro</a>
                <a href="agregar_logro.php" class="btn-primary-gradient"><i class="fas fa-plus-circle me-1"></i> Añadir Nuevo Logro</a>
            </div>
        </div>

        <div class="data-table-container">
            <?php if (count($logros) > 0): ?>
            <table id="logros-table" class="table table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Logro</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logros as $logro): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($logro['nombre_completo_alumno']); ?></td>
                        <td><?php echo htmlspecialchars($logro['logro']); ?></td>
                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($logro['fecha_logro']))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="text-center p-5">
                    <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No hay logros registrados</h4>
                    <p>Aún no se ha guardado ningún logro. ¡Registra el primero!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/2.0.7/js/dataTables.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.js"></script>
<script>
$(document).ready(function() {
    $('#logros-table').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/2.0.7/i18n/es-ES.json" },
        "order": [[ 2, "desc" ]], // Ordenar por fecha (tercera columna) descendente
        "pageLength": 25,
        "responsive": true,
        "pagingType": "full_numbers"
    });
});
</script>

</body>
</html>
<?php
$conn->close();
?>
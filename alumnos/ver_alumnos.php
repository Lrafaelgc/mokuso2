<?php
session_start();
// Security check: Only allow teachers or admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

include '../config/db.php';

// Consulta PRINCIPAL para la tabla con JOINS
$sql = "SELECT 
            a.id, a.nombre, a.apellidos, a.estado_membresia,
            n.nombre AS nivel_nombre,
            d.nombre AS disciplina_nombre,
            u.id AS user_id 
        FROM alumnos a 
        LEFT JOIN niveles n ON a.nivel_id = n.id
        LEFT JOIN disciplinas d ON a.disciplina_id = d.id
        LEFT JOIN users u ON a.id = u.alumno_id AND u.role = 'alumno' 
        ORDER BY a.apellidos, a.nombre ASC"; // Ordenar por apellido primero
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Alumnos - Mokuso Manager</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap5.css">

    <style>
        /* --- VARIABLES GLOBALES (Mokuso Elite Theme) --- */
        :root {
            --color-primary: #8cc63f;
            --color-primary-dark: #6a9e2d;
            --color-primary-glow: rgba(140, 198, 63, 0.3);
            --color-bg-main: #0a0a0f;
            --color-surface: rgba(22, 22, 30, 0.85);
            --color-border: rgba(255, 255, 255, 0.08);
            --color-text-white: #ffffff;
            --color-text-gray: #a0a0b0;
            --color-success: #00E676; /* Verde más vibrante */
            --color-error: #FF5252;
            --color-warning: #FFD600;
            --color-info: #2979FF;
            --shadow-heavy: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            --backdrop-blur: 20px;
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --border-radius: 16px;
        }

        body {
            min-height: 100vh; margin: 0; font-family: 'Poppins', sans-serif;
            color: var(--color-text-white); background-color: var(--color-bg-main);
            background-image:
                radial-gradient(circle at 10% 20%, rgba(140, 198, 63, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(41, 121, 255, 0.05) 0%, transparent 40%);
            position: relative; overflow-x: hidden;
        }

        /* Patrón de fondo */
        body::before {
            content: ''; position: fixed; inset: 0; z-index: -1; opacity: 0.4;
            background-image: linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 30px 30px; pointer-events: none;
        }

        .sidebar { background: var(--color-surface); border-right: 1px solid var(--color-border); border-radius: 0 var(--border-radius) var(--border-radius) 0; box-shadow: var(--shadow-heavy); backdrop-filter: blur(var(--backdrop-blur)); width: 260px; height: 100vh; position: fixed; top: 0; left: 0; padding: 1.5rem; z-index: 100; display: flex; flex-direction: column; }
        .sidebar-header { text-align: center; margin-bottom: 2.5rem; }
        .sidebar-header .logo { height: 50px; filter: drop-shadow(0 0 15px var(--color-primary-glow)); }
        .sidebar-header h2 { font-family: 'Orbitron', sans-serif; font-size: 1.5rem; color: var(--color-text-white); margin: 0.5rem 0 0 0; }
        .sidebar-nav { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav li { margin-bottom: 0.75rem; }
        .sidebar-nav a { display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1rem; color: var(--color-text-gray); text-decoration: none; border-radius: 12px; font-weight: 500; font-size: 1rem; transition: var(--transition-smooth); }
        .sidebar-nav a:hover { background-color: rgba(255,255,255,0.05); color: var(--color-text-white); }
        .sidebar-nav a.active { background-color: var(--color-primary); color: #101012; font-weight: 700; box-shadow: 0 5px 20px var(--color-primary-glow); }
        .sidebar-nav a i { width: 20px; text-align: center; font-size: 1.2rem; }
        .sidebar-footer { margin-top: auto; text-align: center; }
        
        .main-content-wrapper { margin-left: 260px; width: calc(100% - 260px); position: relative; }
        .main-container { width: 100%; max-width: 1600px; margin: 0 auto; padding: 2rem; z-index: 1; }
        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 3rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--color-border); }
        .page-header h1 { font-family: 'Orbitron', sans-serif; color: var(--color-primary); font-size: 2.2rem; margin: 0; letter-spacing: 1px; text-shadow: 0 0 20px var(--color-primary-glow); }
        
        .btn-primary-gradient { padding: 0.9rem 1.8rem; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); border: none; border-radius: 12px; color: #fff; cursor: pointer; transition: var(--transition-smooth); display: inline-flex; align-items: center; gap: 0.8rem; text-decoration: none; font-family: 'Orbitron', sans-serif; box-shadow: 0 8px 20px -8px var(--color-primary-glow); }
        .btn-primary-gradient:hover { transform: translateY(-3px); box-shadow: 0 12px 25px -5px var(--color-primary-glow); }
        .btn-ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text-gray); padding: 0.7rem 1.2rem; font-size: 0.9rem; font-weight: 600; border-radius: 10px; text-decoration: none; transition: var(--transition-smooth); display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-ghost:hover { color: var(--color-primary); border-color: var(--color-primary); background: rgba(140, 198, 63, 0.05); }
        .btn-ghost.warning { border-color: var(--color-warning); color: var(--color-warning); }
        .btn-ghost.warning:hover { background-color: var(--color-warning); color: #000; }

        .data-table-container { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--border-radius); box-shadow: var(--shadow-heavy); backdrop-filter: blur(var(--backdrop-blur)); padding: 2rem; overflow: hidden; }
        
        /* DataTables Custom Styling */
        div.dt-container .row:first-child, div.dt-container .row:last-child { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.5rem; }
        div.dt-container .dt-length label, div.dt-container .dt-search label { color: var(--color-text-gray); font-weight: 500; }
        div.dt-container .dt-length select, div.dt-container .dt-search input { background: rgba(0,0,0,0.3); border: 1px solid var(--color-border); border-radius: 12px; color: var(--color-text-white); padding: 0.6rem 1rem; margin: 0 0.5rem; font-family: 'Poppins', sans-serif; transition: var(--transition-smooth); }
        div.dt-container .dt-length select:focus, div.dt-container .dt-search input:focus { border-color: var(--color-primary); outline: none; background-color: rgba(0,0,0,0.5); }
        div.dt-container .dt-info { color: var(--color-text-gray); padding-top: 0.5rem; }
        div.dt-container .page-item .page-link { background-color: transparent; border: 1px solid var(--color-border); color: var(--color-text-gray); margin: 0 0.2rem; border-radius: 10px; transition: var(--transition-smooth); box-shadow: none; }
        div.dt-container .page-item .page-link:hover { color: var(--color-primary); border-color: var(--color-primary); background-color: rgba(140, 198, 63, 0.1); }
        div.dt-container .page-item.active .page-link { background-color: var(--color-primary); border-color: var(--color-primary); color: #fff; box-shadow: 0 0 15px var(--color-primary-glow); }
        div.dt-container .page-item.disabled .page-link { opacity: 0.5; pointer-events: none; }

        .table { --bs-table-bg: transparent; --bs-table-color: var(--color-text-white); --bs-table-border-color: var(--color-border); --bs-table-striped-bg: rgba(255, 255, 255, 0.02); --bs-table-striped-color: var(--color-text-white); --bs-table-hover-bg: rgba(255, 255, 255, 0.05); --bs-table-hover-color: var(--color-text-white); }
        table.dataTable { width: 100% !important; border-collapse: separate; border-spacing: 0; margin: 0 !important; }
        .table th, .table td { padding: 1rem; vertical-align: middle; }
        .table thead th { font-family: 'Orbitron', sans-serif; font-weight: 700; color: var(--color-primary); text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; background-color: rgba(140, 198, 63, 0.05); border-bottom: 1px solid var(--color-primary) !important; white-space: nowrap; }
        .table tbody tr:last-child td { border-bottom: none; }

        .badge { font-size: 0.75rem; font-weight: 700; padding: 0.4em 1em; border-radius: 30px; text-transform: uppercase; letter-spacing: 0.5px; }
        .bg-success-light { background: rgba(0, 230, 118, 0.15) !important; color: var(--color-success) !important; border: 1px solid rgba(0, 230, 118, 0.3); }
        .bg-danger-light { background: rgba(255, 82, 82, 0.15) !important; color: var(--color-error) !important; border: 1px solid rgba(255, 82, 82, 0.3); }
        .bg-warning-light { background: rgba(255, 214, 0, 0.15) !important; color: var(--color-warning) !important; border: 1px solid rgba(255, 214, 0, 0.3); }

        .action-buttons { white-space: nowrap; display: flex; gap: 0.5rem; justify-content: flex-end; }
        .action-icon-btn { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid var(--color-border); color: var(--color-text-gray); transition: var(--transition-smooth); font-size: 1.2rem; text-decoration: none; }
        .action-icon-btn:hover { transform: translateY(-3px); }
        .btn-key:hover { color: var(--color-warning); border-color: var(--color-warning); background: rgba(255, 214, 0, 0.1); }
        .btn-unlock:hover { color: var(--color-success); border-color: var(--color-success); background: rgba(0, 230, 118, 0.1); }
        .btn-edit:hover { color: var(--color-info); border-color: var(--color-info); background: rgba(41, 121, 255, 0.1); }
        .btn-delete:hover { color: var(--color-error); border-color: var(--color-error); background: rgba(255, 82, 82, 0.1); }

        /* SweetAlert Custom */
        .swal2-popup { background: var(--color-surface) !important; border: 1px solid var(--color-border) !important; border-radius: 24px !important; color: var(--color-text-white) !important; box-shadow: var(--shadow-heavy) !important; backdrop-filter: blur(var(--backdrop-blur)) !important; }
        .swal2-title { color: var(--color-text-white) !important; font-family: 'Orbitron', sans-serif !important; }
        .swal2-html-container { color: var(--color-text-gray) !important; }
        .swal2-confirm { background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)) !important; color: #fff !important; font-weight: 600 !important; border-radius: 12px !important; padding: 0.8rem 1.5rem !important; font-family: 'Orbitron', sans-serif !important; box-shadow: 0 5px 15px -5px var(--color-primary-glow) !important; }
        .swal2-cancel { background: transparent !important; border: 1px solid var(--color-border) !important; color: var(--color-text-gray) !important; border-radius: 12px !important; padding: 0.8rem 1.5rem !important; font-weight: 600 !important; }

        @media (max-width: 992px) { .sidebar { display: none; } .main-content-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header"><img src="/MOKUSO/assets/img/logo2.png" alt="Logo Mokuso" class="logo"><h2>Mokuso</h2></div>
    <ul class="sidebar-nav">
        <li><a href="/MOKUSO/dashboard/index.php"><i class="ph-bold ph-chart-line-up"></i> <span>Dashboard</span></a></li>
        <li><a href="/MOKUSO/alumnos/index.php" class="active"><i class="ph-bold ph-users-three"></i> <span>Alumnos</span></a></li>
        <li><a href="/MOKUSO/asistencias/index.php"><i class="ph-bold ph-calendar-check"></i> <span>Asistencias</span></a></li>
        <li><a href="/MOKUSO/pagos/registrar_pago.php"><i class="ph-bold ph-currency-dollar"></i> <span>Pagos</span></a></li>
        <li><a href="/MOKUSO/logros/agregar_logro.php"><i class="ph-bold ph-trophy"></i> <span>Añadir Logro</span></a></li>
    </ul>
    <div class="sidebar-footer"><a href="/MOKUSO/config/logout.php" class="btn-ghost" style="width: 100%; justify-content: center;"><i class="ph-bold ph-sign-out"></i> Cerrar Sesión</a></div>
</aside>

<main class="main-content-wrapper">
    <div class="main-container">
        <div class="page-header">
            <h1><i class="ph-bold ph-users-three" style="margin-right: 10px; vertical-align: middle;"></i> Gestión de Alumnos</h1>
            <div class="header-actions" style="display: flex; gap: 1rem;">
                <a href="crear_acceso_padre_form.php" class="btn-ghost warning"><i class="ph-bold ph-user-circle-gear"></i> Acceso Padre</a>
                <a href="registrar.php" class="btn-primary-gradient"><i class="ph-bold ph-user-plus"></i> Nuevo Alumno</a>
            </div>
        </div>

        <div class="data-table-container">
            <table id="alumnosTable" class="table table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Nivel</th>
                        <th>Disciplina</th>
                        <th>Membresía</th>
                        <th>Acceso App</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($alumno = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></strong></td>
                            <td><?php echo htmlspecialchars(isset($alumno['nivel_nombre']) ? $alumno['nivel_nombre'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($alumno['disciplina_nombre']) ? $alumno['disciplina_nombre'] : 'N/A'); ?></td>
                            <td>
                                <?php $estado_lower = strtolower($alumno['estado_membresia']); $status_class = in_array($estado_lower, ['activa', 'exento']) ? 'bg-success-light' : 'bg-danger-light'; ?>
                                <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($alumno['estado_membresia'])); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $alumno['user_id'] ? 'bg-success-light' : 'bg-warning-light'; ?>"><?php echo $alumno['user_id'] ? 'CON ACCESO' : 'SIN ACCESO'; ?></span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($alumno['user_id']): ?>
                                        <a href="gestionar_acceso.php?action=restablecer&id=<?php echo $alumno['id']; ?>" class="action-icon-btn btn-key" title="Restablecer Clave"><i class="ph-bold ph-key"></i></a>
                                    <?php else: ?>
                                        <a href="gestionar_acceso.php?action=crear&id=<?php echo $alumno['id']; ?>" class="action-icon-btn btn-unlock" title="Crear Acceso"><i class="ph-bold ph-lock-key-open"></i></a>
                                    <?php endif; ?>
                                    <a href="editar_alumno.php?id=<?php echo $alumno['id']; ?>" class="action-icon-btn btn-edit" title="Editar"><i class="ph-bold ph-pencil-simple"></i></a>
                                    <a href="#" class="action-icon-btn btn-delete delete-btn" data-id="<?php echo $alumno['id']; ?>" title="Borrar"><i class="ph-bold ph-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/2.0.7/js/dataTables.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    $('#alumnosTable').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/2.0.7/i18n/es-ES.json' },
        pageLength: 10,
        "columnDefs": [ { "orderable": false, "targets": 5 } ],
        responsive: true,
        "pagingType": "full_numbers"
    });

    $('#alumnosTable').on('click', '.delete-btn', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Se borrará al alumno permanentemente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, borrar',
            cancelButtonText: 'Cancelar',
            customClass: { popup: 'swal2-popup' }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `eliminar_alumno.php?id=${id}`;
            }
        });
    });

    <?php if (isset($_SESSION['temp_user']) && isset($_SESSION['temp_pass'])): ?>
        Swal.fire({
            title: '¡Credenciales Creadas!',
            html: `<div style="background:rgba(0,0,0,0.2); padding:1.5rem; border-radius:16px; text-align:left; border: 1px solid var(--color-border);">
                    <p style="margin-bottom:0.5rem; color:var(--color-text-gray);">Usuario: <strong style="color:#fff;"><?php echo $_SESSION['temp_user']; ?></strong></p>
                    <p style="margin-bottom:0; color:var(--color-text-gray);">Clave: <strong style="color:#fff;"><?php echo $_SESSION['temp_pass']; ?></strong></p>
                   </div>`,
            icon: 'success',
            customClass: { popup: 'swal2-popup' }
        });
        <?php unset($_SESSION['temp_user'], $_SESSION['temp_pass'], $_SESSION['temp_status']); ?>
    <?php endif; ?>
});
</script>

</body>
</html>
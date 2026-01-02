<?php
session_start();
// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

include '../config/db.php'; 

// Obtener lista de alumnos para el autocompletado
$alumnos_list = [];
$result = $conn->query("SELECT id, nombre, apellidos FROM alumnos ORDER BY apellidos");
while ($alumno = $result->fetch_assoc()) {
    $alumnos_list[] = $alumno;
}

// Pre-seleccionar si se pasa ID por URL
$alumno_id_seleccionado = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;
$success_message = isset($_GET['status']) && $_GET['status'] == 'success' ? "Logro guardado exitosamente." : "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Logro - Mokuso Manager</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .main-container { width: 100%; max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem; z-index: 1; }
        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; }
        .page-header h1 { font-family: 'Orbitron', sans-serif; color: var(--color-primary); font-size: 2.5rem; margin: 0; letter-spacing: 2px; text-shadow: 0 0 18px var(--color-primary-glow); }
        
        .form-card { background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--border-radius); box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur)); padding: 2.5rem; }
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 768px) { .form-grid { grid-template-columns: 1fr 1fr; } .form-group.full-width { grid-column: 1 / -1; } }
        
        .form-group label { display: block; color: var(--color-text-muted); font-weight: 500; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .form-group input, .form-group select { width: 100%; background: rgba(0,0,0,0.2); border: 1.5px solid var(--color-border); border-radius: 10px; color: var(--color-text-light); padding: 0.8rem 1rem; font-size: 1rem; font-family: 'Poppins', sans-serif; transition: border-color 0.3s, box-shadow 0.3s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); }
        input[type="date"] { color-scheme: dark; } input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.8); cursor: pointer; }
        .form-note { color: var(--color-text-muted); font-size: 0.9rem; text-align: center; margin-top: 1.5rem; background: rgba(0,0,0,0.1); padding: 1rem; border-radius: 12px; }
        .form-actions { text-align: center; border-top: 1.5px solid var(--color-border); padding-top: 2rem; margin-top: 1.5rem; grid-column: 1 / -1; }
        
        .autocomplete-container { position: relative; }
        .autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: #36393f; border: 1.5px solid var(--color-border); border-radius: 10px; max-height: 200px; overflow-y: auto; z-index: 1000; display: none; }
        .autocomplete-item { padding: 0.75rem 1rem; cursor: pointer; transition: background-color 0.2s; }
        .autocomplete-item:hover { background-color: var(--color-primary); color: #101012; }
        
        .btn-submit { padding: 0.8rem 2.5rem; font-size: 1.1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; background: linear-gradient(90deg, var(--color-primary), #e07b00 60%, var(--color-secondary) 100%); border: none; border-radius: 12px; color: #101012; cursor: pointer; transition: transform 0.18s, box-shadow 0.18s; display: inline-flex; align-items: center; gap: 0.75rem; }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 8px 25px var(--color-primary-glow); }
        .btn-ghost { background: transparent; border: 2px solid var(--color-border); color: var(--color-text-muted); padding: 0.75rem 1.2rem; font-size: 0.9rem; font-weight: 600; border-radius: 10px; text-decoration: none; transition: all 0.2s; }
        .btn-ghost:hover { color: var(--color-primary); border-color: var(--color-primary); transform: translateY(-2px); }
        
        .swal2-popup { background-color: var(--color-surface) !important; border: 1.5px solid var(--color-border) !important; border-radius: var(--border-radius) !important; color: var(--color-text-light) !important; box-shadow: var(--shadow) !important; }
        .swal2-title { color: var(--color-text-light) !important; }
        .swal2-confirm { background: linear-gradient(90deg, var(--color-primary), #e07b00) !important; border-radius: 10px !important; box-shadow: none !important; color: #101012 !important; font-weight: 600 !important; }

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
        <li><a href="/MOKUSO/logros/" class="active"><i class="fas fa-trophy"></i> <span>Añadir Logro</span></a></li>
    </ul>
    <div class="sidebar-footer"><a href="/MOKUSO/config/logout.php" class="btn-ghost" style="width: 100%;"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></div>
</aside>

<main class="main-content-wrapper">
    <div class="main-container">
        <div class="page-header">
            <h1>Añadir Logro o Graduación</h1>
            <a href="/MOKUSO/logros/index.php" class="btn-ghost"><i class="fas fa-arrow-left me-1"></i> Volver al Centro de Logros</a>
        </div>
        
        <div class="form-card">
            <form action="guardar_logro.php" method="POST">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="alumno_name">Alumno</label>
                        <div class="autocomplete-container">
                            <input type="text" id="alumno_name" placeholder="Buscar por apellido o nombre..." autocomplete="off" required>
                            <div id="autocomplete-list" class="autocomplete-list"></div>
                            <input type="hidden" name="alumno_id" id="alumno_id" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="logro">Descripción del Logro</label>
                        <input type="text" id="logro" name="logro" placeholder="Ej. Cinta Amarilla, 1er Lugar Torneo" required>
                    </div>
                    <div class="form-group">
                        <label for="fecha_logro">Fecha del Logro</label>
                        <input type="date" id="fecha_logro" name="fecha_logro" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <p class="form-note">
                    Nota: Si el logro es un cambio de cinta (ej. "Cinta Amarilla"), el nivel del alumno se actualizará automáticamente.
                </p>
                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Guardar Logro
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success_message): ?>
        Swal.fire({
            title: '¡Éxito!',
            text: '<?php echo addslashes($success_message); ?>',
            icon: 'success',
            customClass: { popup: 'swal2-popup' }
        });
    <?php endif; ?>

    const ALUMNOS_LIST = <?php echo json_encode($alumnos_list); ?>;
    const searchInput = document.getElementById('alumno_name');
    const autocompleteList = document.getElementById('autocomplete-list');
    const hiddenIdInput = document.getElementById('alumno_id');
    const initialAlumnoId = <?php echo $alumno_id_seleccionado; ?>;
    
    function renderSuggestions(filteredList) {
        autocompleteList.innerHTML = '';
        if (filteredList.length === 0) {
            autocompleteList.style.display = 'none';
            return;
        }
        filteredList.forEach(alumno => {
            const item = document.createElement('div');
            item.classList.add('autocomplete-item');
            item.textContent = `${alumno.apellidos}, ${alumno.nombre}`;
            item.dataset.id = alumno.id;
            item.addEventListener('click', () => {
                searchInput.value = `${alumno.apellidos}, ${alumno.nombre}`;
                hiddenIdInput.value = alumno.id;
                autocompleteList.style.display = 'none';
            });
            autocompleteList.appendChild(item);
        });
        autocompleteList.style.display = 'block';
    }

    searchInput.addEventListener('input', function() {
        const searchText = this.value.toLowerCase();
        hiddenIdInput.value = ''; // Clear ID on new input
        if (searchText.length < 2) {
            autocompleteList.style.display = 'none';
            return;
        }
        const filteredAlumnos = ALUMNOS_LIST.filter(alumno => {
            const fullName = `${alumno.apellidos} ${alumno.nombre}`.toLowerCase();
            return fullName.includes(searchText);
        });
        renderSuggestions(filteredAlumnos);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.autocomplete-container')) {
            autocompleteList.style.display = 'none';
        }
    });

    if (initialAlumnoId > 0) {
        const preselectedAlumno = ALUMNOS_LIST.find(a => a.id == initialAlumnoId);
        if (preselectedAlumno) {
            searchInput.value = `${preselectedAlumno.apellidos}, ${preselectedAlumno.nombre}`;
            hiddenIdInput.value = preselectedAlumno.id;
        }
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>
<?php
session_start();
// Security check: Only allow teachers or admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

include '../config/db.php';

// Consulta 1: Alumnos disponibles para asignar (Solo aquellos que no tienen padres vinculados)
$sql_alumnos_disponibles = "
    SELECT a.id, a.nombre, a.apellidos 
    FROM alumnos a
    LEFT JOIN padres_alumnos pa ON a.id = pa.alumno_id
    WHERE pa.padre_id IS NULL
    ORDER BY a.apellidos, a.nombre ASC
";
$result_alumnos_disponibles = $conn->query($sql_alumnos_disponibles);

// Consulta 2: Padres existentes para el selector
$sql_padres_existentes = "
    SELECT p.id, p.nombre, u.username
    FROM padres p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.nombre ASC
";
$result_padres_existentes = $conn->query($sql_padres_existentes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Acceso para Padre/Tutor - Mokuso Manager</title>
    
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
        .sidebar-nav a i { width: 20px; text-align: center; font-size: 1.1rem; }
        .sidebar-footer { margin-top: auto; text-align: center; }
        
        .main-content-wrapper { margin-left: 260px; width: calc(100% - 260px); position: relative; }
        .main-container { width: 100%; max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem; z-index: 1; }
        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; }
        .page-header h1 { font-family: 'Orbitron', sans-serif; color: var(--color-primary); font-size: 2.5rem; margin: 0; letter-spacing: 2px; text-shadow: 0 0 18px var(--color-primary-glow); }
        
        .form-card { background: var(--color-surface); border: 1.5px solid var(--color-border); border-radius: var(--border-radius); box-shadow: var(--shadow); backdrop-filter: blur(var(--backdrop-blur)); padding: 2.5rem; }
        .form-section { margin-bottom: 2.5rem; }
        .form-section h4 { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; color: var(--color-text-light); padding-bottom: 1rem; margin: 0 0 1.5rem 0; border-bottom: 1.5px solid var(--color-border); }
        
        /* --- TOGGLE SWITCH PARA CREAR/VINCULAR --- */
        .action-toggle { display: flex; background-color: rgba(0,0,0,0.2); border-radius: 12px; padding: 0.5rem; margin-bottom: 2.5rem; border: 1px solid var(--color-border); }
        .action-toggle-option { flex: 1; text-align: center; padding: 0.8rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .action-toggle-option.active { background: var(--color-primary); color: #101012; box-shadow: 0 4px 15px var(--color-primary-glow); }
        
        .form-section-content { display: none; animation: fadeIn 0.5s ease; }
        .form-section-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; color: var(--color-text-muted); font-weight: 500; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .form-group input, .form-group select { width: 100%; background: rgba(0,0,0,0.2); border: 1.5px solid var(--color-border); border-radius: 10px; color: var(--color-text-light); padding: 0.8rem 1rem; font-size: 1rem; font-family: 'Poppins', sans-serif; transition: border-color 0.3s, box-shadow 0.3s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); }
        .form-group select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23a0a0a0'%3E%3Cpath fill-rule='evenodd' d='M8 11.646l-4.854-4.853.708-.708L8 10.23l4.146-4.145.708.708L8 11.646z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1em; padding-right: 2.5rem; }
        
        /* --- SELECCIÓN DE ALUMNOS MEJORADA --- */
        #hijos-counter { margin-top: -1rem; margin-bottom: 1.5rem; font-weight: 500; color: var(--color-text-muted); text-align: right; }
        #hijos-counter span { color: var(--color-primary); font-weight: 700; }
        .alumnos-selection-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; max-height: 250px; overflow-y: auto; background-color: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px; border: 1.5px solid var(--color-border); }
        .alumno-check-card {
            background-color: var(--color-surface); border: 2px solid var(--color-border); border-radius: 10px;
            padding: 0.8rem 1rem; display: flex; align-items: center; gap: 0.75rem;
            cursor: pointer; transition: all 0.2s ease; position: relative;
        }
        .alumno-check-card:hover { border-color: var(--color-primary); }
        .alumno-check-card.selected { border-color: var(--color-success); background-color: rgba(0, 191, 166, 0.1); }
        .alumno-check-card input[type="checkbox"] { display: none; }
        .alumno-check-card .checkmark {
            position: absolute; top: 10px; right: 10px; font-size: 1.2rem;
            color: var(--color-success); transform: scale(0); opacity: 0;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s;
        }
        .alumno-check-card.selected .checkmark { transform: scale(1); opacity: 1; }
        
        .btn-submit { padding: 0.8rem 2.5rem; font-size: 1.1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; background: linear-gradient(90deg, var(--color-primary), #e07b00 60%, var(--color-secondary) 100%); border: none; border-radius: 12px; color: #101012; cursor: pointer; transition: transform 0.18s, box-shadow 0.18s; display: inline-flex; align-items: center; gap: 0.75rem; }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 8px 25px var(--color-primary-glow); }
        .btn-ghost { background: transparent; border: 2px solid var(--color-border); color: var(--color-text-muted); padding: 0.75rem 1.2rem; font-size: 0.9rem; font-weight: 600; border-radius: 10px; text-decoration: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-ghost:hover { color: var(--color-primary); border-color: var(--color-primary); transform: translateY(-2px); }
        
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
        <li><a href="/MOKUSO/alumnos/index.php" class="active"><i class="fas fa-users"></i> <span>Alumnos</span></a></li>
        <li><a href="/MOKUSO/asistencias/index.php"><i class="fas fa-calendar-check"></i> <span>Asistencias</span></a></li>
        <li><a href="/MOKUSO/pagos/registrar_pago.php"><i class="fas fa-dollar-sign"></i> <span>Pagos</span></a></li>
        <li><a href="/MOKUSO/logros/agregar_logro.php"><i class="fas fa-trophy"></i> <span>Añadir Logro</span></a></li>
    </ul>
    <div class="sidebar-footer"><a href="/MOKUSO/config/logout.php" class="btn-ghost" style="width: 100%;"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></div>
</aside>

<main class="main-content-wrapper">
    <div class="main-container">
        <div class="page-header">
            <h1>Crear / Vincular Acceso para Padre</h1>
            <a href="ver_alumnos.php" class="btn-ghost"><i class="fas fa-arrow-left me-1"></i> Volver a la Lista</a>
        </div>

        <div class="form-card">
            <form id="padreForm" action="crear_acceso_padre.php" method="POST">
                
                <div class="action-toggle">
                    <div id="toggle-vincular" class="action-toggle-option active" data-target="seccionVincular">Vincular a Padre Existente</div>
                    <div id="toggle-crear" class="action-toggle-option" data-target="seccionNuevoPadre">Crear Nuevo Padre</div>
                </div>

                <!-- SECCIÓN PARA VINCULAR A PADRE EXISTENTE -->
                <div id="seccionVincular" class="form-section-content active">
                    <div class="form-group">
                        <label for="padre_existente_id">Padres Registrados</label>
                        <select class="form-select" id="padre_existente_id" name="padre_existente_id">
                            <option value="">-- Selecciona un padre de la lista --</option>
                            <?php if ($result_padres_existentes && $result_padres_existentes->num_rows > 0) {
                                while ($padre = $result_padres_existentes->fetch_assoc()) {
                                    echo "<option value='{$padre['id']}'>".htmlspecialchars($padre['nombre'])." (Usuario: ".htmlspecialchars($padre['username']).")</option>";
                                }
                            } ?>
                        </select>
                    </div>
                </div>
                
                <!-- SECCIÓN PARA CREAR NUEVO PADRE -->
                <div id="seccionNuevoPadre" class="form-section-content">
                    <div class="form-grid">
                        <div class="form-group"><label for="padre_nombre">Nombre del Padre (*)</label><input type="text" class="form-control" id="padre_nombre" name="nombre"></div>
                        <div class="form-group"><label for="padre_telefono">Teléfono</label><input type="text" class="form-control" id="padre_telefono" name="telefono"></div>
                        <div class="form-group full-width"><label for="padre_direccion">Dirección</label><input type="text" class="form-control" id="padre_direccion" name="direccion"></div>
                        <div class="form-group"><label for="padre_username">Nombre de Usuario (Login) (*)</label><input type="text" class="form-control" id="padre_username" name="username"></div>
                        <div class="form-group"><label for="padre_password">Contraseña Temporal (*)</label><input type="text" class="form-control" id="padre_password" name="password"></div>
                        <div class="col-12 full-width"><small class="text-muted">(*) Campos requeridos para crear una nueva cuenta.</small></div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>Asignar Hijos</h4>
                    <p class="text-muted">Selecciona los alumnos que se vincularán a esta cuenta.</p>
                    <div id="hijos-counter"><span>0</span> alumnos seleccionados</div>
                    <div class="alumnos-selection-grid">
                        <?php if ($result_alumnos_disponibles && $result_alumnos_disponibles->num_rows > 0): ?>
                            <?php while ($alumno = $result_alumnos_disponibles->fetch_assoc()): ?>
                                <label class="alumno-check-card">
                                    <input type="checkbox" name="hijos_ids[]" value="<?php echo $alumno['id']; ?>">
                                    <span class="nombre"><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></span>
                                    <i class="fas fa-check-circle checkmark"></i>
                                </label>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted">No hay alumnos disponibles para asignar. Todos los alumnos ya tienen un padre vinculado.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-actions" style="border-top: 1.5px solid var(--color-border); padding-top: 2rem; margin-top: 1rem;">
                    <button type="submit" class="btn-submit"><i class="fas fa-link me-1"></i> Vincular / Crear</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleVincular = document.getElementById('toggle-vincular');
    const toggleCrear = document.getElementById('toggle-crear');
    const seccionVincular = document.getElementById('seccionVincular');
    const seccionNuevoPadre = document.getElementById('seccionNuevoPadre');
    const padreExistenteSelect = document.getElementById('padre_existente_id');
    const camposNuevoPadre = {
        nombre: document.getElementById('padre_nombre'),
        username: document.getElementById('padre_username'),
        password: document.getElementById('padre_password')
    };

    function setRequiredForNewParent(isRequired) {
        camposNuevoPadre.nombre.required = isRequired;
        camposNuevoPadre.username.required = isRequired;
        camposNuevoPadre.password.required = isRequired;
        padreExistenteSelect.required = !isRequired;
    }

    toggleVincular.addEventListener('click', () => {
        toggleVincular.classList.add('active');
        toggleCrear.classList.remove('active');
        seccionVincular.classList.add('active');
        seccionNuevoPadre.classList.remove('active');
        setRequiredForNewParent(false);
    });

    toggleCrear.addEventListener('click', () => {
        toggleCrear.classList.add('active');
        toggleVincular.classList.remove('active');
        seccionNuevoPadre.classList.add('active');
        seccionVincular.classList.remove('active');
        setRequiredForNewParent(true);
    });

    // Estado inicial
    setRequiredForNewParent(false);

    // Lógica para las tarjetas de selección de alumnos
    const alumnoCards = document.querySelectorAll('.alumno-check-card');
    const counterElement = document.getElementById('hijos-counter').querySelector('span');

    function updateCounter() {
        const selectedCount = document.querySelectorAll('.alumno-check-card.selected').length;
        counterElement.textContent = selectedCount;
    }

    alumnoCards.forEach(card => {
        card.addEventListener('click', function() {
            const checkbox = this.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            this.classList.toggle('selected', checkbox.checked);
            updateCounter();
        });
    });

    // Validar el formulario antes de enviarlo
    document.getElementById('padreForm').addEventListener('submit', function(e) {
        const selectedCount = document.querySelectorAll('.alumno-check-card.selected').length;
        if (selectedCount === 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Atención',
                text: 'Debes seleccionar al menos un alumno para vincular.',
                icon: 'warning',
                customClass: { popup: 'swal2-popup' } // Reutiliza el estilo de SweetAlert
            });
        }
    });
});
</script>

</body>
</html>
<?php
$conn->close();
?>
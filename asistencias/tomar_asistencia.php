<?php
session_start();
// Security check: Only allow teachers or admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

include '../config/db.php';

// --- PASO 1: PROCESAR EL FORMULARIO ANTES DE CUALQUIER HTML ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['alumnos_presentes'])) {
    $alumnos_presentes = $_POST['alumnos_presentes'];
    $fecha_hoy = date('Y-m-d');
    $asistencias_guardadas = 0;

    $sql = "INSERT INTO asistencias (alumno_id, fecha_asistencia) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        foreach ($alumnos_presentes as $alumno_id) {
            $id = (int)$alumno_id;
            $stmt->bind_param("is", $id, $fecha_hoy);
            if ($stmt->execute()) {
                $asistencias_guardadas++;
            }
        }
        $stmt->close();
    }
    
    // Guardamos el mensaje en una sesión flash para mostrarlo en la siguiente página
    $_SESSION['flash_message'] = [
        'message' => "Se guardaron correctamente {$asistencias_guardadas} asistencias.",
        'type' => 'success'
    ];
    // AHORA LA REDIRECCIÓN FUNCIONARÁ
    header("Location: lista_asistencias.php");
    exit();
}

// --- PASO 2: SI NO ES POST, CONTINUAMOS CARGANDO LA PÁGINA ---
// Incluimos el header solo después de que la lógica de redirección ha pasado
include '../templates/header.php';

// Obtener la lista de alumnos activos que NO han registrado asistencia hoy
$sql_alumnos = "
    SELECT a.id, a.nombre, a.apellidos, a.foto_perfil
    FROM alumnos a
    WHERE a.estado_membresia IN ('activa', 'exento')
    AND a.id NOT IN (SELECT alumno_id FROM asistencias WHERE fecha_asistencia = CURDATE())
    ORDER BY a.apellidos, a.nombre ASC
";
$alumnos_activos_result = $conn->query($sql_alumnos);
?>

<style>
    /* --- ESTILOS "DIGITAL DOJO" PARA TOMAR ASISTENCIA --- */
    .main-container {
        width: 100%; max-width: 1400px; margin: 0 auto;
        padding: 2rem 1.5rem 8rem; /* Añadir padding inferior para que el botón fijo no tape contenido */
    }
    .page-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;
    }
    .page-header .title-group h1 {
        font-family: 'Orbitron', sans-serif; color: var(--color-primary);
        font-size: 2.5rem; margin: 0 0 0.5rem 0;
        letter-spacing: 2px; text-shadow: 0 0 18px var(--color-primary-glow);
    }
    .page-header .title-group p { font-size: 1.2rem; color: var(--color-text-muted); margin: 0; }

    /* --- BARRA DE CONTROLES (Contador, Seleccionar Todos) --- */
    .controls-bar {
        background: var(--color-surface); border: 1.5px solid var(--color-border);
        border-radius: var(--border-radius); box-shadow: var(--shadow);
        backdrop-filter: blur(var(--backdrop-blur)); -webkit-backdrop-filter: blur(var(--backdrop-blur));
        padding: 1rem 1.5rem; margin-bottom: 2.5rem;
        display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: center; justify-content: space-between;
    }
    #selection-counter {
        font-size: 1.1rem; font-weight: 600; color: var(--color-text-light);
    }
    #selection-counter span {
        color: var(--color-primary); font-family: 'Orbitron', sans-serif;
    }
    
    /* --- GRID DE ASISTENCIA --- */
    .asistencia-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    .alumno-card {
        background: var(--color-surface); border: 2px solid var(--color-border);
        border-radius: var(--border-radius); padding: 1rem;
        display: flex; align-items: center; gap: 1rem;
        position: relative; cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        overflow: hidden;
    }
    .alumno-card:hover {
        transform: translateY(-5px);
        border-color: var(--color-primary);
        box-shadow: 0 8px 25px var(--color-primary-glow);
    }
    .alumno-card.selected {
        border-color: var(--color-success);
        background: rgba(0, 191, 166, 0.1);
    }
    .alumno-card .selection-checkmark { /* Icono de check para seleccionados */
        position: absolute; top: 10px; right: 10px;
        font-size: 1.5rem; color: var(--color-success);
        transform: scale(0); opacity: 0;
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s;
    }
    .alumno-card.selected .selection-checkmark {
        transform: scale(1); opacity: 1;
    }
    .alumno-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--color-border); }
    .alumno-nombre { font-weight: 600; font-size: 1rem; }
    .alumno-checkbox { display: none; }

    /* --- BOTÓN DE GUARDADO FIJO --- */
    .submit-bar {
        position: fixed; bottom: 0; left: 260px; /* Alineado con el sidebar */
        width: calc(100% - 260px);
        padding: 1rem;
        background: linear-gradient(to top, rgba(10, 10, 15, 0.95) 30%, transparent);
        text-align: center;
        z-index: 100;
        pointer-events: none; /* Permite hacer clic a través del fondo transparente */
    }
    .btn-submit { /* Botón principal grande */
        pointer-events: all; /* Hace que el botón sea clickeable */
        padding: 1rem 3rem; font-size: 1.2rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 1.2px;
        background: linear-gradient(90deg, var(--color-primary), #e07b00 60%, var(--color-secondary) 100%);
        border: none; border-radius: 12px; color: #101012; cursor: pointer;
        transition: transform 0.18s, box-shadow 0.18s;
        display: inline-flex; align-items: center; gap: 0.75rem;
    }
    .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 8px 25px var(--color-primary-glow); }

    /* --- ESTADO VACÍO --- */
    .empty-state {
        background: var(--color-surface); border: 1.5px solid var(--color-border);
        border-radius: var(--border-radius); box-shadow: var(--shadow);
        backdrop-filter: blur(var(--backdrop-blur));
        padding: 4rem 2rem; text-align: center;
    }
    .empty-state i { font-size: 4rem; color: var(--color-success); margin-bottom: 1.5rem; }
    .empty-state h3 { font-family: 'Orbitron', sans-serif; font-size: 1.8rem; margin-bottom: 0.5rem; }
    .empty-state p { color: var(--color-text-muted); font-size: 1.1rem; max-width: 500px; margin: 0 auto 1.5rem auto; }

     /* --- RESPONSIVE --- */
    @media (max-width: 992px) {
        .main-content-wrapper { margin-left: 0; width: 100%; }
        .submit-bar { left: 0; width: 100%; }
    }
</style>

<div class="main-container">
    <div class="page-header">
        <div class="title-group">
            <h1>Tomar Asistencia</h1>
            <p class="text-muted fs-4">Hoy es <?php echo date("d/m/Y"); ?>. Selecciona los alumnos presentes.</p>
        </div>
        <a href="index.php" class="btn-ghost"><i class="fas fa-arrow-left me-1"></i>Volver al Centro</a>
    </div>

    <?php if ($alumnos_activos_result && $alumnos_activos_result->num_rows > 0): ?>
        <form action="tomar_asistencia.php" method="POST" id="asistenciaForm">
            <div class="controls-bar">
                <div id="selection-counter"><span>0</span> alumnos seleccionados</div>
                <button type="button" id="selectAllBtn" class="btn-ghost">Seleccionar Todos</button>
            </div>

            <div class="asistencia-grid mb-4">
                <?php mysqli_data_seek($alumnos_activos_result, 0); while($alumno = $alumnos_activos_result->fetch_assoc()): ?>
                    <label class="alumno-card">
                        <input type="checkbox" name="alumnos_presentes[]" value="<?php echo $alumno['id']; ?>" class="alumno-checkbox">
                        <?php $foto_url = '/MOKUSO/assets/img/uploads/' . (!empty($alumno['foto_perfil']) ? htmlspecialchars($alumno['foto_perfil']) : 'default.png'); ?>
                        <img src="<?php echo $foto_url; ?>" class="alumno-avatar" alt="Avatar">
                        <span class="alumno-nombre"><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></span>
                        <i class="fas fa-check-circle selection-checkmark"></i>
                    </label>
                <?php endwhile; ?>
            </div>
            
            <div class="submit-bar">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save me-2"></i> Guardar Asistencias
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-check"></i>
            <h3>¡Todo listo por hoy!</h3>
            <p>Todos los alumnos activos ya han registrado su asistencia para el día de hoy. ¡Buen trabajo!</p>
            <a href="lista_asistencias.php" class="btn-primary-gradient">Ver Resumen de Hoy</a>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const alumnoCards = document.querySelectorAll('.alumno-card');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const counterElement = document.getElementById('selection-counter');
    const form = document.getElementById('asistenciaForm');

    function updateCounter() {
        if (!counterElement) return;
        const selectedCount = document.querySelectorAll('.alumno-checkbox:checked').length;
        counterElement.innerHTML = `<span>${selectedCount}</span> alumno${selectedCount !== 1 ? 's' : ''} seleccionado${selectedCount !== 1 ? 's' : ''}`;
    }

    alumnoCards.forEach(card => {
        card.addEventListener('click', function() {
            const checkbox = this.querySelector('.alumno-checkbox');
            checkbox.checked = !checkbox.checked;
            this.classList.toggle('selected', checkbox.checked);
            updateCounter();
        });
    });

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const allSelected = document.querySelectorAll('.alumno-checkbox:checked').length === alumnoCards.length;
            
            alumnoCards.forEach(card => {
                const checkbox = card.querySelector('.alumno-checkbox');
                checkbox.checked = !allSelected;
                card.classList.toggle('selected', !allSelected);
            });

            this.textContent = allSelected ? 'Seleccionar Todos' : 'Deseleccionar Todos';
            updateCounter();
        });
    }
    
    // Prevenir envío si no hay nadie seleccionado
    if(form) {
        form.addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('.alumno-checkbox:checked').length;
            if (selectedCount === 0) {
                e.preventDefault(); // Detener el envío del formulario
                // Puedes usar SweetAlert2 si está disponible globalmente
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Atención',
                        text: "Debes seleccionar al menos un alumno para guardar la asistencia.",
                        icon: 'warning',
                        customClass: { popup: 'swal2-popup' } // Aplica el estilo del header
                    });
                } else {
                    alert('Debes seleccionar al menos un alumno.');
                }
            }
        });
    }

    updateCounter();
});
</script>

<?php include '../templates/footer.php'; ?>
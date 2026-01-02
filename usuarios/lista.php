<?php
session_start();
include '../config/db.php'; 

// 1. VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'maestro') {
    header("Location: /MOKUSO/index.php"); 
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
$edit_mode = false;
$edit_user = [
    'id' => '',
    'username' => '',
    'role' => 'alumno',
    'password' => '' 
];

// 2. PROCESAMIENTO (CRUD) - Lógica que se ejecuta antes de cargar la página
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- CREAR ---
    if (isset($_POST['action']) && $_POST['action'] == 'crear') {
        $username = $_POST['username'];
        $password = $_POST['password']; 
        $role = $_POST['role'];
        try {
            $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $username, $password, $role);
            $stmt->execute();
            header("Location: lista.php?status=creado"); exit();
        } catch (mysqli_sql_exception $e) {
            $mensaje = ($e->getCode() == 1062) ? "El usuario ya existe." : "Error: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
    // --- ACTUALIZAR ---
    if (isset($_POST['action']) && $_POST['action'] == 'actualizar') {
        $user_id = $_POST['user_id'];
        $username = $_POST['username'];
        $role = $_POST['role'];
        $password = $_POST['password'];
        try {
            if (!empty($password)) {
                $sql = "UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $username, $password, $role, $user_id);
            } else {
                $sql = "UPDATE users SET username = ?, role = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $username, $role, $user_id);
            }
            $stmt->execute();
            header("Location: lista.php?status=actualizado"); exit();
        } catch (mysqli_sql_exception $e) {
            $mensaje = ($e->getCode() == 1062) ? "El usuario ya existe." : "Error: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
    // --- ELIMINAR ---
    if (isset($_POST['action']) && $_POST['action'] == 'eliminar') {
        if ($_POST['user_id'] == $_SESSION['user_id']) {
            $mensaje = "No puedes eliminar tu propia cuenta."; $tipo_mensaje = 'error';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $_POST['user_id']);
            $stmt->execute();
            header("Location: lista.php?status=eliminado"); exit();
        }
    }
}

// 3. OBTENER DATOS
if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_GET['edit_id']);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}

$sql_users = "SELECT id, username, password, role, alumno_id FROM users ORDER BY id ASC";
$result_users = $conn->query($sql_users);

// 4. PREPARAR ALERTAS
$swal_script = "";
if (isset($_GET['status'])) {
    $t = ($_GET['status']=='creado') ? 'Creado' : (($_GET['status']=='actualizado') ? 'Actualizado' : 'Eliminado');
    $swal_script = "Swal.fire({icon: 'success', title: '$t', text: 'Operación exitosa', background: '#202024', color: '#fff', confirmButtonColor: '#ff6600'});";
}
if (!empty($mensaje)) {
    $icon = ($tipo_mensaje == 'error') ? 'error' : 'success';
    $swal_script = "Swal.fire({icon: '$icon', title: 'Atención', text: '$mensaje', background: '#202024', color: '#fff', confirmButtonColor: '#ff6600'});";
}

// INCLUIR EL HEADER DE NAVEGACIÓN ORIGINAL
include '../templates/header.php'; 
?>

<!-- Librerías adicionales necesarias para el diseño Elite -->
<script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* --- TEMA MOKUSO ELITE (Aplicado solo al contenido) --- */
    :root {
        --primary: #ff6600; --primary-glow: rgba(255, 102, 0, 0.3);
        --surface: #202024; --border: #323238;
        --text-white: #e1e1e6; --text-muted: #a8a8b3;
        --success: #04d361; --danger: #ff3e3e; --info: #00bfff;
        --radius: 16px;
    }

    /* Forzamos fondo oscuro para esta página específica si el header no lo tiene */
    body {
        background-color: #121214; 
        color: var(--text-white);
        font-family: 'Poppins', sans-serif;
    }

    .main-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }

    /* Layout Grid */
    .dashboard-grid { 
        display: grid; 
        grid-template-columns: 1fr 2fr; 
        gap: 2rem; 
        margin-top: 2rem; 
    }
    @media(max-width: 992px) { 
        .dashboard-grid { grid-template-columns: 1fr; } 
    }

    /* Encabezado de Página */
    .page-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 2rem; 
        border-bottom: 1px solid var(--border); 
        padding-bottom: 1rem; 
    }
    .page-title { 
        font-family: 'Orbitron', sans-serif; 
        font-size: 1.8rem; 
        color: var(--text-white); 
        margin: 0; 
        display: flex; 
        align-items: center; 
        gap: 10px; 
    }

    /* Tarjetas Estilo Cristal */
    .card { 
        background: var(--surface); 
        border: 1px solid var(--border); 
        border-radius: var(--radius); 
        padding: 1.5rem; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .card-header { margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
    .card-title { font-family: 'Orbitron'; color: var(--primary); margin: 0; font-size: 1.2rem; }

    /* Formularios */
    .form-group { margin-bottom: 1.2rem; }
    .form-group label { display: block; color: var(--text-muted); margin-bottom: 0.5rem; font-size: 0.9rem; }
    .form-control-elite { 
        width: 100%; background: #18181b; border: 1px solid var(--border); color: white; 
        padding: 10px; border-radius: 8px; outline: none; transition: 0.3s; box-sizing: border-box;
    }
    .form-control-elite:focus { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-glow); }

    /* Botones */
    .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: 0.3s; gap: 8px; }
    .btn-primary { background: linear-gradient(90deg, var(--primary), #e07b00); color: #000; width: 100%; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 102, 0, 0.3); }
    .btn-secondary { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
    .btn-secondary:hover { border-color: var(--text-white); color: var(--text-white); }

    /* Tabla */
    table.dataTable { background: transparent !important; color: var(--text-white) !important; width: 100% !important; border-collapse: collapse !important; }
    table.dataTable thead th { background: rgba(0,0,0,0.2) !important; color: var(--primary) !important; border-bottom: 1px solid var(--border) !important; font-family: 'Orbitron'; font-size: 0.8rem; text-transform: uppercase; }
    table.dataTable tbody td { border-bottom: 1px solid var(--border) !important; padding: 12px 8px !important; vertical-align: middle; }
    
    .password-field { 
        font-family: monospace; background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 4px; 
        color: var(--success); letter-spacing: 1px; font-size: 0.9rem; border: 1px solid var(--border);
    }

    .role-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
    .role-maestro { background: rgba(0, 191, 255, 0.15); color: #00bfff; border: 1px solid #00bfff; }
    .role-alumno { background: rgba(4, 211, 97, 0.15); color: var(--success); border: 1px solid var(--success); }
    .role-padre { background: rgba(217, 70, 239, 0.15); color: #d946ef; border: 1px solid #d946ef; }

    .action-btn { background: transparent; border: 1px solid var(--border); color: var(--text-muted); width: 32px; height: 32px; border-radius: 6px; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; margin-left: 5px; text-decoration: none; }
    .action-btn:hover { border-color: var(--text-white); color: var(--text-white); }
    .action-btn.delete:hover { border-color: var(--danger); color: var(--danger); }

    /* DataTables Overrides */
    .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: var(--text-muted) !important; }
    .dataTables_wrapper input, .dataTables_wrapper select { background: #18181b; border: 1px solid var(--border); color: white; border-radius: 6px; padding: 4px; }
</style>

<div class="main-container">
    
    <div class="page-header">
        <div class="page-title"><i class="ph-fill ph-users-three"></i> Gestión de Usuarios</div>
        <!-- Enlace de vuelta que respeta el historial o va al dashboard -->
        <a href="../dashboard/index.php" class="btn btn-secondary"><i class="ph-bold ph-arrow-left"></i> Volver</a>
    </div>

    <div class="dashboard-grid">
        
        <!-- COLUMNA IZQUIERDA: FORMULARIO -->
        <div class="form-column">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?php echo $edit_mode ? 'Editar Usuario' : 'Crear Usuario'; ?></h3>
                </div>
                
                <form method="POST" action="lista.php">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="action" value="actualizar">
                        <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="action" value="crear">
                    <?php endif; ?>

                    <div class="form-group">
                        <label><i class="ph-bold ph-user"></i> Nombre de Usuario</label>
                        <input type="text" name="username" class="form-control-elite" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label><i class="ph-bold ph-lock"></i> Contraseña <?php echo $edit_mode ? '(Opcional)' : ''; ?></label>
                        <input type="text" name="password" class="form-control-elite" placeholder="Ej: 12345" <?php echo $edit_mode ? '' : 'required'; ?> autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label><i class="ph-bold ph-identification-badge"></i> Rol</label>
                        <select name="role" class="form-control-elite">
                            <option value="alumno" <?php echo ($edit_user['role'] == 'alumno') ? 'selected' : ''; ?>>Alumno</option>
                            <option value="maestro" <?php echo ($edit_user['role'] == 'maestro') ? 'selected' : ''; ?>>Maestro</option>
                            <option value="padre" <?php echo ($edit_user['role'] == 'padre') ? 'selected' : ''; ?>>Padre</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="ph-bold ph-floppy-disk"></i> <?php echo $edit_mode ? 'Guardar Cambios' : 'Crear Usuario'; ?>
                    </button>

                    <?php if ($edit_mode): ?>
                        <a href="lista.php" class="btn btn-secondary" style="width:100%; margin-top:10px; display:block; text-align:center;">Cancelar</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- COLUMNA DERECHA: TABLA -->
        <div class="list-column">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Usuarios del Sistema</h3>
                </div>
                <table id="tabla-usuarios" class="display">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Contraseña</th>
                            <th>Rol</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_users->num_rows > 0): ?>
                            <?php while($row = $result_users->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight:600;"><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><span class="password-field"><?php echo htmlspecialchars($row['password']); ?></span></td>
                                    <td><span class="role-badge role-<?php echo $row['role']; ?>"><?php echo ucfirst($row['role']); ?></span></td>
                                    <td style="text-align:right;">
                                        <a href="lista.php?edit_id=<?php echo $row['id']; ?>" class="action-btn" title="Editar">
                                            <i class="ph-bold ph-pencil-simple"></i>
                                        </a>
                                        <button type="button" onclick="confirmarEliminar(<?php echo $row['id']; ?>, '<?php echo $row['username']; ?>')" class="action-btn delete" title="Eliminar">
                                            <i class="ph-bold ph-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Formulario oculto para eliminar -->
<form id="form-eliminar" method="POST" style="display:none;">
    <input type="hidden" name="action" value="eliminar">
    <input type="hidden" name="user_id" id="eliminar_id">
</form>

<script>
    $(document).ready(function() {
        $('#tabla-usuarios').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            pageLength: 8,
            lengthChange: false,
            dom: 'ftp',
            order: [[ 0, 'asc' ]]
        });

        // Ejecutar alerta PHP si existe
        <?php echo $swal_script; ?>
    });

    function confirmarEliminar(id, nombre) {
        Swal.fire({
            title: '¿Eliminar a ' + nombre + '?',
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff3e3e',
            cancelButtonColor: '#323238',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            background: '#202024', color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('eliminar_id').value = id;
                document.getElementById('form-eliminar').submit();
            }
        });
    }
</script>

<?php 
// INCLUIR EL FOOTER
include '../templates/footer.php'; 
$conn->close(); 
?>
<?php
session_start();

include '../config/db.php';

// 1. Recibir y validar datos
$padre_existente_id = isset($_POST['padre_existente_id']) ? intval($_POST['padre_existente_id']) : 0;
$hijos_ids = isset($_POST['hijos_ids']) ? $_POST['hijos_ids'] : [];

$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
$direccion = isset($_POST['direccion']) ? trim($_POST['direccion']) : '';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : ''; 

if (!is_array($hijos_ids)) {
    $hijos_ids = [$hijos_ids];
}
$hijos_ids = array_map('intval', $hijos_ids);
$hijos_ids = array_filter($hijos_ids);

if (empty($hijos_ids)) {
    $_SESSION['error_message'] = "Error: Debes seleccionar al menos un alumno para vincular.";
    header("Location: crear_acceso_padre_form.php?error=no_alumnos");
    exit();
}

// 2. Iniciar la Transacción
$conn->begin_transaction();
$padre_id_final = $padre_existente_id;

try {
    // A) ESCENARIO: CREAR UN NUEVO PADRE Y CUENTA DE USUARIO
    if ($padre_existente_id == 0) {
        if (empty($nombre) || empty($username) || empty($password)) {
            throw new Exception("Error: Faltan datos obligatorios (Nombre, Usuario y Contraseña) para crear una nueva cuenta.");
        }
        
        // 2a. Crear la cuenta de Usuario (users)
        $role = 'padre';
        $stmt_user = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt_user->bind_param("sss", $username, $password, $role);
        
        if (!$stmt_user->execute()) {
            if ($conn->errno == 1062) { 
                throw new Exception("Error: El nombre de usuario '{$username}' ya existe. Por favor, elige otro.");
            }
            throw new Exception("Error al crear el usuario: " . $stmt_user->error);
        }
        $user_id = $stmt_user->insert_id;
        $stmt_user->close();

        // 2b. Crear el registro del Padre (padres)
        $stmt_padre = $conn->prepare("INSERT INTO padres (user_id, nombre, telefono, direccion) VALUES (?, ?, ?, ?)");
        $stmt_padre->bind_param("isss", $user_id, $nombre, $telefono, $direccion);
        if (!$stmt_padre->execute()) {
            throw new Exception("Error al registrar los datos del padre: " . $stmt_padre->error);
        }
        $padre_id_final = $stmt_padre->insert_id;
        $stmt_padre->close();
        
    } else {
        // B) ESCENARIO: VINCULAR A UN PADRE EXISTENTE (solo se necesita el ID)
        $padre_id_final = $padre_existente_id;
    }

    // 3. VINCULAR LOS ALUMNOS al Padre en la tabla 'padres_alumnos'
    $stmt_vinculo = $conn->prepare("INSERT INTO padres_alumnos (padre_id, alumno_id) VALUES (?, ?)");
    
    foreach ($hijos_ids as $alumno_id) {
        $stmt_vinculo->bind_param("ii", $padre_id_final, $alumno_id);
        // Se ignora el error si el vínculo ya existe (duplicado)
        if (!$stmt_vinculo->execute() && $conn->errno != 1062) { 
            throw new Exception("Error al vincular alumno ID {$alumno_id}: " . $stmt_vinculo->error);
        }
    }
    $stmt_vinculo->close();

    // BLOQUE ELIMINADO: La actualización a 'alumnos.padre_id' ya no es necesaria.
    // La tabla `padres_alumnos` ahora maneja toda la relación.

    // 4. Confirmar la transacción
    $conn->commit();

    // 5. Redirigir con éxito
    // Puedes añadir un mensaje de éxito a la sesión si lo deseas
    // $_SESSION['success_message'] = "¡Acceso de padre creado/vinculado correctamente!";
    header("Location: ../alumnos/index.php?status=padre_success");
    exit();

} catch (Exception $e) {
    // 6. Si ocurre un error, revertir la transacción
    $conn->rollback();
    
    // Guardar el mensaje de error en la sesión para mostrarlo en el formulario
    $_SESSION['error_message'] = $e->getMessage();
    
    header("Location: crear_acceso_padre_form.php?error=processing_failed");
    exit();
}

$conn->close();
?>
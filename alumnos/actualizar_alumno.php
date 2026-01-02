<?php
// Habilitar la visualización de todos los errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../config/db.php';

// Validar que la solicitud sea de tipo POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // Es mejor redirigir con un error que simplemente morir.
    header("Location: index.php?status=error_method");
    exit();
}

// 1. Recolección y Limpieza de Datos
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    header("Location: index.php?status=error_id");
    exit();
}

// Recopilación de datos. Si el campo está vacío, se asigna NULL.
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$apellidos = isset($_POST['apellidos']) ? trim($_POST['apellidos']) : '';
$direccion = !empty(trim($_POST['direccion'])) ? trim($_POST['direccion']) : null;
$email = !empty($_POST['email']) ? $_POST['email'] : null;
$telefono = !empty($_POST['telefono']) ? $_POST['telefono'] : null;
$telefono_emergencia = !empty($_POST['telefono_emergencia']) ? $_POST['telefono_emergencia'] : '';
$fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
$peso = !empty($_POST['peso']) ? floatval($_POST['peso']) : null;
$estatura = !empty($_POST['estatura']) ? floatval($_POST['estatura']) : null;
$talla_dojo = !empty(trim($_POST['talla_dojo'])) ? trim($_POST['talla_dojo']) : null;
$estudiante = !empty($_POST['estudiante']) ? $_POST['estudiante'] : 'No';
$estado_membresia = !empty($_POST['estado_membresia']) ? $_POST['estado_membresia'] : 'pendiente';
$fecha_vencimiento_membresia = !empty($_POST['fecha_vencimiento_membresia']) ? $_POST['fecha_vencimiento_membresia'] : null;
$foto_actual = isset($_POST['foto_actual']) ? $_POST['foto_actual'] : 'default.png';

// MODIFICADO: Se reciben los IDs numéricos del formulario
$disciplina_id = !empty($_POST['disciplina_id']) ? (int)$_POST['disciplina_id'] : null;
$nivel_id = !empty($_POST['nivel_id']) ? (int)$_POST['nivel_id'] : null;
$grupo_id = !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : null;
$tipo_pago_id = !empty($_POST['tipo_pago_id']) ? (int)$_POST['tipo_pago_id'] : null;

// Lógica de Subida de Nueva Imagen (sin cambios)
$foto_nombre_para_db = $foto_actual;
if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../assets/img/uploads/';
    $extension = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
    $extensiones_validas = array('jpg', 'jpeg', 'png', 'gif');

    if (in_array($extension, $extensiones_validas)) {
        // Borra la foto anterior solo si no es la de por defecto
        if ($foto_actual != 'default.png' && file_exists($upload_dir . $foto_actual)) {
            unlink($upload_dir . $foto_actual);
        }
        $nombre_final = 'alumno_' . time() . '_' . uniqid() . '.' . $extension;
        if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $upload_dir . $nombre_final)) {
            $foto_nombre_para_db = $nombre_final;
        }
    }
}

// NUEVO: Se obtiene la cuota mensual correcta desde la tabla tipos_pago
$cuota_mensual = null;
if ($tipo_pago_id) {
    $stmt_pago = $conn->prepare("SELECT monto FROM tipos_pago WHERE id = ?");
    $stmt_pago->bind_param("i", $tipo_pago_id);
    $stmt_pago->execute();
    $result_pago = $stmt_pago->get_result(); // Esto requiere mysqlnd
    if($row_pago = $result_pago->fetch_assoc()) {
        $cuota_mensual = $row_pago['monto'];
    }
    $stmt_pago->close();
}

// 4. Actualización Segura en la Base de Datos
try {
    // MODIFICADO: La consulta UPDATE ahora usa las columnas _id
    $sql = "UPDATE alumnos SET
                nombre = ?, apellidos = ?, direccion = ?, email = ?, telefono = ?,
                telefono_emergencia = ?, fecha_nacimiento = ?, foto_perfil = ?,
                peso = ?, estatura = ?, talla_dojo = ?, estudiante = ?, 
                cuota_mensual = ?, estado_membresia = ?, fecha_vencimiento_membresia = ?,
                disciplina_id = ?, nivel_id = ?, grupo_id = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    // MODIFICADO: La cadena de tipos y las variables se ajustan a la nueva consulta
    $stmt->bind_param(
        "ssssssssddssdsssiii",
        $nombre,
        $apellidos,
        $direccion,
        $email,
        $telefono,
        $telefono_emergencia,
        $fecha_nacimiento,
        $foto_nombre_para_db,
        $peso,
        $estatura,
        $talla_dojo,
        $estudiante,
        $cuota_mensual,
        $estado_membresia,
        $fecha_vencimiento_membresia,
        $disciplina_id,
        $nivel_id,
        $grupo_id,
        $id
    );

    if ($stmt->execute()) {
        header("Location: index.php?status=success_update");
        exit();
    } else {
        throw new Exception("Error al ejecutar la actualización: " . $stmt->error);
    }

} catch (Exception $e) {
    // Redirigir de vuelta con un mensaje de error
    header("Location: editar_alumno.php?id=" . $id . "&status=error&message=" . urlencode($e->getMessage()));
    exit();
}

$stmt->close();
$conn->close();
?>
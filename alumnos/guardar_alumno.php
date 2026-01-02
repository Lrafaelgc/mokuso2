<?php
include '../config/db.php';

// 1. Validar que la solicitud sea de tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php?status=error_method");
    exit();
}

// 2. Recoger y sanear los datos del formulario
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$apellidos = isset($_POST['apellidos']) ? trim($_POST['apellidos']) : '';
$direccion = isset($_POST['direccion']) ? trim($_POST['direccion']) : null;
$fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
$telefono = isset($_POST['telefono']) ? $_POST['telefono'] : null;
$telefono_emergencia = isset($_POST['telefono_emergencia']) ? $_POST['telefono_emergencia'] : '';
$email = isset($_POST['email']) ? $_POST['email'] : null;
$peso = !empty($_POST['peso']) ? (float)$_POST['peso'] : null;
$estatura = !empty($_POST['estatura']) ? (float)$_POST['estatura'] : null;
$talla_dojo = isset($_POST['talla_dojo']) ? $_POST['talla_dojo'] : null;
$estudiante = isset($_POST['estudiante']) ? $_POST['estudiante'] : 'No';

// IDs de las nuevas tablas
$disciplina_id = !empty($_POST['disciplina_id']) ? (int)$_POST['disciplina_id'] : null;
$nivel_id = !empty($_POST['nivel_id']) ? (int)$_POST['nivel_id'] : null;
$grupo_id = !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : null;
$tipo_pago_id = !empty($_POST['tipo_pago_id']) ? (int)$_POST['tipo_pago_id'] : null;

// Validaciones básicas
if (empty($nombre) || empty($apellidos) || empty($telefono_emergencia)) {
    header("Location: registrar.php?status=error&message=" . urlencode("Nombre, apellidos y teléfono de emergencia son obligatorios."));
    exit();
}

// 3. Procesar la subida de la foto de perfil
$foto_perfil = 'default.png';
if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../assets/img/uploads/';
    if (!is_dir($upload_dir)) { 
        mkdir($upload_dir, 0777, true);
    }
    $fileTmpPath = $_FILES['foto_perfil']['tmp_name'];
    $fileExtension = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
    $newFileName = 'alumno_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $dest_path = $upload_dir . $newFileName;
    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($fileExtension, $allowedfileExtensions) && move_uploaded_file($fileTmpPath, $dest_path)) {
        $foto_perfil = $newFileName;
    }
}

// 4. Obtener el monto de la cuota desde la tabla tipos_pago
$cuota_mensual = null;
if ($tipo_pago_id) {
    $stmt_pago = $conn->prepare("SELECT monto FROM tipos_pago WHERE id = ?");
    $stmt_pago->bind_param("i", $tipo_pago_id);
    $stmt_pago->execute();
    $result_pago = $stmt_pago->get_result(); // Esto requiere mysqlnd, si falla, hay que usar get_result_manual
    if($row_pago = $result_pago->fetch_assoc()) {
        $cuota_mensual = $row_pago['monto'];
    }
    $stmt_pago->close();
}

// 5. Preparar la sentencia SQL para la inserción
try {
    $sql = "INSERT INTO alumnos (
                nombre, apellidos, direccion, fecha_nacimiento, telefono, telefono_emergencia,
                email, peso, estatura, talla_dojo, foto_perfil, estudiante, 
                cuota_mensual, disciplina_id, nivel_id, grupo_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar la sentencia: " . $conn->error);
    }
    
    // Cadena de tipos corregida
    $stmt->bind_param("sssssssddsssdiii", 
        $nombre, 
        $apellidos, 
        $direccion,
        $fecha_nacimiento, 
        $telefono, 
        $telefono_emergencia, 
        $email, 
        $peso, 
        $estatura, 
        $talla_dojo, 
        $foto_perfil,
        $estudiante,
        $cuota_mensual,
        $disciplina_id,
        $nivel_id,
        $grupo_id
    );

    if ($stmt->execute()) {
        header("Location: index.php?status=success_add");
    } else {
        throw new Exception("Error al ejecutar la sentencia: " . $stmt->error);
    }

} catch (Exception $e) {
    header("Location: registrar.php?status=error&message=" . urlencode("Error en el servidor: " . $e->getMessage()));
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
}
?>
<?php
session_start();
include '../config/db.php';

// Asegura que solo un maestro pueda ejecutar este script
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'maestro') {
    header("Location: /MOKUSO/ver_alumnos.php");
    exit();
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $alumno_id = intval($_GET['id']);
    
    // Obtener los datos del alumno (nombre y fecha_registro)
    $sql_alumno = "SELECT nombre, fecha_registro FROM alumnos WHERE id = ?";
    $stmt = $conn->prepare($sql_alumno);
    $stmt->bind_param("i", $alumno_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $alumno = $result->fetch_assoc();
        $nombre = $alumno['nombre'];
        $fecha_registro = new DateTime($alumno['fecha_registro']);

        
        // Crear credenciales por defecto
        $username = strtolower(str_replace(' ', '', $nombre)) . $alumno_id;
        $password = $fecha_registro->format('dmy');

        if ($action == 'crear') {
            // Inserta el nuevo usuario con la clave sin encriptar
            $sql_insert = "INSERT INTO users (username, password, role, alumno_id) VALUES (?, ?, 'alumno', ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("ssi", $username, $password, $alumno_id);
            
            if ($stmt_insert->execute()) {
                // Guarda las credenciales en la sesión
                $_SESSION['temp_user'] = $username;
                $_SESSION['temp_pass'] = $password;
                $_SESSION['temp_status'] = 'acceso_creado';
                header("Location: ver_alumnos.php");
            } else {
                header("Location: ver_alumnos.php?error=acceso_fallido");
            }
            $stmt_insert->close();

        } else if ($action == 'restablecer') {
            // Actualiza la contraseña del usuario con la clave sin encriptar
            $sql_update = "UPDATE users SET password = ? WHERE alumno_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("si", $password, $alumno_id);
            
            if ($stmt_update->execute()) {
                // Guarda las credenciales en la sesión
                $_SESSION['temp_user'] = $username;
                $_SESSION['temp_pass'] = $password;
                $_SESSION['temp_status'] = 'acceso_restablecido';
                header("Location: ver_alumnos.php");
            } else {
                header("Location: ver_alumnos.php?error=acceso_fallido");
            }
            $stmt_update->close();
        }
    } else {
        header("Location: ver_alumnos.php?error=acceso_fallido");
    }
    
    $stmt->close();
    $conn->close();
    exit();
}

header("Location: ver_alumnos.php");
exit();
?>
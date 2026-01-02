<?php
session_start();
header('Content-Type: application/json');

include '../config/db.php'; 

// --- CAMBIO 1: DEFINIR ZONA HORARIA ---
// Esto es vital. Configúralo a tu zona. 
// Para San Luis Río Colorado/Tijuana usa: 'America/Tijuana'
// Para Hermosillo/Sonora usa: 'America/Hermosillo'
// Para Centro de México usa: 'America/Mexico_City'
date_default_timezone_set('America/Tijuana'); 

$response = ['status' => 'error', 'message' => 'Solicitud inválida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alumno_id = isset($_POST['alumno_id']) ? intval($_POST['alumno_id']) : 0;
    
    // --- CAMBIO 2: IGNORAR LA FECHA QUE ENVÍA EL JS ---
    // En lugar de leer $_POST['fecha_asistencia'], usamos la del servidor.
    // Esto evita que si son las 8PM en tu ciudad, pero el servidor cree que es UTC, marque mañana.
    $fecha_asistencia = date('Y-m-d'); 
    
    // --- 1. VERIFICACIÓN DE PERMISOS ---
    $acceso_permitido = false;

    $user_id_sesion = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'invitado';

    if ($role === 'maestro') {
        $acceso_permitido = true;
        // Opcional: Si el maestro quiere registrar una fecha pasada manual, 
        // podrías permitir recibir el POST solo si es maestro.
        if(isset($_POST['fecha_asistencia']) && !empty($_POST['fecha_asistencia'])) {
             $fecha_asistencia = trim($_POST['fecha_asistencia']);
        }

    } elseif ($role === 'padre' && $user_id_sesion > 0) {
        
        $sql_get_padre_id = "SELECT id FROM padres WHERE user_id = ?";
        $stmt_padre_id = $conn->prepare($sql_get_padre_id);
        $padre_id = null;
        if ($stmt_padre_id && $conn) {
            $stmt_padre_id->bind_param("i", $user_id_sesion);
            $stmt_padre_id->execute();
            $result_padre_id = $stmt_padre_id->get_result();
            if ($row = $result_padre_id->fetch_assoc()) {
                $padre_id = $row['id'];
            }
            $stmt_padre_id->close();
        }

        if ($padre_id !== null) {
            $sql_verificar_acceso = "SELECT 1 FROM padres_alumnos WHERE padre_id = ? AND alumno_id = ?";
            $stmt_verificar = $conn->prepare($sql_verificar_acceso);
            if ($stmt_verificar) {
                $stmt_verificar->bind_param("ii", $padre_id, $alumno_id);
                $stmt_verificar->execute();
                $stmt_verificar->store_result();
                if ($stmt_verificar->num_rows > 0) {
                    $acceso_permitido = true;
                }
                $stmt_verificar->close();
            }
        }
    } elseif ($role === 'alumno' && isset($_SESSION['alumno_id']) && $_SESSION['alumno_id'] == $alumno_id) {
        // Un alumno solo puede marcar su PROPIA asistencia.
        $acceso_permitido = true;
    }

    if (!$acceso_permitido) {
        $response['message'] = 'Acceso denegado o alumno no vinculado.';
        echo json_encode($response);
        exit();
    }
    
    // --- 2. VERIFICACIÓN DE ESTADO DE MEMBRESÍA ---
    $sql_check_alumno = "SELECT estado_membresia FROM alumnos WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check_alumno);
    
    if ($stmt_check) {
        $stmt_check->bind_param("i", $alumno_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($row = $result_check->fetch_assoc()) {
            $estado_actual = strtolower($row['estado_membresia']);
            if (!in_array($estado_actual, ['activa', 'exento', 'pendiente'])) { // Agregué pendiente por si acaso
                $response['message'] = 'Error: Solo se puede marcar asistencia a miembros activos o exentos.';
                $stmt_check->close();
                echo json_encode($response);
                exit();
            }
        } else {
            $response['message'] = 'Error: Alumno no encontrado.';
            $stmt_check->close();
            echo json_encode($response);
            exit();
        }
        $stmt_check->close();
    }

    // --- 3. VERIFICAR SI YA SE MARCÓ HOY ---
    $sql_exists = "SELECT 1 FROM asistencias WHERE alumno_id = ? AND fecha_asistencia = ?";
    $stmt_exists = $conn->prepare($sql_exists);
    
    if ($stmt_exists) {
        $stmt_exists->bind_param("is", $alumno_id, $fecha_asistencia);
        $stmt_exists->execute();
        $stmt_exists->store_result();
        
        if ($stmt_exists->num_rows > 0) {
            $response['status'] = 'warning';
            $response['message'] = 'La asistencia de hoy ya ha sido registrada.';
        } else {
            // --- 4. INSERTAR ASISTENCIA ---
            $sql_insert = "INSERT INTO asistencias (alumno_id, fecha_asistencia) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            
            if ($stmt_insert) {
                $stmt_insert->bind_param("is", $alumno_id, $fecha_asistencia);
                
                if ($stmt_insert->execute()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Asistencia registrada correctamente (' . $fecha_asistencia . ').';
                } else {
                    $response['message'] = 'Error al registrar la asistencia: ' . $conn->error;
                }
                $stmt_insert->close();
            } else {
                $response['message'] = 'Error de preparación de consulta (INSERT).';
            }
        }
        $stmt_exists->close();
    } else {
        $response['message'] = 'Error de preparación de consulta (SELECT EXISTS).';
    }
}

if (isset($conn)) {
    $conn->close();
}

echo json_encode($response);
?>
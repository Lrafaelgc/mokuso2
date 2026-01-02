<?php
header('Content-Type: application/json');
include '../config/db.php';

session_start();
// Medida de seguridad: Solo un maestro puede borrar asistencias
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'maestro') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

$response = ['success' => false, 'message' => 'ID no proporcionado.'];

if (isset($_POST['id'])) {
    $asistencia_id = intval($_POST['id']);
    
    $sql = "DELETE FROM asistencias WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $asistencia_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response = ['success' => true];
        } else {
            $response['message'] = 'No se encontró la asistencia para eliminar.';
        }
    } else {
        $response['message'] = 'Error al ejecutar la consulta: ' . $stmt->error;
    }
    $stmt->close();
}

$conn->close();
echo json_encode($response);
?>
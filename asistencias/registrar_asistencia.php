<?php
header('Content-Type: application/json');
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;
    
    if ($alumno_id > 0) {
        try {
            $fecha_hoy = date('Y-m-d');

            // --- ¡NUEVA LÓGICA DE VERIFICACIÓN! ---
            // 1. Preparamos una consulta para contar las asistencias de hoy para este alumno.
            $check_sql = "SELECT COUNT(*) as count FROM asistencias WHERE alumno_id = ? AND fecha_asistencia = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("is", $alumno_id, $fecha_hoy);
            $stmt_check->execute();
            $result = $stmt_check->get_result()->fetch_assoc();

            // 2. Si el conteo es mayor a 0, significa que ya asistió.
            if ($result['count'] > 0) {
                http_response_code(409); // 409 Conflict: indica un conflicto con el estado actual del recurso.
                echo json_encode(['success' => false, 'error' => 'La asistencia para este alumno ya fue registrada hoy.']);
                exit();
            }
            // --- FIN DE LA VERIFICACIÓN ---

            // 3. Si no hay registros, procedemos a insertar como antes.
            $sql = "INSERT INTO asistencias (alumno_id, fecha_asistencia) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $alumno_id, $fecha_hoy);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Asistencia registrada.']);

        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de alumno no válido.']);
    }
}
?>
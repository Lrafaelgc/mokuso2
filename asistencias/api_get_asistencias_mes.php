<?php
header('Content-Type: application/json');
include '../config/db.php';

// Validar ID, Mes y Año
$alumno_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

if ($alumno_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de alumno no válido.']);
    exit();
}

try {
    // Obtener nombre del alumno
    $stmt_nombre = $conn->prepare("SELECT nombre, apellidos FROM alumnos WHERE id = ?");
    $stmt_nombre->bind_param("i", $alumno_id);
    $stmt_nombre->execute();
    $alumno = $stmt_nombre->get_result()->fetch_assoc();

    if (!$alumno) {
        http_response_code(404);
        echo json_encode(['error' => 'Alumno no encontrado.']);
        exit();
    }
    
    // Obtener los días de asistencia para el mes y año especificados
    $sql = "SELECT DAY(fecha_asistencia) as dia FROM asistencias WHERE alumno_id = ? AND MONTH(fecha_asistencia) = ? AND YEAR(fecha_asistencia) = ?";
    $stmt_asistencias = $conn->prepare($sql);
    $stmt_asistencias->bind_param("iii", $alumno_id, $month, $year);
    $stmt_asistencias->execute();
    $result = $stmt_asistencias->get_result();
    
    $dias_asistidos = [];
    while ($row = $result->fetch_assoc()) {
        $dias_asistidos[] = (int)$row['dia'];
    }

    // Enviar respuesta
    echo json_encode([
        'nombre_alumno' => $alumno['nombre'] . ' ' . $alumno['apellidos'],
        'dias_asistidos' => $dias_asistidos
    ]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos.']);
}
?>
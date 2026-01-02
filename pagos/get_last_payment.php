<?php
// Incluye la conexión a la base de datos
include '../config/db.php';

// Establece el tipo de contenido como JSON
header('Content-Type: application/json');

// Obtiene el ID del alumno desde la solicitud GET
$alumno_id = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;

if ($alumno_id === 0) {
    echo json_encode(['error' => 'ID de alumno no proporcionado.']);
    exit;
}

// Prepara la consulta para obtener los detalles del alumno
$sql = "SELECT 
            a.estado_membresia, 
            a.fecha_vencimiento_membresia,
            MAX(p.fecha_pago) as ultimo_pago 
        FROM alumnos a
        LEFT JOIN pagos p ON a.id = p.alumno_id
        WHERE a.id = ?
        GROUP BY a.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$result = $stmt->get_result();

$data = $result->fetch_assoc();

if ($data) {
    // Si se encuentra al alumno, devolvemos sus datos en formato JSON
    echo json_encode($data);
} else {
    // Si el alumno no existe, devolvemos un mensaje de error
    echo json_encode(['error' => 'Alumno no encontrado.']);
}

$stmt->close();
$conn->close();
?>
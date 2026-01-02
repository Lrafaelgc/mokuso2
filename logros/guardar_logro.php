<?php
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $alumno_id = $_POST['alumno_id'];
    $logro_desc = trim($_POST['logro']);
    $fecha_logro = $_POST['fecha_logro'];

    if (empty($alumno_id) || empty($logro_desc) || empty($fecha_logro)) {
        die("Error: Todos los campos son requeridos.");
    }

    $conn->begin_transaction();
    try {
        // 1. Insertar el nuevo logro en la tabla 'logros'
        $sql_insert = "INSERT INTO logros (alumno_id, logro, fecha_logro) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("iss", $alumno_id, $logro_desc, $fecha_logro);
        $stmt_insert->execute();

        // 2. Si el logro contiene la palabra "Cinta", actualizamos el nivel del alumno
        if (strpos(strtolower($logro_desc), 'cinta') !== false) {
            $sql_update = "UPDATE alumnos SET nivel = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("si", $logro_desc, $alumno_id);
            $stmt_update->execute();
        }

        // Si todo fue exitoso, confirmamos los cambios
        $conn->commit();
        
        // Redirigimos a la lista de alumnos con un mensaje de éxito
        header("Location: /MOKUSO/alumnos/index.php?status=logro_exitoso");
        exit();

    } catch (mysqli_sql_exception $e) {
        // Si algo falla, revertimos todos los cambios
        $conn->rollback();
        die("Error al guardar el logro: " . $e->getMessage());
    }
}
?>
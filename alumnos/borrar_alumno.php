<?php
include '../config/db.php';

// Validar que se ha pasado un ID válido
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Obtener el nombre de la foto para eliminar el archivo
    $sql_foto = "SELECT foto_perfil FROM alumnos WHERE id = ?";
    $stmt_foto = $conn->prepare($sql_foto);
    $stmt_foto->bind_param("i", $id);
    $stmt_foto->execute();
    $result_foto = $stmt_foto->get_result();
    $alumno = $result_foto->fetch_assoc();
    $stmt_foto->close();

    if ($alumno && $alumno['foto_perfil'] != 'default.png') {
        $file_path = '../assets/img/uploads/' . $alumno['foto_perfil'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Eliminar el registro de la base de datos
    $sql = "DELETE FROM alumnos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header("Location: index.php?status=success_delete");
    exit();
} else {
    header("Location: index.php?error=id_invalido");
    exit();
}
?>
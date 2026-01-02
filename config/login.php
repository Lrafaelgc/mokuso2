<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // NOTA: La columna alumno_id ya no la leeremos si usamos la nueva estructura,
    // pero la mantendremos si es la forma actual de vincular alumno.
    $sql = "SELECT id, username, password, role, alumno_id FROM users WHERE username = ?"; 
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // **¡IMPORTANTE!** Sigue usando tu lógica de verificación de contraseña
        if ($password == $user['password']) { // DEBES CAMBIAR ESTO A password_verify($password, $user['password']) en producción
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Si el usuario es ALUMNO, su alumno_id se guarda en sesión (Lógica actual)
            if ($user['role'] == 'alumno') {
                $_SESSION['alumno_id'] = $user['alumno_id'];
            }
            // Si el usuario es PADRE, NO se guarda alumno_id, se guarda el user_id para buscar sus hijos.
            
            // Redirección basada en el rol
            if ($user['role'] == 'maestro') {
                header("Location: /MOKUSO/alumnos/index.php");
            } else if ($user['role'] == 'alumno') {
                header("Location: /MOKUSO/alumnos/perfil.php");
            } else if ($user['role'] == 'padre') { // <-- NUEVA LÓGICA DE REDIRECCIÓN
                header("Location: /MOKUSO/alumnos/lista_hijos.php");
            }
            exit();
        } else {
            header("Location: /MOKUSO/index.php?error=1");
            exit();
        }
    } else {
        header("Location: /MOKUSO/index.php?error=1");
        exit();
    }
    $stmt->close();
    $conn->close();
} else {
    header("Location: /MOKUSO/index.php");
    exit();
}
<?php
session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se usa una cookie de sesión, también es necesario destruirla
// Nota: Esto borrará la cookie de sesión, no la de 'recordar' si la usaras.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión
session_destroy();

// Redirigir al usuario a la página de inicio
header("Location: /MOKUSO/index.php");
exit();
?>
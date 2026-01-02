<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "MOKUSO";

// Reportar errores de MySQLi para una mejor depuración
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $password, $database);
    $conn->set_charset("utf8mb4");

    // --- AUTOMATOR DE MEMBRESÍAS (LÓGICA DE CICLO MENSUAL) ---
    // Se ejecuta automáticamente cada vez que se incluye este archivo.
    
    $hoy_auto = date('Y-m-d');
    $dia_auto = intval(date('j')); // Día del mes (1-31)

    if ($dia_auto <= 5) {
        // DÍAS 1-5: PERIODO DE GRACIA
        // Si venció y estaba activa, pasa a 'pendiente' (aviso de cobro)
        $conn->query("UPDATE alumnos 
                      SET estado_membresia = 'pendiente' 
                      WHERE fecha_vencimiento_membresia < '$hoy_auto' 
                      AND estado_membresia = 'activa'");
    } else {
        // DÍA 6 EN ADELANTE: CORTE DE SERVICIO
        // Si venció y no ha pagado (sigue activa vieja o pendiente), pasa a 'inactiva'
        $conn->query("UPDATE alumnos 
                      SET estado_membresia = 'inactiva', fecha_ultima_inactividad = '$hoy_auto' 
                      WHERE fecha_vencimiento_membresia < '$hoy_auto' 
                      AND estado_membresia IN ('activa', 'pendiente')");
    }

} catch (mysqli_sql_exception $e) {
    // Manejo de error de conexión en formato JSON
    http_response_code(500);
    die(json_encode(["error" => true, "message" => "Error de conexión a la base de datos: " . $e->getMessage()]));
}
?>
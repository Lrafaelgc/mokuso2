<?php
header('Content-Type: application/json');
include '../config/db.php';

// Establece el idioma a español para los nombres de los meses
setlocale(LC_TIME, 'es_ES.UTF-8', 'Spanish');

$ingresos_mensuales = [];

// Usaremos un bucle para recorrer los últimos 6 meses (incluyendo el actual)
for ($i = 5; $i >= 0; $i--) {
    // Calculamos el primer y último día del mes correspondiente
    $fecha_referencia = strtotime("-$i months");
    $inicio_mes = date('Y-m-01', $fecha_referencia);
    $fin_mes = date('Y-m-t', $fecha_referencia);
    
    // Obtenemos el nombre del mes en español
    // La función strftime() es más fiable con setlocale() que date()
    $nombre_mes = strftime('%B', $fecha_referencia);

    // MODIFICADO: La consulta ahora une 'pagos' con 'tipos_pago' para sumar el monto correcto.
    // MEJORA: Se usa una consulta preparada con BETWEEN para mayor seguridad y eficiencia.
    $sql = "SELECT SUM(tp.monto) as total 
            FROM pagos p
            JOIN tipos_pago tp ON p.tipo_pago_id = tp.id
            WHERE p.fecha_pago BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $inicio_mes, $fin_mes);
    $stmt->execute();
    
    // Usamos get_result() que es compatible con tu servidor si tienes mysqlnd,
    // o puedes adaptarlo a tu función get_result_manual si es necesario.
    // Como es una sola fila, podemos usar bind_result directamente para compatibilidad.
    $stmt->bind_result($total_mes);
    $stmt->fetch();
    $stmt->close();

    // CORREGIDO: Se reemplaza '??' por una comprobación simple para compatibilidad.
    $total = $total_mes ? $total_mes : 0;

    $ingresos_mensuales[] = [
        'mes' => ucfirst($nombre_mes), // Pone la primera letra en mayúscula
        'total' => (float)$total
    ];
}

echo json_encode($ingresos_mensuales);
?>
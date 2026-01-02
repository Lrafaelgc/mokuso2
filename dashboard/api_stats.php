<?php
header('Content-Type: application/json');
include '../config/db.php';

// Validar y sanitizar las fechas de entrada
$fecha_inicio = isset($_GET['inicio']) ? $_GET['inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fin']) ? $_GET['fin'] : date('Y-m-t');

if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $fecha_inicio) || 
    !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $fecha_fin)) {
    echo json_encode(['error' => true, 'message' => 'Formato de fecha inválido.']);
    exit;
}

$data = [
    'kpis' => [],
    'widgets' => [],
    'charts' => []
];

try {
    // --- KPIs (Key Performance Indicators) ---
    // Ingresos en el periodo
    $stmt = $conn->prepare("SELECT SUM(monto) as total FROM movimientos WHERE tipo IN ('ingreso_mensualidad', 'ingreso_otro') AND fecha BETWEEN ? AND ?");
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $data['kpis']['ingresos_periodo'] = isset($result['total']) ? $result['total'] : 0;
    
    // Gastos en el periodo
    $stmt = $conn->prepare("SELECT SUM(monto) as total FROM movimientos WHERE tipo = 'gasto' AND fecha BETWEEN ? AND ?");
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $data['kpis']['gastos_periodo'] = isset($result['total']) ? $result['total'] : 0;
    
    // Balance Neto
    $data['kpis']['balance_periodo'] = $data['kpis']['ingresos_periodo'] - $data['kpis']['gastos_periodo'];

    // Alumnos activos
    $stmt = $conn->prepare("SELECT COUNT(id) as total FROM alumnos WHERE estado_membresia = 'activa'");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $data['kpis']['alumnos_activos'] = isset($result['total']) ? $result['total'] : 0;

    // ====================================================================================
    // LÍNEA CORREGIDA: Se cambió 'fecha_ingreso' por 'DATE(fecha_registro)'
    // ====================================================================================
    $stmt = $conn->prepare("SELECT COUNT(id) as total FROM alumnos WHERE DATE(fecha_registro) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $data['kpis']['alumnos_nuevos'] = isset($result['total']) ? $result['total'] : 0;

    // Asistencias en el periodo
    $stmt = $conn->prepare("SELECT COUNT(id) as total FROM asistencias WHERE fecha_asistencia BETWEEN ? AND ?");
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $data['kpis']['asistencias_periodo'] = isset($result['total']) ? $result['total'] : 0;

    // --- Widgets (Listas) ---
    // Membresías por vencer
    $fecha_limite_vencimiento = date('Y-m-d', strtotime('+7 days'));
    $stmt = $conn->prepare("SELECT id, nombre, apellidos, fecha_vencimiento_membresia FROM alumnos WHERE estado_membresia = 'activa' AND fecha_vencimiento_membresia <= ? ORDER BY fecha_vencimiento_membresia ASC");
    $stmt->bind_param("s", $fecha_limite_vencimiento);
    $stmt->execute();
    $result_vencimientos = $stmt->get_result();
    $vencimientos_array = [];
    while($row = $result_vencimientos->fetch_assoc()) {
        $vencimientos_array[] = $row;
    }
    $data['widgets']['vencimientos_proximos'] = $vencimientos_array;

    // Alumnos en riesgo (más de 15 días sin asistir)
    $fecha_limite_riesgo = date('Y-m-d', strtotime('-15 days'));
    $stmt = $conn->prepare("SELECT a.id, a.nombre, a.apellidos, MAX(ast.fecha_asistencia) as ultima_asistencia FROM alumnos a LEFT JOIN asistencias ast ON a.id = ast.alumno_id WHERE a.estado_membresia = 'activa' GROUP BY a.id HAVING ultima_asistencia < ? OR ultima_asistencia IS NULL ORDER BY ultima_asistencia ASC");
    $stmt->bind_param("s", $fecha_limite_riesgo);
    $stmt->execute();
    $result_riesgo = $stmt->get_result();
    $riesgo_array = [];
    while($row = $result_riesgo->fetch_assoc()) {
        $riesgo_array[] = $row;
    }
    $data['widgets']['alumnos_en_riesgo'] = $riesgo_array;

    // --- Gráficas (Datos para Charts.js) ---
    // Gráfica de Líneas: Ingresos vs Gastos por día
    $stmt = $conn->prepare("
        SELECT 
            fecha,
            SUM(CASE WHEN tipo IN ('ingreso_mensualidad', 'ingreso_otro') THEN monto ELSE 0 END) as ingresos,
            SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END) as gastos
        FROM movimientos
        WHERE fecha BETWEEN ? AND ?
        GROUP BY fecha
        ORDER BY fecha ASC
    ");
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result_tendencia = $stmt->get_result();
    $tendencia_array = [];
    while($row = $result_tendencia->fetch_assoc()) {
        $tendencia_array[] = $row;
    }
    $data['charts']['tendencia_financiera'] = $tendencia_array;

    // Gráfica de Dona: Composición de Ingresos
    $stmt = $conn->prepare("
        SELECT 
            CASE 
                WHEN tipo = 'ingreso_mensualidad' THEN 'Mensualidades'
                WHEN tipo = 'ingreso_otro' THEN 'Otros Ingresos'
            END as concepto,
            SUM(monto) as total
        FROM movimientos
        WHERE tipo IN ('ingreso_mensualidad', 'ingreso_otro') AND fecha BETWEEN ? AND ?
        GROUP BY concepto
    ");
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result_composicion = $stmt->get_result();
    $composicion_array = [];
    while($row = $result_composicion->fetch_assoc()){
        $composicion_array[] = $row;
    }
    $data['charts']['composicion_ingresos'] = $composicion_array;

    echo json_encode($data);

} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}

$conn->close();
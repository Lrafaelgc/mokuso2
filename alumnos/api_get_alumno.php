<?php
// Establece la cabecera para la respuesta JSON.
header('Content-Type: application/json');
include '../config/db.php';

// Función de compatibilidad para servidores sin el driver mysqlnd.
function get_result_manual($stmt) {
    $result = array();
    $stmt->store_result();
    if ($stmt->num_rows === 0) return $result;
    $meta = $stmt->result_metadata();
    $params = array();
    while ($field = $meta->fetch_field()) {
        $params[] = &$result[$field->name];
    }
    call_user_func_array(array($stmt, 'bind_result'), $params);
    $all_results = array();
    while ($stmt->fetch()) {
        $row = array();
        foreach ($result as $key => $val) {
            $row[$key] = $val;
        }
        $all_results[] = $row;
    }
    return $all_results;
}

// Función para enviar errores en formato JSON.
function send_json_error($http_code, $message) {
    http_response_code($http_code);
    echo json_encode(['error' => true, 'message' => $message]);
    exit();
}

// Función para calcular los días hábiles del mes.
function get_dias_habiles_mes($grupo_nombre) {
    if ($grupo_nombre === null) return 0;
    
    $dias_habiles = 0;
    $fecha_inicio_mes = new DateTime('first day of this month');
    $fecha_actual = new DateTime();
    $fecha_fin_periodo = clone $fecha_actual;
    $fecha_fin_periodo->add(new DateInterval('P1D'));
    
    $periodo = new DatePeriod($fecha_inicio_mes, new DateInterval('P1D'), $fecha_fin_periodo);

    foreach ($periodo as $dia) {
        $dia_semana = $dia->format('N');
        // Adecúa esta lógica a los horarios reales de tus grupos
        if (($grupo_nombre === 'C7' || $grupo_nombre === 'CM8' || $grupo_nombre === 'A7' || $grupo_nombre === 'B9') && $dia_semana <= 5) {
            $dias_habiles++;
        } elseif ($grupo_nombre === 'C6' && ($dia_semana == 1 || $dia_semana == 3 || $dia_semana == 5) ) {
            $dias_habiles++;
        }
    }
    return $dias_habiles;
}

// Función para calcular tiempo activo.
function calcularTiempoActivo($fecha_registro, $estado_membresia, $fecha_ultima_inactividad) {
    if (!$fecha_registro) return 'N/A';
    try {
        $hoy = new DateTime();
        $registro = new DateTime($fecha_registro);
        $tiempo_inactivo_segundos = 0;
        if ($estado_membresia === 'inactiva' && !empty($fecha_ultima_inactividad)) {
            $ultima_inactividad = new DateTime($fecha_ultima_inactividad);
            if ($ultima_inactividad > $registro) {
                $diff_inactividad = $hoy->getTimestamp() - $ultima_inactividad->getTimestamp();
                $tiempo_inactivo_segundos = max(0, $diff_inactividad);
            }
        }
        $tiempo_total_segundos = $hoy->getTimestamp() - $registro->getTimestamp();
        $tiempo_activo_segundos = max(0, $tiempo_total_segundos - $tiempo_inactivo_segundos);
        
        $intervalo = date_diff(new DateTime("@".$registro->getTimestamp()), new DateTime("@".($registro->getTimestamp() + $tiempo_activo_segundos)));

        $partes = [];
        if ($intervalo->y > 0) $partes[] = $intervalo->y . " año" . ($intervalo->y > 1 ? "s" : "");
        if ($intervalo->m > 0) $partes[] = $intervalo->m . " mes" . ($intervalo->m > 1 ? "es" : "");
        if ($intervalo->d > 0 && empty($partes)) $partes[] = $intervalo->d . " día" . ($intervalo->d > 1 ? "s" : "");
        
        return empty($partes) ? 'Menos de 1 día' : implode(", ", array_slice($partes, 0, 2));

    } catch (Exception $e) {
        return 'Fecha inválida';
    }
}

// 1. Validar el ID de entrada
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    send_json_error(400, "El ID de alumno proporcionado no es válido.");
}

try {
    // 2. Consulta principal que une todas las tablas
    $sql = "SELECT 
                a.*,
                TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, CURDATE()) AS edad,
                n.nombre AS nivel_nombre,
                d.nombre AS disciplina_nombre,
                g.nombre AS grupo_nombre
            FROM alumnos AS a
            LEFT JOIN niveles AS n ON a.nivel_id = n.id
            LEFT JOIN disciplinas AS d ON a.disciplina_id = d.id
            LEFT JOIN grupos AS g ON a.grupo_id = g.id
            WHERE a.id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $alumnos_data = get_result_manual($stmt);
    
    $alumno = isset($alumnos_data[0]) ? $alumnos_data[0] : null;
    $stmt->close();

    if (!$alumno) {
        send_json_error(404, "No se encontró ningún alumno con el ID proporcionado.");
    }
    
    // 3. Obtener asistencias y logros
    $stmt_ultima = $conn->prepare("SELECT fecha_asistencia FROM asistencias WHERE alumno_id = ? ORDER BY fecha_asistencia DESC LIMIT 1");
    $stmt_ultima->bind_param("i", $id);
    $stmt_ultima->execute();
    $result_ultima = get_result_manual($stmt_ultima);
    $ultima_asistencia = isset($result_ultima[0]['fecha_asistencia']) ? $result_ultima[0]['fecha_asistencia'] : null;
    $stmt_ultima->close();

    $stmt_asistencias = $conn->prepare("SELECT COUNT(*) AS total FROM asistencias WHERE alumno_id = ? AND MONTH(fecha_asistencia) = MONTH(CURDATE()) AND YEAR(fecha_asistencia) = YEAR(CURDATE())");
    $stmt_asistencias->bind_param("i", $id);
    $stmt_asistencias->execute();
    $result_asistencias = get_result_manual($stmt_asistencias);
    $asistencias_mes = isset($result_asistencias[0]['total']) ? $result_asistencias[0]['total'] : 0;
    $stmt_asistencias->close();

    $stmt_logros = $conn->prepare("SELECT logro, fecha_logro FROM logros WHERE alumno_id = ? ORDER BY fecha_logro DESC");
    $stmt_logros->bind_param("i", $id);
    $stmt_logros->execute();
    $logros = get_result_manual($stmt_logros);
    $stmt_logros->close();

    // 4. Procesar datos y realizar cálculos
    $grupo_nombre = isset($alumno['grupo_nombre']) ? $alumno['grupo_nombre'] : null;
    $fecha_registro = isset($alumno['fecha_registro']) ? $alumno['fecha_registro'] : null;
    $estado_membresia = isset($alumno['estado_membresia']) ? $alumno['estado_membresia'] : 'pendiente';
    $fecha_ultima_inactividad = isset($alumno['fecha_ultima_inactividad']) ? $alumno['fecha_ultima_inactividad'] : null;
    
    $dias_habiles = get_dias_habiles_mes($grupo_nombre);
    $porcentaje_asistencia = ($dias_habiles > 0) ? round(($asistencias_mes / $dias_habiles) * 100, 2) : 0;
    
    $tiempo_miembro_str = calcularTiempoActivo($fecha_registro, $estado_membresia, $fecha_ultima_inactividad);
    
    $tiempo_inactivo_str = null;
    if ($estado_membresia === 'inactiva' && !empty($fecha_ultima_inactividad)) {
        $tiempo_inactivo_str = date('d/m/Y', strtotime($fecha_ultima_inactividad));
    }

    // 5. Empaquetar y enviar la respuesta JSON
    $respuesta = [
        'detalles' => $alumno,
        'asistencias' => [
            'total' => (int)$asistencias_mes,
            'porcentaje' => $porcentaje_asistencia,
            'ultima_asistencia' => $ultima_asistencia,
            'grupo' => $grupo_nombre
        ],
        'logros' => $logros,
        'tiempo_miembro_str' => $tiempo_miembro_str,
        'tiempo_inactivo_str' => $tiempo_inactivo_str
    ];

    echo json_encode($respuesta);

} catch (Exception $e) {
    send_json_error(500, "Error de procesamiento: " . $e->getMessage());
}
?>
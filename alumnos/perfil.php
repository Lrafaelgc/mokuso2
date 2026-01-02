<?php
session_start();
include '../config/db.php';

// 1. VERIFICACIÓN DE SESIÓN Y PERMISOS
if (!isset($_SESSION['user_id'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

$user_id_sesion = $_SESSION['user_id'];
$alumno_id = null;
$alumno_id_solicitado = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;

// 2. LÓGICA DE ACCESO
if ($alumno_id_solicitado > 0) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'maestro') {
        $alumno_id = $alumno_id_solicitado;
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'padre') {
        $stmt = $conn->prepare("SELECT id FROM padres WHERE user_id = ?");
        $stmt->bind_param("i", $user_id_sesion);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $padre_id = $row['id'];
            $stmt_check = $conn->prepare("SELECT 1 FROM padres_alumnos WHERE padre_id = ? AND alumno_id = ?");
            $stmt_check->bind_param("ii", $padre_id, $alumno_id_solicitado);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $alumno_id = $alumno_id_solicitado;
            }
        }
        if (!$alumno_id) { header("Location: lista_hijos.php"); exit(); }
    }
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'alumno') {
    $alumno_id = $_SESSION['alumno_id'];
}

if (!$alumno_id) { header("Location: /MOKUSO/index.php"); exit(); }

// 3. OBTENER DATOS
$detalles = null;
$asistencias = [];
$logros = [];
$show_check_in_button = false;
$alerta_vencimiento = false;
$dias_restantes = 0;
$tiempo_miembro = 'N/A';
$fecha_vence_formateada = 'No definida';
$porcentaje_tiempo = 0; // Para la barra de progreso de membresía

if ($alumno_id) {
    $sql = "SELECT a.*, n.nombre AS nivel_nombre, d.nombre AS disciplina_nombre 
            FROM alumnos a 
            LEFT JOIN niveles n ON a.nivel_id = n.id 
            LEFT JOIN disciplinas d ON a.disciplina_id = d.id 
            WHERE a.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $alumno_id);
    $stmt->execute();
    $detalles = $stmt->get_result()->fetch_assoc();

    if ($detalles) {
        // Botón Check-in
        if (in_array(strtolower($detalles['estado_membresia']), ['activa', 'exento', 'pendiente'])) {
            if ((isset($_SESSION['role']) && $_SESSION['role'] === 'alumno') || $_SESSION['role'] === 'padre') {
                $show_check_in_button = true;
            }
        }

        // CÁLCULO DE VENCIMIENTO Y BARRA DE PROGRESO
        if (!empty($detalles['fecha_vencimiento_membresia']) && $detalles['fecha_vencimiento_membresia'] != '0000-00-00') {
            $vence = new DateTime($detalles['fecha_vencimiento_membresia']);
            $hoy = new DateTime();
            $hoy->setTime(0,0,0); $vence->setTime(0,0,0);
            
            // Formato bonito para mostrar
            $meses_es = ['Jan'=>'Ene','Feb'=>'Feb','Mar'=>'Mar','Apr'=>'Abr','May'=>'May','Jun'=>'Jun','Jul'=>'Jul','Aug'=>'Ago','Sep'=>'Sep','Oct'=>'Oct','Nov'=>'Nov','Dec'=>'Dic'];
            $fecha_vence_formateada = $vence->format('d') . ' de ' . $meses_es[$vence->format('M')] . ', ' . $vence->format('Y');

            if ($vence >= $hoy) {
                $diff = $hoy->diff($vence);
                $dias_restantes = $diff->days;
                
                // Lógica para la barra de progreso (Asumimos ciclo de 30 días para visualización)
                // Si faltan 30 días, barra llena (verde). Si falta 0, barra vacía.
                $porcentaje_tiempo = min(100, ($dias_restantes / 30) * 100);
                
                if ($dias_restantes <= 5) { // Alerta amarilla si faltan 5 días o menos (Coincide con tu regla de pago)
                    $alerta_vencimiento = true;
                }
            } else {
                $dias_restantes = -1; // Vencido
                $porcentaje_tiempo = 0;
            }
        }

        // Cálculo Antigüedad
        if (!empty($detalles['fecha_registro']) && $detalles['fecha_registro'] != '0000-00-00 00:00:00') {
            $fecha_reg = new DateTime($detalles['fecha_registro']);
            $hoy = new DateTime();
            $diff = $fecha_reg->diff($hoy);
            $partes = [];
            if ($diff->y > 0) $partes[] = $diff->y . " año" . ($diff->y > 1 ? "s" : "");
            if ($diff->m > 0) $partes[] = $diff->m . " mes" . ($diff->m > 1 ? "es" : "");
            $tiempo_miembro = empty($partes) ? "Nuevo Ingreso" : implode(" y ", array_slice($partes, 0, 2));
        }

        // Asistencias
        $res_asis = $conn->query("SELECT fecha_asistencia FROM asistencias WHERE alumno_id = $alumno_id ORDER BY fecha_asistencia ASC");
        while ($row = $res_asis->fetch_assoc()) $asistencias[] = $row['fecha_asistencia'];

        // Logros
        $res_logros = $conn->query("SELECT logro, fecha_logro FROM logros WHERE alumno_id = $alumno_id ORDER BY fecha_logro DESC");
        while ($row = $res_logros->fetch_assoc()) $logros[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - <?php echo htmlspecialchars($detalles['nombre']); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #ff6600; --primary-glow: rgba(255, 102, 0, 0.4);
            --bg-main: #121214; --surface: #202024; --border: #323238;
            --text-white: #e1e1e6; --text-muted: #a8a8b3;
            --success: #04d361; --danger: #ff3e3e; --warning: #fad733;
            --radius: 16px;
        }

        body {
            background-color: var(--bg-main); color: var(--text-white); font-family: 'Poppins', sans-serif;
            min-height: 100vh; overflow-x: hidden; padding-bottom: 80px; /* Espacio para footer móvil */
        }

        /* FONDO */
        .background-blur { position: fixed; inset: 0; z-index: -1; pointer-events: none; }
        .blur-circle { position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.3; animation: float 10s infinite alternate; }
        .b1 { top: -10%; left: -10%; width: 50vw; height: 50vw; background: var(--primary); }
        .b2 { bottom: -10%; right: -10%; width: 40vw; height: 40vw; background: #00bfff; animation-delay: -5s; }
        @keyframes float { from {transform: translate(0,0);} to {transform: translate(30px, 50px);} }

        .main-container { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* HEADER TIPO ID CARD */
        .profile-header-card {
            background: rgba(32, 32, 36, 0.85); border: 1px solid var(--border); border-radius: var(--radius);
            backdrop-filter: blur(20px); padding: 2rem; margin-bottom: 1.5rem; text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); position: relative; overflow: hidden;
        }
        .profile-header-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px;
            background: linear-gradient(90deg, var(--primary), #e07b00);
        }

        .avatar-container {
            width: 110px; height: 110px; margin: 0 auto 1rem; padding: 4px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), transparent);
        }
        .avatar-img {
            width: 100%; height: 100%; border-radius: 50%; object-fit: cover;
            border: 4px solid var(--surface); background: var(--surface);
        }

        .student-name { font-family: 'Orbitron', sans-serif; font-size: 1.8rem; margin: 0; color: var(--text-white); }
        .student-rank { color: var(--primary); font-weight: 600; font-size: 1rem; margin-bottom: 0.5rem; }
        
        /* NUEVO: TARJETA DE MEMBRESÍA VISUAL */
        .membership-tracker {
            background: rgba(0,0,0,0.2); border-radius: 12px; padding: 1.2rem; margin-top: 1.5rem;
            border: 1px solid var(--border); text-align: left; position: relative; overflow: hidden;
        }
        .membership-tracker h4 { font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; }
        .expiry-date { font-size: 1.2rem; font-weight: 700; color: var(--text-white); margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center; }
        
        .days-left-badge {
            font-size: 0.8rem; padding: 4px 10px; border-radius: 20px; font-weight: 700;
        }
        .badge-ok { background: rgba(4, 211, 97, 0.15); color: var(--success); border: 1px solid var(--success); }
        .badge-warn { background: rgba(250, 215, 51, 0.15); color: var(--warning); border: 1px solid var(--warning); }
        .badge-danger { background: rgba(255, 62, 62, 0.15); color: var(--danger); border: 1px solid var(--danger); }

        /* BARRA DE PROGRESO PERSONALIZADA */
        .progress-track {
            height: 8px; width: 100%; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden; margin-top: 5px;
        }
        .progress-fill {
            height: 100%; border-radius: 4px; transition: width 1s ease-in-out;
        }

        /* BOTÓN ASISTENCIA HERO */
        .checkin-hero-btn {
            display: block; width: 100%; max-width: 350px; margin: 1.5rem auto 0;
            padding: 1rem; border: none; border-radius: 14px;
            background: linear-gradient(135deg, var(--primary), #ff8c00);
            color: #000; font-weight: 700; font-size: 1.1rem; text-transform: uppercase;
            box-shadow: 0 0 20px rgba(255, 102, 0, 0.3); transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer; animation: pulse-glow 2s infinite;
        }
        .checkin-hero-btn:hover { transform: scale(1.03); box-shadow: 0 0 30px rgba(255, 102, 0, 0.5); }
        .checkin-hero-btn:disabled { background: #444; color: #888; animation: none; cursor: not-allowed; transform: none; box-shadow: none; }
        @keyframes pulse-glow { 0% { box-shadow: 0 0 0 0 rgba(255, 102, 0, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(255, 102, 0, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 102, 0, 0); } }

        /* STATS GRID */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap: 10px; margin-top: 2rem; }
        .stat-tile { background: var(--surface); border: 1px solid var(--border); padding: 1rem 0.5rem; border-radius: 12px; text-align: center; transition: 0.3s; }
        .stat-tile:hover { border-color: var(--text-muted); transform: translateY(-3px); }
        .stat-num { font-size: 1.3rem; font-weight: 700; color: var(--text-white); display: block; line-height: 1.2; }
        .stat-desc { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

        /* SECTION CARD */
        .section-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 1.5rem; margin-top: 1.5rem;
        }
        .section-title { font-family: 'Orbitron', sans-serif; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; font-size: 1.2rem; }

        /* CALENDARIO */
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center; margin-top: 10px; }
        .day-header { font-size: 0.8rem; color: var(--text-muted); padding-bottom: 5px; }
        .day-cell { 
            aspect-ratio: 1; display: flex; align-items: center; justify-content: center; 
            border-radius: 8px; font-size: 0.9rem; color: var(--text-muted); 
        }
        .day-cell.present { background: rgba(4, 211, 97, 0.15); color: var(--success); font-weight: 700; border: 1px solid rgba(4, 211, 97, 0.3); }
        
        /* UTILS */
        .btn-icon { background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 8px 15px; border-radius: 8px; transition: 0.3s; }
        .btn-icon:hover { color: var(--text-white); border-color: var(--text-white); }
        
        /* MODAL PAGO */
        .modal-content { background: #1a1a1d; border: 1px solid var(--border); color: var(--text-white); }
        .modal-header, .modal-footer { border-color: var(--border); }
        .pay-method { background: rgba(255,255,255,0.05); padding: 15px; border-radius: 10px; margin-bottom: 10px; cursor: pointer; border: 1px solid transparent; }
        .pay-method:hover { border-color: var(--primary); background: rgba(255, 102, 0, 0.05); }
    </style>
</head>
<body>

<div class="background-blur">
    <div class="blur-circle b1"></div>
    <div class="blur-circle b2"></div>
</div>

<div class="main-container">
    <div class="text-center mb-4">
        <img src="/MOKUSO/assets/img/logo2.png" alt="Logo" style="height:60px; opacity:0.9;">
    </div>

    <?php if ($detalles): ?>
    
    <!-- 1. HÉROE DE PERFIL -->
    <div id="printable-area" class="profile-header-card">
        <div class="avatar-container">
            <img src="/MOKUSO/assets/img/uploads/<?php echo htmlspecialchars($detalles['foto_perfil'] ?: 'default.png'); ?>" class="avatar-img">
        </div>
        <h1 class="student-name"><?php echo htmlspecialchars($detalles['nombre'].' '.$detalles['apellidos']); ?></h1>
        <div class="student-rank"><?php echo htmlspecialchars($detalles['nivel_nombre'] ?: 'Sin Grado'); ?></div>
        
        <div style="color: var(--text-muted); font-size: 0.9rem;">
            <?php echo htmlspecialchars($detalles['disciplina_nombre'] ?: 'Artes Marciales'); ?>
        </div>

        <!-- 2. TARJETA DE MEMBRESÍA INTELIGENTE -->
        <div class="membership-tracker">
            <h4><i class="ph-bold ph-credit-card"></i> Estado de Membresía</h4>
            
            <div class="expiry-date">
                <span><?php echo $fecha_vence_formateada; ?></span>
                <?php if($dias_restantes < 0): ?>
                    <span class="days-left-badge badge-danger">VENCIDA</span>
                <?php elseif($dias_restantes <= 5): ?>
                    <span class="days-left-badge badge-warn"><?php echo $dias_restantes; ?> DÍAS</span>
                <?php else: ?>
                    <span class="days-left-badge badge-ok">ACTIVA</span>
                <?php endif; ?>
            </div>

            <?php if($dias_restantes >= 0): ?>
                <!-- Barra Verde/Amarilla según días -->
                <div class="progress-track">
                    <div class="progress-fill" style="width: <?php echo $porcentaje_tiempo; ?>%; background: <?php echo ($dias_restantes <= 5) ? 'var(--warning)' : 'var(--success)'; ?>;"></div>
                </div>
                <small class="mt-2 d-block text-muted">
                    <?php echo ($dias_restantes <= 5) ? "Tu fecha límite se acerca. Evita interrupciones." : "Estás al corriente con tus pagos."; ?>
                </small>
            <?php else: ?>
                <!-- Barra Roja Vencida -->
                <div class="progress-track"><div class="progress-fill" style="width: 100%; background: var(--danger);"></div></div>
                <small class="mt-2 d-block text-danger fw-bold">Favor de realizar el pago para reactivar el acceso.</small>
            <?php endif; ?>
        </div>

        <!-- 3. BOTÓN DE ASISTENCIA (Acción Principal) -->
        <?php if ($show_check_in_button): ?>
            <button id="checkInButton" class="checkin-hero-btn">
                <i class="ph-bold ph-hand-tap"></i> Marcar Asistencia
            </button>
            <div id="checkInMessage" class="mt-2 fw-bold"></div>
        <?php endif; ?>
    </div>

    <!-- 4. ESTADÍSTICAS RÁPIDAS -->
    <div class="stats-grid">
        <div class="stat-tile">
            <span class="stat-num"><?php echo count($asistencias); ?></span>
            <span class="stat-desc">Clases</span>
        </div>
        <div class="stat-tile">
            <span class="stat-num"><?php echo count($logros); ?></span>
            <span class="stat-desc">Logros</span>
        </div>
        <div class="stat-tile">
            <span class="stat-num"><?php echo $detalles['peso'] ? $detalles['peso'].'kg' : '--'; ?></span>
            <span class="stat-desc">Peso</span>
        </div>
        <div class="stat-tile">
            <span class="stat-num" style="font-size:1rem; line-height:1.4;"><?php echo $tiempo_miembro; ?></span>
            <span class="stat-desc">Antigüedad</span>
        </div>
    </div>

    <div class="d-flex justify-content-center gap-2 mt-4 mb-4">
        <button class="btn-icon" onclick="downloadPDF()"><i class="ph-bold ph-download-simple"></i> PDF</button>
        <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="ph-bold ph-bank"></i> Datos Pago</button>
        <a href="/MOKUSO/config/logout.php" class="btn-icon" style="color:var(--danger); border-color:var(--danger);"><i class="ph-bold ph-sign-out"></i> Salir</a>
    </div>

    <!-- 5. CALENDARIO -->
    <div class="section-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="section-title m-0"><i class="ph-duotone ph-calendar-check"></i> Asistencia</h3>
            <div id="currentMonthLabel" class="text-muted" style="font-size:0.9rem;"></div>
        </div>
        <div id="calendar-container"></div>
        <div class="d-flex justify-content-between mt-2">
            <button class="btn-icon py-1 px-3" onclick="changeMonth(-1)"><i class="ph-bold ph-caret-left"></i></button>
            <button class="btn-icon py-1 px-3" onclick="changeMonth(1)"><i class="ph-bold ph-caret-right"></i></button>
        </div>
    </div>

    <!-- 6. LOGROS -->
    <?php if(!empty($logros)): ?>
    <div class="section-card">
        <h3 class="section-title"><i class="ph-duotone ph-trophy"></i> Trayectoria</h3>
        <div style="border-left: 2px solid var(--border); padding-left: 20px; margin-left: 10px;">
            <?php foreach($logros as $l): ?>
            <div class="mb-3 position-relative">
                <div style="position:absolute; left:-26px; top:5px; width:12px; height:12px; background:var(--primary); border-radius:50%; border:2px solid var(--surface);"></div>
                <div style="color:var(--text-white); font-weight:600;"><?php echo htmlspecialchars($l['logro']); ?></div>
                <small class="text-muted"><?php echo date('d M, Y', strtotime($l['fecha_logro'])); ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-danger text-center">No se encontró información.</div>
    <?php endif; ?>
</div>

<!-- MODAL PAGO -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ph-bold ph-wallet"></i> Renueva tu Membresía</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <h2 style="color:var(--primary); font-family:'Orbitron', sans-serif;">
                        $<?php echo number_format($detalles['cuota_mensual'] ?: 0, 2); ?>
                    </h2>
                    <p class="text-muted">Mensualidad</p>
                </div>
                <div class="pay-method" onclick="copyToClipboard('1503003986')">
                    <div class="d-flex justify-content-between"><span>Cuenta BBVA</span><i class="ph-bold ph-copy"></i></div>
                    <div class="h5 m-0 mt-1">1503003986</div>
                </div>
                <div class="pay-method" onclick="copyToClipboard('012020015030039868')">
                    <div class="d-flex justify-content-between"><span>CLABE</span><i class="ph-bold ph-copy"></i></div>
                    <div class="h5 m-0 mt-1">012020015030039868</div>
                </div>
                <p class="text-center text-muted mt-3 small">Titular: Lorenzo Gutierrez Esparza</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    // DATOS
    const asistenciasData = <?php echo json_encode($asistencias); ?> || [];
    const alumnoId = <?php echo json_encode($alumno_id); ?>; 
    let currentDate = new Date();

    function renderCalendar(date) {
        const container = document.getElementById('calendar-container');
        if(!container) return;
        
        const year = date.getFullYear();
        const month = date.getMonth();
        const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        
        document.getElementById('currentMonthLabel').innerText = `${monthNames[month]} ${year}`;

        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDay = new Date(year, month, 1).getDay();
        const startOffset = firstDay === 0 ? 6 : firstDay - 1;

        let html = `<div class="calendar-grid">
            <div class="day-header">L</div><div class="day-header">M</div><div class="day-header">M</div>
            <div class="day-header">J</div><div class="day-header">V</div><div class="day-header">S</div><div class="day-header">D</div>
            ${'<div></div>'.repeat(startOffset)}`;

        for(let i=1; i<=daysInMonth; i++) {
            const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
            const isPresent = asistenciasData.includes(dateStr);
            html += `<div class="day-cell ${isPresent ? 'present' : ''}">${i}</div>`;
        }
        html += '</div>';
        container.innerHTML = html;
    }

    window.changeMonth = (offset) => {
        currentDate.setMonth(currentDate.getMonth() + offset);
        renderCalendar(currentDate);
    }

    renderCalendar(currentDate);

    // CHECK-IN
    const checkInBtn = document.getElementById('checkInButton');
    if(checkInBtn) {
        checkInBtn.addEventListener('click', async () => {
            checkInBtn.disabled = true;
            const msgDiv = document.getElementById('checkInMessage');
            msgDiv.innerHTML = '<span class="text-muted">Conectando...</span>';
            
            const todayStr = new Date().toISOString().split('T')[0];
            const formData = new FormData();
            formData.append('alumno_id', alumnoId);
            formData.append('fecha_asistencia', todayStr);

            try {
                const res = await fetch('/MOKUSO/asistencias/guardar_asistencia.php', { method:'POST', body:formData });
                const data = await res.json();
                
                if(data.status === 'success') {
                    msgDiv.innerHTML = '<span style="color:var(--success)"><i class="ph-bold ph-check-circle"></i> ¡Asistencia Registrada!</span>';
                    checkInBtn.style.background = 'var(--success)';
                    checkInBtn.style.boxShadow = 'none';
                    if(!asistenciasData.includes(todayStr)) {
                        asistenciasData.push(todayStr);
                        renderCalendar(currentDate);
                    }
                } else {
                    msgDiv.innerHTML = `<span style="color:var(--warning)">${data.message}</span>`;
                }
            } catch(e) {
                msgDiv.innerHTML = '<span style="color:var(--danger)">Error de conexión</span>';
            } finally {
                setTimeout(() => { if(!msgDiv.innerHTML.includes('Registrada')) checkInBtn.disabled = false; }, 2000);
            }
        });
    }

    window.copyToClipboard = (text) => {
        navigator.clipboard.writeText(text);
        alert("Copiado: " + text); 
    };

    window.downloadPDF = () => {
        const element = document.getElementById('printable-area');
        html2canvas(element, { backgroundColor: '#202024', scale: 2 }).then(canvas => {
            const img = canvas.toDataURL('image/png');
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF();
            const props = pdf.getImageProperties(img);
            const h = (props.height * 190) / props.width;
            pdf.addImage(img, 'PNG', 10, 10, 190, h);
            pdf.save('Mokuso_ID.pdf');
        });
    };
</script>

</body>
</html>
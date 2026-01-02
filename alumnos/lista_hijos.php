<?php
session_start();
include '../config/db.php'; 

// 1. Verificar Sesión y Rol
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'padre') {
    header("Location: /MOKUSO/index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$padre_id = null;
$hijos = [];

// 2. Obtener Hijos
if ($conn) {
    // Obtener ID del padre
    $stmt_padre = $conn->prepare("SELECT id FROM padres WHERE user_id = ?");
    $stmt_padre->bind_param("i", $user_id);
    $stmt_padre->execute();
    $res_padre = $stmt_padre->get_result();
    
    if ($row = $res_padre->fetch_assoc()) {
        $padre_id = $row['id'];
    }
    $stmt_padre->close();

    if ($padre_id !== null) {
        // Consulta mejorada: Incluye fecha de vencimiento y conteo de clases del mes
        $sql_hijos = "
            SELECT 
                a.id, a.nombre, a.apellidos, a.estado_membresia, a.foto_perfil, a.fecha_vencimiento_membresia,
                n.nombre AS nivel_nombre,
                d.nombre AS disciplina_nombre,
                (SELECT fecha_asistencia FROM asistencias WHERE alumno_id = a.id ORDER BY fecha_asistencia DESC LIMIT 1) as ultima_asistencia,
                (SELECT COUNT(*) FROM asistencias WHERE alumno_id = a.id AND MONTH(fecha_asistencia) = MONTH(CURRENT_DATE()) AND YEAR(fecha_asistencia) = YEAR(CURRENT_DATE())) as clases_mes
            FROM alumnos a
            JOIN padres_alumnos pa ON a.id = pa.alumno_id
            LEFT JOIN niveles n ON a.nivel_id = n.id
            LEFT JOIN disciplinas d ON a.disciplina_id = d.id
            WHERE pa.padre_id = ?
            ORDER BY a.fecha_vencimiento_membresia ASC
        ";
        
        $stmt = $conn->prepare($sql_hijos);
        $stmt->bind_param("i", $padre_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            // Cálculos de Membresía por hijo
            $row['dias_restantes'] = -1;
            $row['porcentaje_tiempo'] = 0;
            
            if (!empty($row['fecha_vencimiento_membresia']) && $row['fecha_vencimiento_membresia'] != '0000-00-00') {
                $vence = new DateTime($row['fecha_vencimiento_membresia']);
                $hoy = new DateTime();
                $hoy->setTime(0,0,0); $vence->setTime(0,0,0);
                
                if ($vence >= $hoy) {
                    $diff = $hoy->diff($vence);
                    $row['dias_restantes'] = $diff->days;
                    // Barra visual (base 30 días)
                    $row['porcentaje_tiempo'] = min(100, ($diff->days / 30) * 100);
                } else {
                    $row['dias_restantes'] = -1; // Vencido
                }
            }
            $hijos[] = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Familiar - Mokuso Elite</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* --- TEMA ELITE --- */
        :root {
            --primary: #ff6600; --primary-glow: rgba(255, 102, 0, 0.4);
            --bg-main: #121214; --surface: #202024; --border: #323238;
            --text-white: #e1e1e6; --text-muted: #a8a8b3;
            --success: #04d361; --danger: #ff3e3e; --warning: #fad733;
            --radius: 16px;
        }

        body {
            background-color: var(--bg-main); color: var(--text-white); font-family: 'Poppins', sans-serif;
            min-height: 100vh; overflow-x: hidden; padding-bottom: 80px;
        }

        /* FONDO ANIMADO */
        .background-blur { position: fixed; inset: 0; z-index: -1; pointer-events: none; }
        .blur-circle { position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.3; animation: float 10s infinite alternate; }
        .b1 { top: -10%; left: -10%; width: 50vw; height: 50vw; background: var(--primary); }
        .b2 { bottom: -10%; right: -10%; width: 40vw; height: 40vw; background: #00bfff; animation-delay: -5s; }
        @keyframes float { from {transform: translate(0,0);} to {transform: translate(30px, 50px);} }

        .main-container { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* HEADER */
        .header-section { text-align: center; margin-bottom: 3rem; border-bottom: 1px solid var(--border); padding-bottom: 2rem; }
        .header-logo { height: 60px; margin-bottom: 1rem; filter: drop-shadow(0 0 15px var(--primary-glow)); }
        .header-title { font-family: 'Orbitron', sans-serif; font-size: 2rem; margin: 0; color: var(--text-white); }
        .header-subtitle { color: var(--text-muted); font-size: 1rem; }

        /* GRID DE HIJOS */
        .kids-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }

        /* TARJETA DE HIJO */
        .kid-card {
            background: rgba(32, 32, 36, 0.85); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 0; overflow: hidden; transition: transform 0.3s, box-shadow 0.3s;
            backdrop-filter: blur(15px); position: relative;
        }
        .kid-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.4); border-color: var(--primary); }

        /* Encabezado de Tarjeta (Foto + Info) */
        .kid-header {
            display: flex; align-items: center; gap: 1.5rem; padding: 1.5rem;
            background: linear-gradient(to right, rgba(255,255,255,0.03), transparent);
        }
        .kid-avatar {
            width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
            border: 3px solid var(--bg-main); outline: 2px solid var(--border);
        }
        .kid-info h2 { font-family: 'Orbitron', sans-serif; font-size: 1.3rem; margin: 0; color: var(--text-white); }
        .kid-rank { color: var(--primary); font-size: 0.9rem; font-weight: 600; margin-top: 2px; }
        .kid-stats { font-size: 0.8rem; color: var(--text-muted); margin-top: 5px; display: flex; gap: 10px; }
        .stat-pill { background: rgba(255,255,255,0.1); padding: 2px 8px; border-radius: 10px; }

        /* Estado de Membresía (Mini Dashboard) */
        .membership-mini { padding: 1rem 1.5rem; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.2); }
        .mem-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; display: flex; justify-content: space-between; margin-bottom: 5px; }
        .progress-track { height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 3px; transition: width 1s ease; }
        
        /* Colores de estado */
        .status-text.ok { color: var(--success); }
        .status-text.warn { color: var(--warning); }
        .status-text.danger { color: var(--danger); }

        /* Acciones */
        .kid-actions { padding: 1.5rem; display: flex; flex-direction: column; gap: 10px; }
        
        .btn-checkin {
            width: 100%; padding: 12px; border: none; border-radius: 12px;
            font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 0.95rem;
            background: linear-gradient(135deg, var(--primary), #ff8c00); color: #000;
            cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px;
        }
        .btn-checkin:hover:not(:disabled) { transform: scale(1.02); box-shadow: 0 0 20px rgba(255, 102, 0, 0.4); }
        
        .btn-checkin.done {
            background: rgba(4, 211, 97, 0.15); color: var(--success); border: 1px solid var(--success);
            cursor: default; box-shadow: none;
        }
        .btn-checkin:disabled { opacity: 0.7; cursor: not-allowed; }

        .btn-profile {
            width: 100%; background: transparent; border: 1px solid var(--border); color: var(--text-muted);
            padding: 10px; border-radius: 12px; font-weight: 600; text-decoration: none; text-align: center;
            transition: 0.2s; font-size: 0.9rem;
        }
        .btn-profile:hover { border-color: var(--text-white); color: var(--text-white); background: rgba(255,255,255,0.05); }

        /* Alerta sin hijos */
        .empty-state { text-align: center; padding: 4rem 2rem; background: var(--surface); border-radius: var(--radius); border: 1px dashed var(--border); }
        .empty-icon { font-size: 4rem; color: var(--text-muted); margin-bottom: 1rem; display: block; }
        
        .logout-link { display: block; text-align: center; margin-top: 3rem; color: var(--danger); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

<div class="background-blur">
    <div class="blur-circle b1"></div>
    <div class="blur-circle b2"></div>
</div>

<div class="main-container">
    
    <div class="header-section">
        <img src="/MOKUSO/assets/img/logo2.png" alt="Logo" class="header-logo">
        <h1 class="header-title">Panel Familiar</h1>
        <p class="header-subtitle">Gestiona el progreso y asistencia de tus hijos.</p>
    </div>

    <?php if (!empty($hijos)): ?>
        <div class="kids-grid">
            <?php foreach ($hijos as $hijo): 
                $hoy = date('Y-m-d');
                $ya_asistio = (isset($hijo['ultima_asistencia']) && $hijo['ultima_asistencia'] === $hoy);
                $estado = strtolower($hijo['estado_membresia']);
                $activo = ($estado === 'activa' || $estado === 'exento' || $estado === 'pendiente');
                
                // Configuración visual de membresía
                $barColor = 'var(--success)';
                $statusText = 'Activo';
                $statusClass = 'ok';
                $dias = $hijo['dias_restantes'];

                if ($dias < 0) {
                    $barColor = 'var(--danger)'; $statusText = 'Vencido'; $statusClass = 'danger';
                } elseif ($dias <= 5) {
                    $barColor = 'var(--warning)'; $statusText = $dias . ' días restantes'; $statusClass = 'warn';
                }
            ?>
            
            <div class="kid-card">
                <div class="kid-header">
                    <img src="/MOKUSO/assets/img/uploads/<?php echo htmlspecialchars($hijo['foto_perfil'] ?: 'default.png'); ?>" class="kid-avatar">
                    <div class="kid-info">
                        <h2><?php echo htmlspecialchars($hijo['nombre']); ?></h2>
                        <div class="kid-rank"><?php echo htmlspecialchars($hijo['nivel_nombre'] ?: 'Sin Grado'); ?></div>
                        <div class="kid-stats">
                            <span class="stat-pill"><i class="ph-fill ph-lightning"></i> <?php echo $hijo['clases_mes']; ?> clases este mes</span>
                        </div>
                    </div>
                </div>

                <!-- MINI DASHBOARD DE MEMBRESÍA -->
                <div class="membership-mini">
                    <div class="mem-label">
                        <span>Membresía</span>
                        <span class="status-text <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    </div>
                    <?php if($dias >= 0): ?>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: <?php echo $hijo['porcentaje_tiempo']; ?>%; background: <?php echo $barColor; ?>;"></div>
                        </div>
                    <?php else: ?>
                        <div class="progress-track"><div class="progress-fill" style="width: 100%; background: var(--danger);"></div></div>
                    <?php endif; ?>
                </div>

                <div class="kid-actions">
                    <!-- Botón Asistencia -->
                    <?php if ($ya_asistio): ?>
                        <button class="btn-checkin done" disabled>
                            <i class="ph-bold ph-check-circle"></i> Asistencia Registrada
                        </button>
                    <?php elseif (!$activo): ?>
                        <button class="btn-checkin" disabled style="background: #444; color: #888;">
                            <i class="ph-bold ph-lock"></i> Membresía Vencida
                        </button>
                    <?php else: ?>
                        <button class="btn-checkin" onclick="marcarAsistencia(this, <?php echo $hijo['id']; ?>)">
                            <i class="ph-bold ph-hand-tap"></i> Marcar Asistencia Hoy
                        </button>
                    <?php endif; ?>

                    <a href="perfil.php?alumno_id=<?php echo $hijo['id']; ?>" class="btn-profile">Ver Perfil Completo</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="ph-duotone ph-users-three empty-icon"></i>
            <h3>No hay alumnos vinculados</h3>
            <p class="text-muted">Si crees que esto es un error, contacta al maestro.</p>
        </div>
    <?php endif; ?>

    <a href="/MOKUSO/config/logout.php" class="logout-link"><i class="ph-bold ph-sign-out"></i> Cerrar Sesión</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function marcarAsistencia(btn, id) {
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Procesando...';
    btn.disabled = true;

    // --- CORRECCIÓN AQUÍ ---
    // Creamos la fecha usando la hora local del dispositivo
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0'); // Meses son 0-11, sumamos 1
    const day = String(d.getDate()).padStart(2, '0');
    const today = `${year}-${month}-${day}`;
    // -----------------------

    const formData = new FormData();
    formData.append('alumno_id', id);
    formData.append('fecha_asistencia', today);

    fetch('/MOKUSO/asistencias/guardar_asistencia.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            // Transformar botón a éxito
            btn.className = 'btn-checkin done';
            btn.innerHTML = '<i class="ph-bold ph-check-circle"></i> Asistencia Registrada';
            
            Swal.fire({
                icon: 'success',
                title: '¡Listo!',
                text: 'Asistencia registrada correctamente.',
                timer: 2000,
                showConfirmButton: false,
                background: '#202024', color: '#fff'
            });
        } else {
            throw new Error(data.message);
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message || 'No se pudo conectar con el servidor',
            background: '#202024', color: '#fff'
        });
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>

</body>
</html>
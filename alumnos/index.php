
<?php
include '../templates/header.php';
include '../config/db.php';

// --- SISTEMA AUTOMÁTICO DE VERIFICACIÓN (PHP 5.5 Compatible) ---
$fecha_actual = date('Y-m-d');
$fecha_sanitizada = $conn->real_escape_string($fecha_actual);
$conn->query("UPDATE alumnos SET estado_membresia = 'inactiva', fecha_ultima_inactividad = '{$fecha_sanitizada}' WHERE fecha_vencimiento_membresia < '{$fecha_sanitizada}' AND estado_membresia = 'activa'");

// --- OBTENER GRUPOS ---
$grupos_result = $conn->query("SELECT id, nombre FROM grupos ORDER BY nombre");
$grupos = array();
if ($grupos_result) {
    while ($grupo = $grupos_result->fetch_assoc()) {
        $grupos[] = $grupo;
    }
}

// --- OBTENER ALUMNOS ---
$query_alumnos = "SELECT a.id, a.nombre, a.apellidos, a.foto_perfil, a.estado_membresia, a.grupo_id, a.fecha_nacimiento, n.nombre AS nivel_nombre 
                  FROM alumnos AS a 
                  LEFT JOIN niveles AS n ON a.nivel_id = n.id 
                  ORDER BY a.estado_membresia ASC, a.apellidos ASC";
$result_alumnos = $conn->query($query_alumnos);
$alumnos = array();

if ($result_alumnos) {
    while ($alumno = $result_alumnos->fetch_assoc()) {
        // Cálculo de Edad para la tarjeta (PHP 5.5)
        $edad = 'N/A';
        if (!empty($alumno['fecha_nacimiento']) && $alumno['fecha_nacimiento'] != '0000-00-00') {
            $nacimiento = new DateTime($alumno['fecha_nacimiento']);
            $hoy = new DateTime();
            $diff = $hoy->diff($nacimiento);
            $edad = $diff->y;
        }
        $alumno['edad_calculada'] = $edad;
        $alumnos[] = $alumno;
    }
}
?>

<script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* --- VARIABLES GLOBALES (Mokuso Elite Theme) --- */
    :root {
        --primary: #ff6600; 
        --primary-dark: #cc5200;
        --primary-glow: rgba(255, 102, 0, 0.3);
        --bg-main: #121214;
        --surface: #202024;
        --border: #323238;
        --text-white: #e1e1e6;
        --text-gray: #a8a8b3;
        --success: #04d361;
        --danger: #ff3e3e;
        --warning: #fad733;
        --radius: 12px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        background-color: var(--bg-main);
        font-family: 'Poppins', sans-serif;
        color: var(--text-white);
        background-image: radial-gradient(circle at 10% 20%, rgba(255, 102, 0, 0.05) 0%, transparent 40%);
    }

    .main-container { padding: 2rem; max-width: 1400px; margin: 0 auto; }

    /* HEADER */
    .page-header {
        display: flex; justify-content: space-between; align-items: center;
        flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1.5rem;
    }
    .page-header h1 {
        font-family: 'Orbitron', sans-serif; color: var(--text-white); font-size: 2rem; margin: 0;
        display: flex; align-items: center; gap: 10px;
    }
    .page-header h1 i { color: var(--primary); }

    /* BOTONES */
    .btn {
        padding: 0.8rem 1.5rem; border-radius: 10px; font-weight: 600; text-decoration: none;
        display: inline-flex; align-items: center; gap: 8px; transition: var(--transition); border: none; cursor: pointer;
    }
    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px var(--primary-glow); }
    .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-gray); }
    .btn-outline:hover { border-color: var(--primary); color: var(--text-white); }

    /* FILTROS */
    .filters-bar {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: var(--radius); padding: 1.2rem; margin-bottom: 2.5rem;
        display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
    }
    .search-container { flex: 2; min-width: 280px; position: relative; }
    .search-container i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-gray); }
    .search-container input {
        width: 100%; padding: 0.9rem 1rem 0.9rem 3rem;
        background: rgba(0,0,0,0.2); border: 1px solid var(--border);
        border-radius: 10px; color: var(--text-white); outline: none;
    }
    .filter-selects select {
        padding: 0.9rem 1rem; background-color: rgba(0,0,0,0.3);
        border: 1px solid var(--border); border-radius: 10px; color: var(--text-white);
        cursor: pointer; min-width: 180px;
    }
    .filter-selects select option { background-color: #1a1a2e; color: white; }

    /* --- TARJETAS DE ALUMNOS (Tu diseño preferido) --- */
    .alumnos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }

    .student-card {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;
        position: relative; overflow: hidden; transition: var(--transition);
        border-left: 3px solid transparent; /* Para el estado */
    }
    .student-card.activa { border-left-color: var(--success); }
    .student-card.inactiva { border-left-color: var(--danger); opacity: 0.85; }
    .student-card.pendiente { border-left-color: var(--warning); }

    .student-card:hover {
        transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.4); border-color: var(--primary);
    }

    .card-header { display: flex; align-items: center; gap: 1rem; }
    
    .student-avatar-wrapper { position: relative; }
    .student-avatar {
        width: 65px; height: 65px; border-radius: 50%; object-fit: cover;
        border: 2px solid var(--border); background: var(--bg-main);
    }
    .belt-dot {
        width: 12px; height: 12px; border-radius: 50%; position: absolute; bottom: 2px; right: 0;
        border: 2px solid var(--surface);
    }

    .student-info h3 { margin: 0; font-size: 1.1rem; color: var(--text-white); font-weight: 600; }
    .student-info p { margin: 2px 0 0; font-size: 0.85rem; color: var(--text-gray); display: flex; align-items: center; gap: 5px; }

    .card-stats-mini {
        display: flex; justify-content: space-between; background: rgba(0,0,0,0.2);
        padding: 8px 12px; border-radius: 8px; margin-top: auto;
    }
    .stat-mini-item { display: flex; flex-direction: column; align-items: center; }
    .stat-mini-label { font-size: 0.7rem; color: var(--text-gray); text-transform: uppercase; }
    .stat-mini-val { font-size: 0.9rem; font-weight: 700; color: var(--text-white); }

    .card-actions { display: flex; gap: 8px; margin-top: 5px; }
    .btn-card {
        flex: 1; padding: 8px; border-radius: 8px; border: 1px solid var(--border);
        background: transparent; color: var(--text-gray); cursor: pointer; transition: var(--transition);
        display: flex; justify-content: center; align-items: center;
    }
    .btn-card:hover { background: var(--border); color: var(--text-white); border-color: var(--text-white); }
    .btn-card.view:hover { color: var(--primary); border-color: var(--primary); }
    
    .btn-quick-assist {
        width: 40px; border: 1px solid rgba(4, 211, 97, 0.3); color: var(--success);
        background: rgba(4, 211, 97, 0.1); border-radius: 8px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; transition: var(--transition);
    }
    .btn-quick-assist:hover { background: var(--success); color: #000; }

    /* --- ESTILOS DEL MODAL (ELITE PRO) --- */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 2000;
        display: none; align-items: center; justify-content: center; backdrop-filter: blur(5px);
    }
    .modal-overlay.show { display: flex; animation: fadeIn 0.3s; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .modal-content {
        background: var(--surface); width: 100%; max-width: 550px; border-radius: 20px;
        border: 1px solid var(--border); overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        display: flex; flex-direction: column; max-height: 90vh;
    }

    .modal-header-elite {
        text-align: center; padding: 2rem 1.5rem 1rem;
        background: linear-gradient(to bottom, rgba(30, 30, 35, 0.95), var(--surface));
        border-bottom: 1px solid var(--border); position: relative;
    }
    .modal-avatar-glow {
        width: 110px; height: 110px; margin: 0 auto 1rem; padding: 4px; border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), transparent);
       
    }
    @keyframes spin-slow { to { transform: rotate(360deg); } }
    .modal-avatar-img {
        width: 100%; height: 100%; border-radius: 50%; object-fit: cover;
        border: 4px solid var(--bg-main); background: var(--surface);
    }

    .modal-body-scroll { padding: 1.5rem; overflow-y: auto; }

    /* GRIDS DE INFORMACIÓN */
    .info-section-title {
        font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;
        color: var(--text-gray); margin: 1.5rem 0 0.8rem; display: block; font-weight: 700; border-bottom: 1px solid var(--border); padding-bottom: 5px;
    }
    .stats-grid-elite { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    
    .stat-card {
        background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border);
        border-radius: 12px; padding: 12px; display: flex; align-items: center; gap: 12px;
    }
    .stat-icon-box {
        width: 38px; height: 38px; border-radius: 8px; background: rgba(0,0,0,0.3);
        color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
    }
    .stat-data { display: flex; flex-direction: column; }
    .stat-label { font-size: 0.7rem; color: var(--text-gray); }
    .stat-value { font-size: 0.95rem; font-weight: 600; color: var(--text-white); }

    /* ALERTAS */
    .alert-box {
        padding: 12px; border-radius: 10px; margin-top: 15px; font-size: 0.85rem;
        display: flex; align-items: center; gap: 12px;
    }
    .alert-danger { background: rgba(255, 62, 62, 0.1); border: 1px solid rgba(255, 62, 62, 0.3); color: var(--danger); }

    /* ACORDEÓN LOGROS */
    .achievements-container { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
    .achievements-header {
        background: rgba(255,255,255,0.03); padding: 12px 15px; cursor: pointer;
        display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; font-weight: 600;
    }
    .achievements-list { list-style: none; padding: 0; margin: 0; display: none; background: rgba(0,0,0,0.2); }
    .achievements-list.show { display: block; }
    .achievements-list li {
        padding: 10px 15px; border-bottom: 1px solid var(--border); font-size: 0.85rem; display: flex; gap: 10px; align-items: center;
    }
    .medal-icon { color: var(--warning); font-size: 1.2rem; }

    /* ESTADO VACÍO */
    .empty-state {
        grid-column: 1 / -1; padding: 4rem; text-align: center;
        background: var(--surface); border: 1px dashed var(--border); border-radius: 20px;
    }
</style>

<div class="main-container">
    <div class="page-header">
        <h1><i class="ph-fill ph-users-three"></i> Directorio de Alumnos</h1>
        <div class="header-actions">
            <a href="registrar.php" class="btn btn-primary"><i class="ph-bold ph-user-plus"></i> Nuevo Alumno</a>
            <a href="/MOKUSO/alumnos/ver_alumnos.php" class="btn btn-outline"><i class="ph-bold ph-eye"></i> Ver Todos</a>
        </div>
    </div>

    <div class="filters-bar">
        <div class="search-container">
            <i class="ph-bold ph-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Buscar por nombre...">
        </div>
        <div class="filter-selects" style="display:flex; gap:10px;">
            <select id="statusFilter">
                <option value="all">Todos los Estados</option>
                <option value="activa">Activos</option>
                <option value="inactiva">Inactivos</option>
                <option value="pendiente">Pendientes</option>
                <option value="exento">Exentos</option>
            </select>
            <select id="groupFilter">
                <option value="all">Todos los Grupos</option>
                <?php foreach ($grupos as $grupo): ?>
                    <option value="<?php echo $grupo['id']; ?>"><?php echo htmlspecialchars($grupo['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="alumnos-grid" id="alumnosListContainer">
        <?php if (!empty($alumnos)): ?>
            <?php foreach ($alumnos as $alumno): ?>
                <div class="student-card <?php echo htmlspecialchars($alumno['estado_membresia']); ?>"
                     data-id="<?php echo $alumno['id']; ?>"
                     data-nombre="<?php echo strtolower(htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos'])); ?>"
                     data-status="<?php echo htmlspecialchars($alumno['estado_membresia']); ?>"
                     data-grupo-id="<?php echo $alumno['grupo_id']; ?>">
                    
                    <div class="card-header">
                        <div class="student-avatar-wrapper">
                            <?php $foto = !empty($alumno['foto_perfil']) ? htmlspecialchars($alumno['foto_perfil']) : 'default.png'; ?>
                            <img src="/MOKUSO/assets/img/uploads/<?php echo $foto; ?>" class="student-avatar" onerror="this.src='/MOKUSO/assets/img/uploads/default.png'">
                            <div class="belt-dot" style="background-color: <?php echo ($alumno['estado_membresia'] == 'activa') ? 'var(--success)' : (($alumno['estado_membresia'] == 'inactiva') ? 'var(--danger)' : 'var(--warning)'); ?>"></div>
                        </div>
                        <div class="student-info">
                            <h3><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></h3>
                            <p><i class="ph-fill ph-medal"></i> <?php echo isset($alumno['nivel_nombre']) ? htmlspecialchars($alumno['nivel_nombre']) : 'Sin Cinta'; ?></p>
                        </div>
                    </div>

                    <div class="card-stats-mini">
                        <div class="stat-mini-item">
                            <span class="stat-mini-label">Edad</span>
                            <span class="stat-mini-val"><?php echo $alumno['edad_calculada']; ?></span>
                        </div>
                        <div class="stat-mini-item">
                            <span class="stat-mini-label">Estado</span>
                            <span class="stat-mini-val" style="color: <?php echo ($alumno['estado_membresia'] == 'activa') ? 'var(--success)' : 'var(--text-gray)'; ?>">
                                <?php echo ucfirst($alumno['estado_membresia']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-actions">
                        <button class="btn-card view" onclick="openModal(<?php echo $alumno['id']; ?>)" title="Ver Expediente">
                            <i class="ph-bold ph-eye"></i>
                        </button>
                        <a href="/MOKUSO/pagos/registrar_pago.php?alumno_id=<?php echo $alumno['id']; ?>" class="btn-card" title="Cobrar">
                            <i class="ph-bold ph-currency-dollar"></i>
                        </a>
                        <a href="/MOKUSO/alumnos/editar_alumno.php?id=<?php echo $alumno['id']; ?>" class="btn-card" title="Editar">
                            <i class="ph-bold ph-pencil-simple"></i>
                        </a>
                        <?php if ($alumno['estado_membresia'] == 'activa' || $alumno['estado_membresia'] == 'exento'): ?>
                            <button class="btn-quick-assist" onclick="quickAssist(this, <?php echo $alumno['id']; ?>)" title="Asistencia Rápida">
                                <i class="ph-bold ph-check"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="ph-duotone ph-ghost" style="font-size: 4rem; color: var(--text-gray);"></i>
                <h3 style="margin-top:1rem;">No hay alumnos registrados</h3>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="modalDetail" class="modal-overlay">
    <div class="modal-content">
        <div id="modalLoader" style="padding:4rem; text-align:center;">
            <i class="ph-bold ph-spinner-gap ph-spin" style="font-size: 3rem; color: var(--primary);"></i>
        </div>
        <div id="modalData" style="display:none;"></div>
    </div>
</div>

<script>
    // --- 1. LÓGICA DE FILTRADO ---
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const groupFilter = document.getElementById('groupFilter');
    const cards = document.querySelectorAll('.student-card');

    function filterAlumnos() {
        const query = searchInput.value.toLowerCase().trim();
        const status = statusFilter.value;
        const group = groupFilter.value;

        cards.forEach(card => {
            const matchQuery = !query || card.dataset.nombre.includes(query);
            const matchStatus = status === 'all' || card.dataset.status === status;
            const matchGroup = group === 'all' || card.dataset.grupoId === group;

            card.style.display = (matchQuery && matchStatus && matchGroup) ? 'flex' : 'none';
        });
    }

    searchInput.addEventListener('input', filterAlumnos);
    statusFilter.addEventListener('change', filterAlumnos);
    groupFilter.addEventListener('change', filterAlumnos);

    // --- 2. ASISTENCIA RÁPIDA (AJAX) ---
    async function quickAssist(btn, id) {
        // Prevenimos que el click se propague si está dentro de otro elemento
        if(event) event.stopPropagation();

        const originalIcon = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i>';
        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('alumno_id', id);

            const res = await fetch('/MOKUSO/asistencias/registrar_asistencia.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await res.json(); 

            if (data.success) {
                const Toast = Swal.mixin({
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 2000,
                    background: '#202024', color: '#fff', iconColor: '#04d361'
                });
                Toast.fire({ icon: 'success', title: 'Asistencia registrada' });
                btn.style.background = 'var(--success)';
                btn.style.color = '#000';
            } else {
                throw new Error(data.error || 'Error desconocido');
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo registrar', background: '#202024', color: '#fff' });
        } finally {
            setTimeout(() => {
                btn.innerHTML = originalIcon;
                btn.disabled = false;
                if(btn.style.background !== 'var(--success)') {
                     btn.style.background = ''; btn.style.color = '';
                }
            }, 2000);
        }
    }

    // --- 3. LÓGICA DEL MODAL ELITE PRO ---
    const modal = document.getElementById('modalDetail');
    const modalLoader = document.getElementById('modalLoader');
    const modalData = document.getElementById('modalData');

    function closeModal() { modal.classList.remove('show'); }
    
    // Cerrar al dar click fuera
    modal.addEventListener('click', (e) => {
        if(e.target === modal) closeModal();
    });

    async function openModal(id) {
        modal.classList.add('show');
        modalLoader.style.display = 'block';
        modalData.style.display = 'none';

        try {
            const res = await fetch(`/MOKUSO/alumnos/api_get_alumno.php?id=${id}`);
            const data = await res.json();
            
            if(data.error) throw new Error(data.message);
            
            const d = data.detalles;
            const asis = data.asistencias;
            const foto = d.foto_perfil || 'default.png';

            // Helper Formateo Fecha
            const formatDate = (dateStr) => {
                if (!dateStr || dateStr.startsWith('0000')) return 'N/A';
                return new Date(dateStr).toLocaleDateString('es-ES', {day:'numeric', month:'short', year:'numeric'});
            };

            // Lógica de Tiempo e Inactividad
            let tiempoTexto = data.tiempo_miembro_str || 'Nuevo Ingreso';
            let alertaInactividad = '';
            
            if (data.tiempo_inactivo_str) {
                alertaInactividad = `
                    <div class="alert-box alert-danger">
                        <i class="ph-bold ph-warning-circle" style="font-size:1.5rem;"></i>
                        <div>
                            <strong>Inactivo desde:</strong> ${data.tiempo_inactivo_str}<br>
                            <small>El tiempo activo fue pausado.</small>
                        </div>
                    </div>`;
            }

            // Lógica de Logros (Acordeón)
            let logrosHTML = '<div style="padding:15px; color:var(--text-gray); font-size:0.85rem;">Sin logros registrados.</div>';
            if (data.logros && data.logros.length > 0) {
                logrosHTML = '<ul class="achievements-list" id="achievementsList">';
                data.logros.forEach(l => {
                    logrosHTML += `
                        <li>
                            <i class="ph-fill ph-trophy medal-icon"></i>
                            <div>
                                <strong style="color:var(--text-white)">${l.logro}</strong>
                                <div style="font-size:0.75rem; color:var(--text-gray)">${formatDate(l.fecha_logro)}</div>
                            </div>
                        </li>`;
                });
                logrosHTML += '</ul>';
            }

            // Construcción del HTML Interno
            modalData.innerHTML = `
                <div class="modal-header-elite">
                    <button onclick="closeModal()" style="position:absolute; top:15px; right:15px; background:none; border:none; color:var(--text-gray); font-size:1.5rem; cursor:pointer;">&times;</button>
                    <div class="modal-avatar-glow">
                        <img src="/MOKUSO/assets/img/uploads/${foto}" class="modal-avatar-img" onerror="this.src='/MOKUSO/assets/img/uploads/default.png'">
                    </div>
                    <h2 style="margin:0; color:var(--text-white); font-family:'Orbitron', sans-serif;">${d.nombre} ${d.apellidos}</h2>
                    <div style="display:flex; justify-content:center; gap:10px; margin-top:5px; font-size:0.9rem;">
                        <span style="color:var(--primary); font-weight:600;"><i class="ph-fill ph-medal"></i> ${d.nivel_nombre || 'Sin Nivel'}</span>
                        <span style="color:var(--text-gray)">|</span>
                        <span style="color:var(--text-white)">${d.disciplina_nombre || 'General'}</span>
                    </div>
                    
                    <span style="display:inline-block; margin-top:10px; padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:bold; 
                          background:${d.estado_membresia === 'activa' ? 'rgba(4, 211, 97, 0.15)' : 'rgba(255, 62, 62, 0.15)'};
                          color:${d.estado_membresia === 'activa' ? 'var(--success)' : 'var(--danger)'}; border:1px solid currentColor;">
                        ${d.estado_membresia.toUpperCase()}
                    </span>
                </div>

                <div class="modal-body-scroll">
                    
                    <span class="info-section-title">Datos del Guerrero</span>
                    <div class="stats-grid-elite">
                        <div class="stat-card">
                            <div class="stat-icon-box"><i class="ph-duotone ph-cake"></i></div>
                            <div class="stat-data"><span class="stat-label">Edad</span><span class="stat-value">${d.edad || '?'} años</span></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-box"><i class="ph-duotone ph-phone"></i></div>
                            <div class="stat-data"><span class="stat-label">Emergencia</span><span class="stat-value">${d.telefono_emergencia || 'N/A'}</span></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-box"><i class="ph-duotone ph-ruler"></i></div>
                            <div class="stat-data"><span class="stat-label">Estatura</span><span class="stat-value">${d.estatura ? d.estatura + ' m' : 'N/A'}</span></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-box"><i class="ph-duotone ph-scales"></i></div>
                            <div class="stat-data"><span class="stat-label">Peso</span><span class="stat-value">${d.peso ? d.peso + ' kg' : 'N/A'}</span></div>
                        </div>
                    </div>

                    <span class="info-section-title">Actividad en el Dojo</span>
                    <div class="stats-grid-elite">
                        <div class="stat-card" style="grid-column: span 2;">
                            <div class="stat-icon-box" style="color:var(--success)"><i class="ph-duotone ph-calendar-check"></i></div>
                            <div class="stat-data">
                                <span class="stat-label">Asistencia Global</span>
                                <span class="stat-value">${asis.porcentaje}% <span style="font-size:0.7rem; font-weight:400; color:var(--text-gray)">(${formatDate(asis.ultima_asistencia)})</span></span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-box"><i class="ph-duotone ph-hourglass"></i></div>
                            <div class="stat-data"><span class="stat-label">Antigüedad</span><span class="stat-value" style="font-size:0.8rem">${tiempoTexto}</span></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-box"><i class="ph-duotone ph-calendar"></i></div>
                            <div class="stat-data"><span class="stat-label">Registro</span><span class="stat-value">${formatDate(d.fecha_registro)}</span></div>
                        </div>
                    </div>
                    
                    ${alertaInactividad}

                    <span class="info-section-title">Historial</span>
                    <div class="achievements-container">
                        <div class="achievements-header" onclick="toggleAchievements()">
                            <span><i class="ph-fill ph-medal"></i> Ver Logros y Cintas</span>
                            <i class="ph-bold ph-caret-down" id="achieveIcon"></i>
                        </div>
                        ${logrosHTML}
                    </div>

                    <div style="display:flex; gap:10px; margin-top:25px;">
                        <a href="/MOKUSO/pagos/registrar_pago.php?alumno_id=${d.id}" class="btn btn-primary" style="flex:1; justify-content:center;">
                            <i class="ph-bold ph-currency-dollar"></i> Cobrar
                        </a>
                        <a href="/MOKUSO/alumnos/editar_alumno.php?id=${d.id}" class="btn btn-outline" style="flex:1; justify-content:center;">
                            <i class="ph-bold ph-pencil-simple"></i> Editar
                        </a>
                    </div>
                </div>
            `;
            
            modalLoader.style.display = 'none';
            modalData.style.display = 'block';

        } catch (e) {
            console.error(e);
            modalData.innerHTML = `<div style="padding:2rem; text-align:center; color:var(--danger)"><i class="ph-bold ph-warning"></i><br>Error al cargar datos del alumno.</div>`;
            modalLoader.style.display = 'none';
            modalData.style.display = 'block';
        }
    }

    // Toggle para los logros
    function toggleAchievements() {
        const list = document.getElementById('achievementsList');
        const icon = document.getElementById('achieveIcon');
        if(list) {
            list.classList.toggle('show');
            if(list.classList.contains('show')) {
                icon.classList.replace('ph-caret-down', 'ph-caret-up');
            } else {
                icon.classList.replace('ph-caret-up', 'ph-caret-down');
            }
        }
    }
</script>

<?php 
$conn->close();
include '../templates/footer.php'; 
?>
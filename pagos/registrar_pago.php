<?php
session_start();
// 1. SEGURIDAD
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['maestro', 'admin'])) {
    header("Location: /MOKUSO/index.php");
    exit();
}

include '../config/db.php';

// --- 2. PROCESAMIENTO DE FORMULARIOS (PHP) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    try {
        $conn->begin_transaction();

        switch ($action) {
            // CASO A: REGISTRAR MENSUALIDAD (LÓGICA ESTRICTA DE CALENDARIO)
            case 'registrar_mensualidad':
                // Datos recibidos
                $alumno_id = filter_input(INPUT_POST, 'alumno_id', FILTER_VALIDATE_INT);
                $tipo_pago_id = filter_input(INPUT_POST, 'tipo_pago_id', FILTER_VALIDATE_INT);
                $meses_a_pagar = filter_input(INPUT_POST, 'meses', FILTER_VALIDATE_INT) ?: 1;
                $fecha_pago = filter_input(INPUT_POST, 'fecha_pago', FILTER_SANITIZE_STRING); // Fecha en que entrega el dinero

                if (!$alumno_id || !$tipo_pago_id) throw new Exception("Faltan datos del alumno o tipo de pago.");

                // 1. Obtener info del Pago (Monto Base)
                $stmt_tipo = $conn->prepare("SELECT concepto, monto FROM tipos_pago WHERE id = ?");
                $stmt_tipo->bind_param("i", $tipo_pago_id);
                $stmt_tipo->execute();
                $res_tipo = $stmt_tipo->get_result()->fetch_assoc();
                $stmt_tipo->close();

                $monto_unitario = $res_tipo['monto'];
                $monto_total = $monto_unitario * $meses_a_pagar;
                $concepto_base = $res_tipo['concepto'];
                
                // Descripción detallada
                $descripcion_mov = "Mensualidad: $concepto_base (x$meses_a_pagar mes" . ($meses_a_pagar > 1 ? 'es' : '') . ")";

                // 2. Lógica de Fechas (Vencimiento a Fin de Mes)
                // Obtener vencimiento actual del alumno
                $stmt_check = $conn->prepare("SELECT fecha_vencimiento_membresia FROM alumnos WHERE id = ?");
                $stmt_check->bind_param("i", $alumno_id);
                $stmt_check->execute();
                $current_data = $stmt_check->get_result()->fetch_assoc();
                $vencimiento_actual = $current_data['fecha_vencimiento_membresia'];
                $stmt_check->close();

                $hoy = date('Y-m-d');
                
                // REGLA: Si paga atrasado (ej: 25 Nov), cubre Noviembre. Si paga adelantado, cubre siguientes.
                
                if ($vencimiento_actual && $vencimiento_actual >= $hoy) {
                    // ALUMNO VIGENTE: Empezamos a contar desde el mes siguiente a su vencimiento actual
                    $fecha_base = new DateTime($vencimiento_actual);
                    $fecha_base->modify('first day of next month');
                } else {
                    // ALUMNO VENCIDO O NUEVO: Empezamos a contar desde el 1ro de ESTE mes (mes del pago)
                    $fecha_base = new DateTime($fecha_pago); 
                    $fecha_base->modify('first day of this month');
                }

                // Sumar los meses pagados. 
                // Nota: Si paga 1 mes, no sumamos nada al mes base, solo vamos al final de ese mes.
                $meses_extra = $meses_a_pagar - 1;
                if ($meses_extra > 0) {
                    $fecha_base->modify("+$meses_extra months");
                }
                
                // Forzar siempre al ÚLTIMO día del mes resultante
                $fecha_base->modify('last day of this month');
                $nueva_fecha_vencimiento = $fecha_base->format('Y-m-d');

                // 3. Insertar Movimiento Financiero
                $sql_mov = "INSERT INTO movimientos (tipo, descripcion, monto, fecha, alumno_id) VALUES ('ingreso_mensualidad', ?, ?, ?, ?)";
                $stmt_mov = $conn->prepare($sql_mov);
                $stmt_mov->bind_param("sdsi", $descripcion_mov, $monto_total, $fecha_pago, $alumno_id);
                $stmt_mov->execute();
                $stmt_mov->close();

                // 4. Actualizar Alumno (Activar y poner nueva fecha)
                // Nota: Si estamos antes del día 5, es 'activa'. Si es después del 5 y pagó, también se reactiva.
                $nuevo_estado = ($monto_total > 0) ? 'activa' : 'exento';
                
                $sql_upd = "UPDATE alumnos SET estado_membresia = ?, fecha_vencimiento_membresia = ?, fecha_ultima_inactividad = NULL WHERE id = ?";
                $stmt_upd = $conn->prepare($sql_upd);
                $stmt_upd->bind_param("ssi", $nuevo_estado, $nueva_fecha_vencimiento, $alumno_id);
                $stmt_upd->execute();
                $stmt_upd->close();

                $_SESSION['flash_message'] = [
                    'type' => 'success', 
                    'message' => "Pago registrado. Membresía válida hasta el <b>" . date("d/m/Y", strtotime($nueva_fecha_vencimiento)) . "</b>"
                ];
                break;

            // CASO B: OTROS INGRESOS
            case 'registrar_ingreso':
                $desc = filter_input(INPUT_POST, 'ingreso_descripcion', FILTER_SANITIZE_STRING);
                $monto = filter_input(INPUT_POST, 'ingreso_monto', FILTER_VALIDATE_FLOAT);
                $fecha = filter_input(INPUT_POST, 'ingreso_fecha', FILTER_SANITIZE_STRING);

                if (!$desc || !$monto) throw new Exception("Datos incompletos.");

                $stmt = $conn->prepare("INSERT INTO movimientos (tipo, descripcion, monto, fecha) VALUES ('ingreso_otro', ?, ?, ?)");
                $stmt->bind_param("sds", $desc, $monto, $fecha);
                $stmt->execute();
                $stmt->close();
                
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Ingreso extra registrado.'];
                break;

            // CASO C: GASTOS
            case 'registrar_gasto':
                $desc = filter_input(INPUT_POST, 'gasto_descripcion', FILTER_SANITIZE_STRING);
                $monto = filter_input(INPUT_POST, 'gasto_monto', FILTER_VALIDATE_FLOAT);
                $fecha = filter_input(INPUT_POST, 'gasto_fecha', FILTER_SANITIZE_STRING);
                $cat_id = filter_input(INPUT_POST, 'gasto_categoria_id', FILTER_VALIDATE_INT);

                if (!$desc || !$monto || !$cat_id) throw new Exception("Datos de gasto incompletos.");

                $stmt = $conn->prepare("INSERT INTO movimientos (tipo, descripcion, monto, fecha, categoria_gasto_id) VALUES ('gasto', ?, ?, ?, ?)");
                $stmt->bind_param("sdsi", $desc, $monto, $fecha, $cat_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Gasto registrado correctamente.'];
                break;
        }

        $conn->commit();
        header("Location: registrar_pago.php"); 
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        header("Location: registrar_pago.php");
        exit();
    }
}

// --- 3. CARGA DE DATOS PARA LA VISTA ---
$alumnos = $conn->query("SELECT id, nombre, apellidos, estado_membresia, fecha_vencimiento_membresia, foto_perfil FROM alumnos ORDER BY apellidos ASC");
$alumnos_data = []; 
while($row = $alumnos->fetch_assoc()) { $alumnos_data[] = $row; } 

$tipos_pago = $conn->query("SELECT * FROM tipos_pago ORDER BY monto ASC");
$categorias = $conn->query("SELECT * FROM categorias_gastos ORDER BY nombre ASC");

$flash = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
unset($_SESSION['flash_message']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Caja y Pagos - Mokuso Elite</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Fuentes e Iconos -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    
    <!-- Librerías -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        /* --- ESTILOS ELITE UNIFICADOS --- */
        :root {
            --primary: #ff6600; 
            --bg: #121214; --surface: #202024; --border: #323238;
            --text: #e1e1e6; --text-muted: #a8a8b3; 
            --success: #04d361; --danger: #ff3e3e; --warning: #fad733;
        }
        body { background-color: var(--bg); color: var(--text); font-family: 'Poppins', sans-serif; margin: 0; }
        
        .main-container { padding: 2rem; max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem; }
        @media(max-width: 900px) { .main-container { grid-template-columns: 1fr; } }

        /* HEADER */
        .page-header { grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
        .page-title { font-family: 'Orbitron', sans-serif; font-size: 1.8rem; color: var(--text); margin: 0; display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--primary); }

        /* TABS DE NAVEGACIÓN */
        .tabs-container { grid-column: 1 / -1; display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab-btn {
            background: var(--surface); border: 1px solid var(--border); color: var(--text-muted);
            padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; flex: 1;
            display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; min-width: 150px;
        }
        .tab-btn:hover { border-color: var(--primary); color: var(--text); }
        .tab-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3); }

        /* FORMULARIOS */
        .form-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 2rem; }
        .form-section { display: none; animation: fadeIn 0.4s; }
        .form-section.active { display: block; }
        @keyframes fadeIn { from{opacity:0; transform: translateY(10px);} to{opacity:1; transform: translateY(0);} }

        .form-group { margin-bottom: 1.5rem; }
        label { display: block; color: var(--text-muted); margin-bottom: 0.5rem; font-size: 0.9rem; }
        
        input, select {
            width: 100%; background: #18181b; border: 1px solid var(--border); color: white;
            padding: 12px; border-radius: 10px; font-family: inherit; outline: none; box-sizing: border-box;
        }
        input:focus, select:focus { border-color: var(--primary); }

        /* BOTONES DE MESES */
        .months-wrapper { display: flex; gap: 10px; margin-top: 5px; flex-wrap: wrap; }
        .month-option {
            flex: 1; background: rgba(255,255,255,0.05); border: 1px solid var(--border);
            padding: 10px; border-radius: 8px; text-align: center; cursor: pointer; transition: 0.2s; font-size: 0.9rem; min-width: 60px;
        }
        .month-option:hover { background: rgba(255,255,255,0.1); }
        .month-option.selected { background: var(--success); color: black; border-color: var(--success); font-weight: 700; }

        /* SELECT2 DARK THEME */
        .select2-container--default .select2-selection--single { background-color: #18181b; border: 1px solid var(--border); border-radius: 10px; height: 45px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--text); line-height: 45px; padding-left: 12px; }
        .select2-dropdown { background-color: var(--surface); border: 1px solid var(--border); }
        .select2-results__option { color: var(--text); }
        .select2-search__field { background-color: #18181b; color: white; border: 1px solid var(--border); }
        .select2-container--default .select2-results__option--highlighted[aria-selected] { background-color: var(--primary); }

        /* PANEL RESUMEN (STICKY) */
        .summary-panel { position: sticky; top: 20px; height: fit-content; }
        .preview-card { 
            background: linear-gradient(145deg, #202024, #1a1a1e); 
            border: 1px solid var(--border); border-radius: 16px; padding: 2rem; text-align: center; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.4); position: relative;
        }
        
        .preview-avatar { 
            width: 100px; height: 100px; border-radius: 50%; object-fit: cover; 
            border: 3px solid var(--bg); outline: 2px solid var(--primary); margin-bottom: 1rem;
        }
        .preview-name { font-size: 1.3rem; font-weight: 700; margin: 0; font-family: 'Orbitron', sans-serif; }
        .preview-badge { 
            display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; 
            margin-top: 5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
        }
        .status-activa { background: rgba(4, 211, 97, 0.15); color: var(--success); border: 1px solid var(--success); }
        .status-inactiva { background: rgba(255, 62, 62, 0.15); color: var(--danger); border: 1px solid var(--danger); }
        .status-pendiente { background: rgba(250, 215, 51, 0.15); color: var(--warning); border: 1px solid var(--warning); }

        .bill-details { margin-top: 20px; text-align: left; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 12px; border: 1px solid var(--border); }
        .bill-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; color: var(--text-muted); }
        .bill-row.total { border-top: 1px solid var(--border); margin-top: 10px; padding-top: 10px; font-size: 1.1rem; color: var(--text); font-weight: 700; }
        .highlight { color: var(--success); }

        /* Alerta Visual de Vencimiento Próximo */
        .warning-alert {
            margin-top: 15px; padding: 10px; background: rgba(250, 215, 51, 0.1); 
            border: 1px solid var(--warning); border-radius: 8px; color: var(--warning);
            font-size: 0.85rem; display: flex; align-items: center; gap: 10px; text-align: left;
            display: none; /* Oculto por defecto */
        }

        .btn-pay {
            width: 100%; background: linear-gradient(135deg, var(--success), #00a844); border: none; margin-top: 20px;
            padding: 15px; border-radius: 12px; color: black; font-weight: 700; cursor: pointer; font-size: 1rem;
            display: flex; align-items: center; justify-content: center; gap: 10px; transition: transform 0.2s;
        }
        .btn-pay:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(4, 211, 97, 0.3); }
        .btn-pay:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .btn-ghost { color: var(--text-muted); text-decoration: none; font-weight: 600; transition: 0.3s; }
        .btn-ghost:hover { color: var(--text); }

        /* ESTILOS ALERTAS */
        .swal2-popup { background: var(--surface) !important; color: var(--text) !important; border: 1px solid var(--border); }
        .swal2-title { color: var(--text) !important; }
    </style>
</head>
<body>

<div class="main-container">
    
    <div class="page-header">
        <h1 class="page-title"><i class="ph-fill ph-cash-register"></i> Terminal de Pagos</h1>
        <a href="/MOKUSO/dashboard/index.php" class="btn-ghost"><i class="ph-bold ph-arrow-left"></i> Volver</a>
    </div>

    <!-- TABS -->
    <div class="tabs-container">
        <button class="tab-btn active" onclick="switchTab('mensualidad')"><i class="ph-bold ph-user"></i> Cobrar Mensualidad</button>
        <button class="tab-btn" onclick="switchTab('ingreso')"><i class="ph-bold ph-trend-up"></i> Otro Ingreso</button>
        <button class="tab-btn" onclick="switchTab('gasto')"><i class="ph-bold ph-trend-down"></i> Registrar Gasto</button>
    </div>

    <!-- COLUMNA IZQUIERDA (FORMULARIOS) -->
    <div class="forms-column">
        
        <!-- 1. FORMULARIO MENSUALIDAD -->
        <div id="form-mensualidad" class="form-section active form-card">
            <form action="" method="POST" id="formPay">
                <input type="hidden" name="action" value="registrar_mensualidad">
                
                <div class="form-group">
                    <label>Seleccionar Alumno</label>
                    <select id="alumno_id" name="alumno_id" style="width: 100%;" required>
                        <option value="">Buscar alumno...</option>
                        <?php foreach($alumnos_data as $alum): ?>
                            <option value="<?php echo $alum['id']; ?>" 
                                    data-foto="<?php echo $alum['foto_perfil']; ?>"
                                    data-status="<?php echo $alum['estado_membresia']; ?>"
                                    data-vence="<?php echo $alum['fecha_vencimiento_membresia']; ?>">
                                <?php echo $alum['apellidos'] . " " . $alum['nombre']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Concepto de Pago</label>
                    <select name="tipo_pago_id" id="tipo_pago_id" onchange="updateTotals()" required>
                        <?php while($tp = $tipos_pago->fetch_assoc()): ?>
                            <option value="<?php echo $tp['id']; ?>" data-monto="<?php echo $tp['monto']; ?>">
                                <?php echo $tp['concepto']; ?> ($<?php echo $tp['monto']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Periodo a Pagar</label>
                    <input type="hidden" name="meses" id="meses_input" value="1">
                    <div class="months-wrapper">
                        <div class="month-option selected" onclick="selectMonth(1, this)">1 Mes</div>
                        <div class="month-option" onclick="selectMonth(3, this)">3 Meses</div>
                        <div class="month-option" onclick="selectMonth(6, this)">6 Meses</div>
                        <div class="month-option" onclick="selectMonth(12, this)">1 Año</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Fecha de Pago</label>
                    <input type="date" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </form>
        </div>

        <!-- 2. FORMULARIO OTROS INGRESOS -->
        <div id="form-ingreso" class="form-section form-card">
            <form action="" method="POST">
                <input type="hidden" name="action" value="registrar_ingreso">
                <div class="form-group">
                    <label>Descripción</label>
                    <input type="text" name="ingreso_descripcion" placeholder="Ej: Venta de Camiseta" required>
                </div>
                <div class="form-group">
                    <label>Monto ($)</label>
                    <input type="number" name="ingreso_monto" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="ingreso_fecha" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <button type="submit" class="btn-pay" style="background: var(--primary); color:white;">Guardar Ingreso</button>
            </form>
        </div>

        <!-- 3. FORMULARIO GASTOS -->
        <div id="form-gasto" class="form-section form-card">
            <form action="" method="POST">
                <input type="hidden" name="action" value="registrar_gasto">
                <div class="form-group">
                    <label>Descripción del Gasto</label>
                    <input type="text" name="gasto_descripcion" placeholder="Ej: Pago de Luz" required>
                </div>
                <div class="form-group">
                    <label>Categoría</label>
                    <select name="gasto_categoria_id" required>
                        <?php while($cat = $categorias->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo $cat['nombre']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto ($)</label>
                    <input type="number" name="gasto_monto" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="gasto_fecha" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <button type="submit" class="btn-pay" style="background: var(--danger); color:white;">Registrar Gasto</button>
            </form>
        </div>

    </div>

    <!-- COLUMNA DERECHA (RESUMEN EN TIEMPO REAL) -->
    <div class="summary-panel" id="summaryPanel">
        <!-- Estado Inicial Vacío -->
        <div class="preview-card" id="emptyState">
            <i class="ph-duotone ph-shopping-cart" style="font-size: 3rem; color: var(--text-muted);"></i>
            <p style="color: var(--text-muted); margin-top: 10px;">Selecciona un alumno para calcular el pago.</p>
        </div>

        <!-- Tarjeta de Resumen -->
        <div class="preview-card" id="resumenCard" style="display:none;">
            <img src="" id="res_foto" class="preview-avatar" onerror="this.src='/MOKUSO/assets/img/uploads/default.png'">
            <h3 id="res_nombre" class="preview-name">Nombre Alumno</h3>
            <span id="res_status" class="preview-badge">--</span>

            <div class="bill-details">
                <div class="bill-row">
                    <span>Vence Actualmente:</span>
                    <span id="res_vencimiento" style="color:var(--text);">--</span>
                </div>
                
                <!-- Alerta Visual de Próximo Vencimiento -->
                <div id="alertVencimiento" class="warning-alert">
                    <i class="ph-fill ph-warning"></i>
                    <span>¡La membresía está por vencer en menos de 3 días!</span>
                </div>

                <div class="bill-row" style="margin-top:10px;">
                    <span>Nuevo Vencimiento:</span>
                    <span id="res_nuevo_vencimiento" class="highlight">--</span>
                </div>
                <div class="bill-row total">
                    <span>Total a Pagar:</span>
                    <span id="res_total">$0.00</span>
                </div>
            </div>

            <button type="button" onclick="confirmarPago()" class="btn-pay">
                <i class="ph-bold ph-check-circle"></i> Confirmar Pago
            </button>
        </div>
    </div>

</div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // 1. INICIALIZACIÓN
    $(document).ready(function() {
        $('#alumno_id').select2({ placeholder: "Buscar alumno...", allowClear: true });
        
        // Mostrar alerta si existe mensaje PHP
        <?php if ($flash): ?>
            Swal.fire({
                icon: '<?php echo $flash['type']; ?>',
                title: '<?php echo ($flash['type'] == 'success') ? '¡Excelente!' : 'Atención'; ?>',
                html: '<?php echo $flash['message']; ?>',
                background: '#202024', color: '#e1e1e6', confirmButtonColor: '#ff6600'
            });
        <?php endif; ?>
    });

    // Cambio de Tabs
    function switchTab(tabName) {
        document.querySelectorAll('.form-section').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        
        document.getElementById('form-'+tabName).classList.add('active');
        event.currentTarget.classList.add('active');

        const panel = document.getElementById('summaryPanel');
        if(tabName === 'mensualidad') {
            panel.style.opacity = '1'; panel.style.pointerEvents = 'all';
        } else {
            panel.style.opacity = '0.3'; panel.style.pointerEvents = 'none';
        }
    }

    // 2. CÁLCULO DE FECHAS Y ALERTAS
    let currentVence = null;

    $('#alumno_id').on('select2:select', function (e) {
        const data = e.params.data.element.dataset;
        const foto = data.foto ? `/MOKUSO/assets/img/uploads/${data.foto}` : '/MOKUSO/assets/img/uploads/default.png';
        
        // Mostrar tarjeta
        $('#emptyState').hide();
        $('#resumenCard').fadeIn();
        $('#res_nombre').text(e.params.data.text);
        $('#res_foto').attr('src', foto);
        
        // Badge de estado
        const statusSpan = $('#res_status');
        statusSpan.text(data.status.toUpperCase());
        statusSpan.removeClass().addClass('preview-badge status-' + data.status);

        // Fecha Actual y Alertas
        currentVence = data.vence;
        const alertBox = $('#alertVencimiento');
        
        if(!currentVence || currentVence.startsWith('0000')) {
            $('#res_vencimiento').text('Sin registro / Vencido');
            currentVence = null; 
            alertBox.hide();
        } else {
            const dateObj = new Date(currentVence + 'T00:00:00');
            $('#res_vencimiento').text(dateObj.toLocaleDateString('es-ES', {day:'numeric', month:'short', year:'numeric'}));
            
            // Lógica de alerta (3 días)
            const hoy = new Date();
            const diffTime = dateObj - hoy;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
            
            // Mostrar alerta si faltan 3 días o menos y la fecha es futura
            if (diffDays >= 0 && diffDays <= 3) {
                alertBox.fadeIn();
            } else {
                alertBox.hide();
            }
        }

        updateTotals();
    });

    function selectMonth(num, btn) {
        document.querySelectorAll('.month-option').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        $('#meses_input').val(num);
        updateTotals();
    }

    function updateTotals() {
        const meses = parseInt($('#meses_input').val());
        const option = $('#tipo_pago_id').find(':selected');
        const montoBase = parseFloat(option.data('monto')) || 0;
        
        // Total Monetario
        const total = montoBase * meses;
        $('#res_total').text('$' + total.toLocaleString('es-MX', {minimumFractionDigits: 2}));

        // Cálculo Simulado de Nueva Fecha (Ciclo Calendario)
        const today = new Date();
        let baseDate = new Date(); // Por defecto hoy

        let currentExpiry = null;
        if(currentVence) {
            // Parseo manual seguro YYYY-MM-DD
            const parts = currentVence.split('-');
            currentExpiry = new Date(parts[0], parts[1] - 1, parts[2]); 
        }

        // Si tiene vencimiento futuro -> Base es mes siguiente a ese vencimiento
        if (currentExpiry && currentExpiry >= today) {
            baseDate = new Date(currentExpiry);
            baseDate.setDate(1); // Forzar dia 1
            baseDate.setMonth(baseDate.getMonth() + 1); // Siguiente mes
        } else {
            // Vencido o nuevo: Base es este mes
            baseDate = new Date(); 
            baseDate.setDate(1);
        }

        // Sumar meses (menos 1 porque el mes base cuenta como el primero)
        baseDate.setMonth(baseDate.getMonth() + (meses - 1));
        
        // Calcular último día de ese mes resultante
        const year = baseDate.getFullYear();
        const month = baseDate.getMonth();
        const lastDay = new Date(year, month + 1, 0); // Día 0 del mes sig es el ultimo de este

        // Formatear fecha final
        const options = { day: 'numeric', month: 'long', year: 'numeric' };
        $('#res_nuevo_vencimiento').text("Fin de " + lastDay.toLocaleDateString('es-ES', {month:'long', year:'numeric'}));
    }

    function confirmarPago() {
        const alumno = $('#alumno_id').val();
        const pago = $('#tipo_pago_id').val();
        
        if(!alumno || !pago) {
            Swal.fire({icon: 'warning', title: 'Faltan datos', text: 'Selecciona alumno y concepto', background:'#202024', color:'#fff'});
            return;
        }
        document.getElementById('formPay').submit();
    }
</script>

</body>
</html>




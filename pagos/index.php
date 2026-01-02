<?php
include '../templates/header.php';
include '../config/db.php';

// --- CONFIGURACIÓN DE FECHAS ---
// Detectar idioma para mostrar fechas en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_MX.UTF-8', 'es_ES', 'Spanish_Spain');

if (isset($_GET['mes']) && !empty($_GET['mes'])) {
    $fecha_filtro = $_GET['mes'] . '-01';
    $mes_actual = date('m', strtotime($fecha_filtro));
    $ano_actual = date('Y', strtotime($fecha_filtro));
} else {
    $fecha_filtro = date('Y-m-01');
    $mes_actual = date('m');
    $ano_actual = date('Y');
}

// Calcular mes anterior para comparativas (Tendencias)
$fecha_anterior = date('Y-m-d', strtotime('-1 month', strtotime($fecha_filtro)));
$mes_anterior = date('m', strtotime($fecha_anterior));
$ano_anterior = date('Y', strtotime($fecha_anterior));

// --- FUNCIONES AUXILIARES ---
function obtenerTotal($conn, $tipo, $mes, $ano) {
    // Ajuste en la consulta para manejar tipos múltiples si es ingreso
    $filtro_tipo = ($tipo == 'ingreso') ? "(tipo = 'ingreso_mensualidad' OR tipo = 'ingreso_otro')" : "tipo = 'gasto'";
    
    $sql = "SELECT SUM(monto) as total FROM movimientos WHERE $filtro_tipo AND MONTH(fecha) = ? AND YEAR(fecha) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $mes, $ano);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    // CORRECCIÓN AQUÍ: Usamos ternario clásico en lugar de ??
    return isset($res['total']) ? $res['total'] : 0;
}

function calcularTendencia($actual, $anterior) {
    if ($anterior == 0) return ($actual > 0) ? 100 : 0;
    return number_format((($actual - $anterior) / $anterior) * 100, 1);
}

// --- DATOS DEL MES ACTUAL ---
$ingresos_mes = obtenerTotal($conn, 'ingreso', $mes_actual, $ano_actual);
$gastos_mes   = obtenerTotal($conn, 'gasto', $mes_actual, $ano_actual);
$balance_mes  = $ingresos_mes - $gastos_mes;

// --- DATOS DEL MES ANTERIOR (Para tendencias) ---
$ingresos_ant = obtenerTotal($conn, 'ingreso', $mes_anterior, $ano_anterior);
$gastos_ant   = obtenerTotal($conn, 'gasto', $mes_anterior, $ano_anterior);

// --- CÁLCULO DE TENDENCIAS ---
$tendencia_ingresos = calcularTendencia($ingresos_mes, $ingresos_ant);
$tendencia_gastos   = calcularTendencia($gastos_mes, $gastos_ant);

// --- KPI: ALUMNOS PAGADOS ---

$sql_pagados = "SELECT COUNT(DISTINCT alumno_id) as total FROM movimientos WHERE tipo = 'ingreso_mensualidad' AND MONTH(fecha) = ? AND YEAR(fecha) = ?";
$stmt_pagados = $conn->prepare($sql_pagados);
$stmt_pagados->bind_param("ii", $mes_actual, $ano_actual);
$stmt_pagados->execute();
$res_pagados = $stmt_pagados->get_result()->fetch_assoc();

// CORRECCIÓN AQUÍ:
$alumnos_pagados = isset($res_pagados['total']) ? $res_pagados['total'] : 0;

// --- TOP GASTOS ---
$sql_top = "SELECT cg.nombre, SUM(m.monto) as total 
            FROM movimientos m 
            JOIN categorias_gastos cg ON m.categoria_gasto_id = cg.id 
            WHERE m.tipo = 'gasto' AND MONTH(m.fecha) = ? AND YEAR(m.fecha) = ?
            GROUP BY m.categoria_gasto_id 
            ORDER BY total DESC LIMIT 5";
$stmt_top = $conn->prepare($sql_top);
$stmt_top->bind_param("ii", $mes_actual, $ano_actual);
$stmt_top->execute();
$top_gastos = $stmt_top->get_result();

// --- ÚLTIMOS MOVIMIENTOS ---
$ultimos = $conn->query("SELECT fecha, descripcion, monto, tipo FROM movimientos ORDER BY fecha DESC, id DESC LIMIT 6");

?>

<style>
    /* VARIABLES DE TEMA (Asegurando consistencia si header.php cambia) */
    :root {
        --primary: #ff6600; /* Naranja Dojo */
        --primary-glow: rgba(255, 102, 0, 0.4);
        --bg-dark: #121214;
        --bg-card: #1c1c1e;
        --text-main: #e1e1e6;
        --text-muted: #a8a8b3;
        --success: #04d361;
        --danger: #ff3e3e;
        --border: #29292e;
        --radius: 12px;
    }

    body { background-color: var(--bg-dark); color: var(--text-main); font-family: 'Inter', sans-serif; }

    /* LAYOUT GENERAL */
    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    /* HEADER & FILTROS */
    .dash-header {
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin-bottom: 30px;
    }

    @media (min-width: 768px) {
        .dash-header {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }

    .title-area h1 { margin: 0; font-size: 1.8rem; font-weight: 700; color: var(--text-main); }
    .title-area p { margin: 5px 0 0; color: var(--text-muted); font-size: 0.9rem; }

    .actions-area {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .date-filter {
        background: var(--bg-card);
        border: 1px solid var(--border);
        color: var(--text-main);
        padding: 10px 15px;
        border-radius: 8px;
        outline: none;
        cursor: pointer;
    }
    
    .btn-action {
        background: linear-gradient(135deg, var(--primary), #e65100);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px var(--primary-glow);
    }

    /* KPI GRID (Tarjetas Superiores) */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .kpi-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
        position: relative;
        overflow: hidden;
        transition: border-color 0.3s;
    }

    .kpi-card:hover { border-color: var(--primary); }

    .kpi-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
    .kpi-title { color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .kpi-icon { background: rgba(255,255,255,0.05); padding: 8px; border-radius: 8px; font-size: 1.2rem; }
    
    .kpi-value { font-size: 2rem; font-weight: 700; color: var(--text-main); margin-bottom: 5px; }
    
    .trend-badge {
        font-size: 0.8rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        border-radius: 4px;
        background: rgba(255,255,255,0.05);
    }
    .trend-up { color: var(--success); background: rgba(4, 211, 97, 0.1); }
    .trend-down { color: var(--danger); background: rgba(255, 62, 62, 0.1); }
    .trend-neutral { color: var(--text-muted); }

    /* CONTENIDO PRINCIPAL (Gráfico y Listas) */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }

    @media (min-width: 1024px) {
        .content-grid { grid-template-columns: 2fr 1fr; }
    }

    .widget {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
        display: flex;
        flex-direction: column;
    }

    .widget-title { font-size: 1.1rem; margin: 0 0 20px 0; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
    .widget-link { color: var(--primary); font-size: 0.9rem; text-decoration: none; }

    /* LISTAS */
    .transaction-list, .expense-list { list-style: none; padding: 0; margin: 0; }
    
    .list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--border);
    }
    .list-item:last-child { border-bottom: none; }

    .item-info { display: flex; flex-direction: column; }
    .item-main { font-weight: 500; color: var(--text-main); }
    .item-sub { font-size: 0.8rem; color: var(--text-muted); }

    .amount { font-weight: 600; font-family: 'Roboto Mono', monospace; }
    .amount.pos { color: var(--success); }
    .amount.neg { color: var(--danger); }

    /* GRÁFICO */
    .chart-wrapper { position: relative; height: 300px; width: 100%; }

</style>

<div class="dashboard-container">
    
    <header class="dash-header">
        <div class="title-area">
            <h1>Panel Financiero</h1>
            <p>Resumen de <?php echo strftime('%B %Y', strtotime($ano_actual.'-'.$mes_actual.'-01')); ?></p>
        </div>
        <form action="" method="GET" class="actions-area">
            <input type="month" name="mes" class="date-filter" 
                   value="<?php echo $ano_actual.'-'.$mes_actual; ?>" 
                   onchange="this.form.submit()">
            
            <a href="registrar_pago.php" class="btn-action">
                <i class="fas fa-plus"></i> Nuevo Movimiento
            </a>
        </form>
    </header>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Ingresos</span>
                <i class="fas fa-wallet kpi-icon" style="color: var(--success)"></i>
            </div>
            <div class="kpi-value">$<?php echo number_format($ingresos_mes, 2); ?></div>
            <div class="trend-badge <?php echo ($tendencia_ingresos >= 0) ? 'trend-up' : 'trend-down'; ?>">
                <i class="fas fa-arrow-<?php echo ($tendencia_ingresos >= 0) ? 'up' : 'down'; ?>"></i>
                <?php echo abs($tendencia_ingresos); ?>% vs mes anterior
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Gastos</span>
                <i class="fas fa-shopping-cart kpi-icon" style="color: var(--danger)"></i>
            </div>
            <div class="kpi-value">$<?php echo number_format($gastos_mes, 2); ?></div>
            <div class="trend-badge <?php echo ($tendencia_gastos <= 0) ? 'trend-up' : 'trend-down'; ?>">
                <i class="fas fa-arrow-<?php echo ($tendencia_gastos >= 0) ? 'up' : 'down'; ?>"></i>
                <?php echo abs($tendencia_gastos); ?>% vs mes anterior
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Balance Neto</span>
                <i class="fas fa-scale-balanced kpi-icon" style="color: var(--primary)"></i>
            </div>
            <div class="kpi-value" style="color: <?php echo ($balance_mes >= 0) ? 'var(--success)' : 'var(--danger)'; ?>">
                $<?php echo number_format($balance_mes, 2); ?>
            </div>
            <span class="item-sub">Disponible en caja</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Pagos Recibidos</span>
                <i class="fas fa-users kpi-icon" style="color: #4da6ff)"></i>
            </div>
            <div class="kpi-value"><?php echo $alumnos_pagados; ?></div>
            <span class="item-sub">Alumnos al corriente este mes</span>
        </div>
    </div>

    <div class="content-grid">
        
        <div class="main-column" style="display:flex; flex-direction:column; gap:20px;">
            
            <div class="widget">
                <div class="widget-title">Resumen Visual</div>
                <div class="chart-wrapper">
                    <canvas id="financeChart"></canvas>
                </div>
            </div>

            <div class="widget">
                <div class="widget-title">
                    Movimientos Recientes
                    <a href="historial.php" class="widget-link">Ver todo</a>
                </div>
                <ul class="transaction-list">
                    <?php while($row = $ultimos->fetch_assoc()): 
                        $es_gasto = (strpos($row['tipo'], 'gasto') !== false);
                    ?>
                    <li class="list-item">
                        <div class="item-info">
                            <span class="item-main"><?php echo htmlspecialchars($row['descripcion']); ?></span>
                            <span class="item-sub"><?php echo date("d M, Y", strtotime($row['fecha'])); ?></span>
                        </div>
                        <span class="amount <?php echo $es_gasto ? 'neg' : 'pos'; ?>">
                            <?php echo $es_gasto ? '-' : '+'; ?> $<?php echo number_format($row['monto'], 2); ?>
                        </span>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>

        <div class="side-column">
            <div class="widget">
                <div class="widget-title">Top Gastos del Mes</div>
                <ul class="expense-list">
                    <?php if ($top_gastos->num_rows > 0): ?>
                        <?php while($gasto = $top_gastos->fetch_assoc()): 
                            // Calcular porcentaje del gasto total para una barra visual
                         // Cambiamos a $gastos_mes para que coincida con la variable de arriba
                            $porcentaje = ($gastos_mes > 0) ? ($gasto['total'] / $gastos_mes) * 100 : 0;
                        ?>
                        <li class="list-item" style="display:block;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                <span class="item-main"><?php echo htmlspecialchars($gasto['nombre']); ?></span>
                                <span class="amount neg">$<?php echo number_format($gasto['total'], 2); ?></span>
                            </div>
                            <div style="width:100%; height:6px; background:var(--border); border-radius:3px; overflow:hidden;">
                                <div style="width:<?php echo $porcentaje; ?>%; height:100%; background:var(--danger);"></div>
                            </div>
                        </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="list-item"><span class="item-sub">Sin gastos registrados</span></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="widget" style="margin-top: 20px; background: linear-gradient(135deg, #1c1c1e 0%, #252529 100%);">
                <div class="widget-title">Estado de Membresías</div>
                <div style="text-align:center; padding: 10px;">
                   <i class="fas fa-info-circle" style="font-size: 2rem; color: var(--text-muted); margin-bottom: 10px;"></i>
                   <p class="item-sub">Recuerda revisar los alumnos con pagos vencidos en la sección de Alumnos.</p>
                   <a href="../alumnos/" class="btn-action" style="font-size:0.8rem; background: var(--border);">Ir a Alumnos</a>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('financeChart').getContext('2d');
    
    // Gradiente para Ingresos
    let gradientIngreso = ctx.createLinearGradient(0, 0, 0, 400);
    gradientIngreso.addColorStop(0, 'rgba(4, 211, 97, 0.5)'); // Verde
    gradientIngreso.addColorStop(1, 'rgba(4, 211, 97, 0.0)');

    // Gradiente para Gastos
    let gradientGasto = ctx.createLinearGradient(0, 0, 0, 400);
    gradientGasto.addColorStop(0, 'rgba(255, 62, 62, 0.5)'); // Rojo
    gradientGasto.addColorStop(1, 'rgba(255, 62, 62, 0.0)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Ingresos', 'Gastos'],
            datasets: [{
                label: 'Monto Total',
                data: [<?php echo $ingresos_mes; ?>, <?php echo $gastos_mes; ?>],
                backgroundColor: [
                    gradientIngreso,
                    gradientGasto
                ],
                borderColor: [
                    '#04d361',
                    '#ff3e3e'
                ],
                borderWidth: 2,
                borderRadius: 10,
                borderSkipped: false,
                barThickness: 60
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1c1c1e',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#29292e',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        label: function(context) {
                            return '$ ' + context.parsed.y.toLocaleString('es-MX', {minimumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#29292e' },
                    ticks: { color: '#a8a8b3' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#e1e1e6', font: {size: 14, weight: 'bold'} }
                }
            }
        }
    });
</script>

<?php 
$conn->close();
include '../templates/footer.php'; 
?>
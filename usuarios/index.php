<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'maestro') {
    header("Location: /MOKUSO/index.php");
    exit();
}
include '../config/db.php';

// --- ESTADÍSTICAS ---
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_maestros = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'maestro'")->fetch_assoc()['c'];
$total_alumnos = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'alumno'")->fetch_assoc()['c'];
$total_padres = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'padre'")->fetch_assoc()['c'];

// Últimos registrados
$ultimos = $conn->query("SELECT username, role FROM users ORDER BY id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Mokuso Elite</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* --- TEMA ELITE --- */
        :root {
            --primary: #ff6600; --bg-main: #121214; --surface: #202024; --border: #323238;
            --text-white: #e1e1e6; --text-gray: #a8a8b3; --radius: 16px;
        }
        body { background-color: var(--bg-main); color: var(--text-white); font-family: 'Poppins', sans-serif; margin: 0; min-height: 100vh; }
        
        .main-container { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        
        /* HEADER */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
        .page-title { font-family: 'Orbitron', sans-serif; font-size: 2rem; color: var(--text-white); margin: 0; display: flex; align-items: center; gap: 12px; }
        
        .btn-ghost { color: var(--text-gray); text-decoration: none; border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; transition: 0.3s; }
        .btn-ghost:hover { border-color: var(--primary); color: var(--text-white); }

        /* KPI GRID */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .kpi-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; }
        .kpi-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(255, 102, 0, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
        .kpi-info h3 { margin: 0; font-size: 2rem; font-weight: 700; font-family: 'Orbitron'; }
        .kpi-info p { margin: 0; color: var(--text-gray); font-size: 0.9rem; text-transform: uppercase; }

        /* SECCIONES */
        .content-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem; }
        @media(max-width: 900px) { .content-grid { grid-template-columns: 1fr; } }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 2rem; height: 100%; }
        .card h3 { font-family: 'Orbitron'; margin-top: 0; margin-bottom: 1.5rem; color: var(--text-white); }

        .user-list li { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); color: var(--text-gray); }
        .user-list li:last-child { border-bottom: none; }
        .role-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: rgba(255,255,255,0.1); }

        /* ACTION BUTTON */
        .btn-main {
            display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%;
            padding: 1.5rem; background: linear-gradient(135deg, var(--primary), #cc5200);
            color: white; font-weight: 700; text-transform: uppercase; border: none; border-radius: var(--radius);
            font-size: 1.2rem; text-decoration: none; transition: transform 0.2s; margin-top: 2rem;
            box-shadow: 0 10px 30px rgba(255, 102, 0, 0.2);
        }
        .btn-main:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(255, 102, 0, 0.4); color: white; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="page-header">
        <div class="page-title"><i class="ph-fill ph-users-three"></i> Usuarios</div>
        <a href="/MOKUSO/dashboard/index.php" class="btn-ghost"><i class="ph-bold ph-arrow-left"></i> Volver</a>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="ph-bold ph-user-gear"></i></div>
            <div class="kpi-info"><h3><?php echo $total_users; ?></h3><p>Total Usuarios</p></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color:#00bfff; background:rgba(0,191,255,0.1)"><i class="ph-bold ph-chalkboard-teacher"></i></div>
            <div class="kpi-info"><h3><?php echo $total_maestros; ?></h3><p>Maestros</p></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color:#04d361; background:rgba(4,211,97,0.1)"><i class="ph-bold ph-student"></i></div>
            <div class="kpi-info"><h3><?php echo $total_alumnos; ?></h3><p>Alumnos</p></div>
        </div>
         <div class="kpi-card">
            <div class="kpi-icon" style="color:#d946ef; background:rgba(217,70,239,0.1)"><i class="ph-bold ph-baby"></i></div>
            <div class="kpi-info"><h3><?php echo $total_padres; ?></h3><p>Padres</p></div>
        </div>
    </div>

    <!-- CONTENIDO -->
    <div class="content-grid">
        <div class="card">
            <h3><i class="ph-bold ph-chart-pie-slice"></i> Distribución</h3>
            <div style="height: 250px; position: relative;">
                <canvas id="usersChart"></canvas>
            </div>
            <a href="lista.php" class="btn-main">
                <i class="ph-bold ph-gear"></i> Gestionar Usuarios
            </a>
        </div>

        <div class="card">
            <h3><i class="ph-bold ph-clock-counter-clockwise"></i> Recientes</h3>
            <ul class="user-list">
                <?php while($u = $ultimos->fetch_assoc()): ?>
                <li>
                    <span><i class="ph-fill ph-user-circle me-2"></i> <?php echo htmlspecialchars($u['username']); ?></span>
                    <span class="role-badge"><?php echo strtoupper($u['role']); ?></span>
                </li>
                <?php endwhile; ?>
            </ul>
            <p style="color:var(--text-gray); font-size:0.8rem; margin-top:1rem; text-align:center;">
                Mostrando los últimos 5 registros.
            </p>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('usersChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Maestros', 'Alumnos', 'Padres'],
            datasets: [{
                data: [<?php echo $total_maestros; ?>, <?php echo $total_alumnos; ?>, <?php echo $total_padres; ?>],
                backgroundColor: ['#00bfff', '#04d361', '#d946ef'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { color: '#a8a8b3', padding: 20 } } },
            cutout: '75%'
        }
    });
</script>

</body>
</html>
<?php $conn->close(); ?>
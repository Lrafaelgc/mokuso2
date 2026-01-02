<?php 
include '../templates/header.php'; 
// Obtenemos el ID del alumno de la URL
$alumno_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($alumno_id <= 0) {
    echo "<div class='container'><h1>Error: ID de alumno no válido.</h1></div>";
    include '../templates/footer.php';
    exit();
}
?>
 
<div class="container">
    <div class="attendance-header">
        <h1>Centro de Asistencias</h1>
        <h2 id="student-name-title">Cargando...</h2>
    </div>

    <div class="calendar-container">
        <div class="calendar-nav">
            <button id="prev-month" class="btn-secondary">&lt; Mes Anterior</button>
            <span id="current-month-year" class="month-display"></span>
            <button id="next-month" class="btn-secondary">Mes Siguiente &gt;</button>
        </div>
        <div class="calendar-grid-header">
            <div>Dom</div><div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div>
        </div>
        <div id="calendar-grid" class="calendar-grid">
            </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const studentId = <?php echo $alumno_id; ?>;
    let currentDate = new Date();

    const studentNameTitle = document.getElementById('student-name-title');
    const monthDisplay = document.getElementById('current-month-year');
    const calendarGrid = document.getElementById('calendar-grid');
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');

    async function renderCalendar(date) {
        const year = date.getFullYear();
        const month = date.getMonth(); // 0-11

        // Actualizar UI
        calendarGrid.innerHTML = '<div class="loader">Cargando asistencias...</div>';
        monthDisplay.textContent = date.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });

        try {
            // Obtener datos de la API
            const response = await fetch(`/MOKUSO/asistencias/api_get_asistencias_mes.php?id=${studentId}&month=${month + 1}&year=${year}`);
            const data = await response.json();
            if (data.error) throw new Error(data.error);

            studentNameTitle.textContent = data.nombre_alumno;
            const attendedDays = new Set(data.dias_asistidos);

            // Lógica para generar el calendario
            calendarGrid.innerHTML = ''; // Limpiar loader
            const firstDayOfMonth = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            // Rellenar días vacíos al inicio
            for (let i = 0; i < firstDayOfMonth; i++) {
                calendarGrid.insertAdjacentHTML('beforeend', '<div class="calendar-day empty"></div>');
            }

            // Rellenar los días del mes
            for (let day = 1; day <= daysInMonth; day++) {
                const dayDiv = document.createElement('div');
                dayDiv.className = 'calendar-day';
                dayDiv.textContent = day;
                if (attendedDays.has(day)) {
                    dayDiv.classList.add('presente');
                    dayDiv.title = 'Asistió';
                }
                calendarGrid.appendChild(dayDiv);
            }
        } catch (error) {
            calendarGrid.innerHTML = `<p style="color: #dc3545;">Error al cargar las asistencias: ${error.message}</p>`;
        }
    }

    prevMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar(currentDate);
    });

    nextMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar(currentDate);
    });

    renderCalendar(currentDate);
});
</script>

<?php include '../templates/footer.php'; ?>
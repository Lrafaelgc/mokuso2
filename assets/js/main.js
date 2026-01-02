/**
 * =================================================================
 * ARCHIVO JAVASCRIPT PRINCIPAL PARA LA APLICACIÓN MOKUSO MANAGER
 * =================================================================
 * Este archivo contiene la lógica de notificaciones, navegación, 
 * filtrado y la gestión de la tarjeta modal de perfil de alumno.
 */

// ===============================================
// ====== SISTEMA DE NOTIFICACIONES (SweetAlert2) ======
// ===============================================
function showAlert(title, text, icon) {
    Swal.fire({
        title: title,
        text: text,
        icon: icon,
        confirmButtonText: 'Entendido',
        timer: 2500,
        timerProgressBar: true,
        background: '#202336',
        color: '#e0e0e0',
        confirmButtonColor: '#e94560'
    });
}

// ===============================================
// ====== LÓGICA PRINCIPAL DE LA APLICACIÓN ======
// ===============================================
document.addEventListener('DOMContentLoaded', function () {

    // ----------------------------------------------------
    // --- Lógica para el Menú Lateral (Sidebar) ---
    // ----------------------------------------------------
    const currentPage = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    
    if (currentPage.includes('/dashboard/')) {
        document.querySelector('#navDashboard')?.classList.add('active');
    } else if (currentPage.includes('/alumnos/') || currentPage.includes('/asistencias/')) {
        document.querySelector('#navAlumnos')?.classList.add('active');
    } else if (currentPage.includes('/pagos/')) {
        document.querySelector('#navPagos')?.classList.add('active');
    } else if (currentPage.includes('/logros/')) {
        document.querySelector('#navLogros')?.classList.add('active');
    } else {
        document.querySelector('#navAlumnos')?.classList.add('active');
    }

    // ----------------------------------------------------
    // --- Lógica de Filtros para la Página de Alumnos ---
    // ----------------------------------------------------
    const alumnosPageContainer = document.getElementById('alumnosListContainer');
    if (alumnosPageContainer) {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const groupFilter = document.getElementById('groupFilter');
        const alumnoItems = alumnosPageContainer.querySelectorAll('.alumno-item');

        function filterAlumnos() {
            const searchText = searchInput.value.toLowerCase();
            const selectedStatus = statusFilter.value;
            const selectedGroup = groupFilter.value;

            alumnoItems.forEach(item => {
                const nombre = item.dataset.nombre;
                const status = item.dataset.status;
                const grupoId = item.dataset.grupoId;

                const matchesSearch = nombre.includes(searchText);
                const matchesStatus = selectedStatus === 'all' || status === selectedStatus;
                const matchesGroup = selectedGroup === 'all' || grupoId === selectedGroup;

                if (matchesSearch && matchesStatus && matchesGroup) {
                    item.style.display = ''; 
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        searchInput?.addEventListener('input', filterAlumnos);
        statusFilter?.addEventListener('change', filterAlumnos);
        groupFilter?.addEventListener('change', filterAlumnos);
    }

    // ----------------------------------------------------
    // --- Lógica del Modal de Perfil de Alumno ---
    // ----------------------------------------------------
    const modal = document.getElementById('alumno-card-modal');
    if (modal) {
        const cardBody = document.getElementById('card-body');
        const hideModal = () => modal.classList.remove('show');

        // A. Abrir Tarjeta Modal y Cargar Datos
        document.querySelectorAll('.alumno-item').forEach(item => {
            item.addEventListener('click', async function (event) {
                if (event.target.closest('.btn-asistencia')) return;
                
                const alumnoId = this.dataset.id;
                cardBody.innerHTML = `<div class="loader"></div>`;
                modal.classList.add('show');

                try {
                    const response = await fetch(`/MOKUSO/alumnos/api_get_alumno.php?id=${alumnoId}`);
                    if (!response.ok) throw new Error('No se pudo conectar con el servidor.');
                    
                    const data = await response.json();
                    if (data.error) throw new Error(data.message);

                    const detalles = data.detalles;
                    const porcentajeAsistencia = data.asistencias.porcentaje;
                    const ultimaAsistencia = data.asistencias.ultima_asistencia ? new Date(data.asistencias.ultima_asistencia).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' }) : 'N/A';
                    const fechaRegistro = detalles.fecha_registro ? new Date(detalles.fecha_registro).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' }) : 'N/A';
                    const tiempoMiembroCalculado = data.tiempo_miembro_str || 'N/A';
                    const tiempoInactivo = data.tiempo_inactivo_str || null;

                    let tiempoMiembroTexto = tiempoMiembroCalculado;
                    if (tiempoInactivo) {
                        tiempoMiembroTexto = `${tiempoMiembroCalculado} ACTIVO(S)<br><span class="text-danger">INACTIVO desde: ${tiempoInactivo}</span>`;
                    }

                    const logrosHTML = data.logros.length > 0 ? 
                        '<ul>' + data.logros.map(l => `<li><strong>${l.logro}</strong> - ${new Date(l.fecha_logro).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' })}</li>`).join('') + '</ul>' : 
                        '<p>No hay logros registrados.</p>';
                    
                    // --- CONSTRUCCIÓN DE LA TARJETA ---
                    cardBody.innerHTML = `
                        <div class="creative-card">
                            ${detalles.estado_membresia.toLowerCase() === 'inactiva' ? '<div class="card-status-banner">Inactivo</div>' : ''}
                            <button class="card-close-btn">&times;</button>
                            
                            <div class="card-top">
                                <div class="profile-img-wrapper">
                                    <img src="/MOKUSO/assets/img/uploads/${detalles.foto_perfil}" alt="Foto" class="profile-avatar">
                                </div>
                                <h3>${detalles.nombre} ${detalles.apellidos}</h3>
                                <p class="level">${detalles.nivel_nombre || 'Nivel no asignado'}</p>
                                <p class="discipline">${detalles.disciplina_nombre || 'Disciplina no asignada'}</p>
                            </div>

                            <div class="card-body-content">
                                <div class="card-grid">
                                    <div class="stat-item">
                                        <i class="fas fa-child icon"></i><span>Edad</span>
                                        <p>${detalles.edad || 'N/A'} años</p>
                                    </div>
                                    <div class="stat-item">
                                        <i class="fas fa-phone-alt icon"></i><span>Emergencia</span>
                                        <p>${detalles.telefono_emergencia || 'N/A'}</p>
                                    </div>
                                    <div class="stat-item">
                                        <i class="fas fa-ruler-vertical icon"></i><span>Estatura</span>
                                        <p>${detalles.estatura || 'N/A'} m</p>
                                    </div>
                                    <div class="stat-item">
                                        <i class="fas fa-weight-hanging icon"></i><span>Peso</span>
                                        <p>${detalles.peso || 'N/A'} kg</p>
                                    </div>
                                    <div class="stat-item">
                                        <i class="fas fa-calendar-check icon"></i><span>Asistencia</span>
                                        <p>${porcentajeAsistencia}%</p>
                                    </div>
                                    <div class="stat-item">
                                        <i class="fas fa-clock icon"></i><span>Última Clase</span>
                                        <p>${ultimaAsistencia}</p>
                                    </div>
                                </div>
                                
                                <div class="date-section">
                                    <div class="date-item">
                                        <i class="fas fa-calendar-alt icon"></i><span>Fecha de Registro</span>
                                        <p>${fechaRegistro}</p>
                                    </div>
                                    <div class="date-item">
                                        <i class="fas fa-hourglass-half icon"></i><span>Tiempo de Membresía</span>
                                        <p>${tiempoMiembroTexto}</p>
                                    </div>
                                </div>

                                <div class="card-logros">
                                    <div class="logros-header">
                                        <span>Logros</span>
                                        <i class="fas fa-chevron-down toggle-icon"></i> 
                                    </div>
                                    <div class="logros-content hidden">
                                        ${logrosHTML}
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <a href="/MOKUSO/asistencias/detalle_asistencia.php?id=${detalles.id}" class="btn-secondary">Historial</a>
                                    <button class="btn-success" id="marcarAsistenciaModalBtn" data-alumno-id="${detalles.id}">Marcar Asistencia</button>
                                    <a href="/MOKUSO/alumnos/editar_alumno.php?id=${detalles.id}" class="btn-primary">Editar</a>
                                    <a href="/MOKUSO/pagos/registrar_pago.php?alumno_id=${detalles.id}" class="btn-payment">Pago</a>
                                </div>
                            </div>
                        </div>`;
                        
                    // --- Lógica de Eventos para la Tarjeta recién creada ---
                    
                    // 1. Botón de cerrar
                    cardBody.querySelector('.card-close-btn')?.addEventListener('click', hideModal);
                    
                    // 2. Acordeón de logros
                    const logrosHeader = cardBody.querySelector('.logros-header');
                    if(logrosHeader) {
                        logrosHeader.addEventListener('click', () => {
                            logrosHeader.nextElementSibling.classList.toggle('hidden');
                            logrosHeader.querySelector('.toggle-icon').classList.toggle('fa-chevron-up');
                        });
                    }

                    // 3. --- NUEVO: Lógica para el botón "Marcar Asistencia" dentro del modal ---
                    const marcarAsistenciaBtn = cardBody.querySelector('#marcarAsistenciaModalBtn');
                    if (marcarAsistenciaBtn) {
                        marcarAsistenciaBtn.addEventListener('click', async function() {
                            const alumnoId = this.dataset.alumnoId;
                            this.disabled = true; // Desactivar botón
                            this.textContent = 'Registrando...';

                            try {
                                const response = await fetch('/MOKUSO/asistencias/registrar_asistencia.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `alumno_id=${alumnoId}`
                                });
                                const data = await response.json();
                                if (!data.success) throw new Error(data.error);
                                
                                showAlert('¡Éxito!', 'Asistencia registrada correctamente.', 'success');
                                this.textContent = '¡Registrada!';
                                this.style.backgroundColor = 'var(--color-success-dark)';
                                // Opcional: Recargar los datos del modal para ver la asistencia actualizada
                                // hideModal(); // Cierra el modal para que al reabrir se refresque

                            } catch (error) {
                                showAlert('Atención', error.message, 'warning');
                                this.textContent = 'Marcar Asistencia'; // Restaurar texto del botón en caso de error
                                this.disabled = false; // Reactivar botón
                            }
                        });
                    }

                } catch (error) {
                    showAlert('Error', error.message, 'error');
                    hideModal();
                }
            });
        });
        
        // B. Registrar asistencia rápida (botón '+' en la lista principal)
        document.querySelectorAll('.btn-asistencia').forEach(button => {
            button.addEventListener('click', async function (event) {
                event.stopPropagation();
                const alumnoId = this.closest('.alumno-item').dataset.id;
                button.disabled = true;
                try {
                    const response = await fetch('/MOKUSO/asistencias/registrar_asistencia.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `alumno_id=${alumnoId}`
                    });
                    const data = await response.json();
                    if (!data.success) throw new Error(data.error);
                    button.style.backgroundColor = 'var(--color-success)';
                    showAlert('¡Éxito!', 'Asistencia registrada correctamente.', 'success');
                } catch (error) {
                    showAlert('Atención', error.message, 'warning');
                } finally {
                    setTimeout(() => {
                        button.disabled = false;
                    }, 1000);
                }
            });
        });

        // C. Cierre del Modal al hacer clic fuera
        modal.addEventListener('click', (event) => {
            if (event.target === modal) hideModal();
        });
    }
});
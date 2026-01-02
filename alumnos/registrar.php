<?php
include '../templates/header.php';
include '../config/db.php'; // Incluimos la conexión a la BD

// --- Consultas para llenar los selects dinámicamente ---
$disciplinas_result = $conn->query("SELECT * FROM disciplinas ORDER BY nombre");
$niveles_result = $conn->query("SELECT * FROM niveles ORDER BY orden, nombre");
$grupos_result = $conn->query("SELECT * FROM grupos ORDER BY nombre");
$tipos_pago_result = $conn->query("SELECT * FROM tipos_pago ORDER BY monto");
?>

<script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>

<style>
    /* --- VARIABLES GLOBALES (Mokuso Elite Theme) - Asegúrate que coincidan con tu CSS global --- */
    :root {
        --color-primary: #8cc63f;
        --color-primary-dark: #6a9e2d;
        --color-primary-glow: rgba(140, 198, 63, 0.3);
        --color-bg-main: #0a0a0f;
        --color-surface: rgba(22, 22, 30, 0.85);
        --color-border: rgba(255, 255, 255, 0.08);
        --color-text-white: #ffffff;
        --color-text-gray: #a0a0b0;
        --shadow-heavy: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        --backdrop-blur: 20px;
        --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --border-radius: 16px;
    }

    body {
        background-color: var(--color-bg-main);
        background-image:
            radial-gradient(circle at 10% 20%, rgba(140, 198, 63, 0.05) 0%, transparent 40%),
            radial-gradient(circle at 90% 80%, rgba(41, 121, 255, 0.05) 0%, transparent 40%);
        font-family: 'Poppins', sans-serif;
        color: var(--color-text-white);
    }

    body::before {
        content: ''; position: fixed; inset: 0; z-index: -1; opacity: 0.4;
        background-image: linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                          linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
        background-size: 30px 30px; pointer-events: none;
    }

    .main-container {
        width: 100%; max-width: 950px; margin: 0 auto;
        padding: 2rem 1.5rem; position: relative; z-index: 1;
    }

    /* --- HEADER DE PÁGINA --- */
    .page-header {
        display: flex; align-items: center; gap: 1rem;
        margin-bottom: 2.5rem; padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--color-border);
    }
    .page-header h1 {
        font-family: 'Orbitron', sans-serif; color: var(--color-primary);
        font-size: 2.2rem; margin: 0; letter-spacing: 1px;
        text-shadow: 0 0 20px var(--color-primary-glow);
        display: flex; align-items: center; gap: 10px;
    }

    /* --- TARJETA PRINCIPAL DEL FORMULARIO (Elite Card) --- */
    .form-wrapper-card {
        background: var(--color-surface); border: 1px solid var(--color-border);
        border-radius: 24px; box-shadow: var(--shadow-heavy);
        backdrop-filter: blur(var(--backdrop-blur)); -webkit-backdrop-filter: blur(var(--backdrop-blur));
        padding: 3rem 2.5rem; position: relative; overflow: hidden;
    }
    /* Efecto de brillo superior */
    .form-wrapper-card::after {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
        background: linear-gradient(90deg, transparent, var(--color-primary), transparent); opacity: 0.5;
    }

    /* --- SECCIONES DEL FORMULARIO --- */
    .form-section { margin-bottom: 3rem; }
    .form-section:last-of-type { margin-bottom: 0; }
    .form-section h2 {
        font-family: 'Orbitron', sans-serif; font-size: 1.4rem;
        color: var(--color-text-white); padding-bottom: 0.8rem;
        margin: 0 0 1.5rem 0; border-bottom: 1px solid var(--color-border);
        display: flex; align-items: center; gap: 0.8rem;
    }
    .form-section h2 i { color: var(--color-primary); font-size: 1.6rem; }

    /* --- GRID Y CAMPOS (Elite Inputs) --- */
    .form-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
    }
    .form-group.full-width { grid-column: 1 / -1; }

    .form-group label {
        display: block; color: var(--color-text-gray);
        font-weight: 500; font-size: 0.9rem; margin-bottom: 0.6rem;
        margin-left: 0.5rem; /* Pequeña indentación */
    }
    .form-group input, .form-group select {
        width: 100%;
        background: rgba(0,0,0,0.3); border: 1px solid var(--color-border);
        border-radius: 12px; color: var(--color-text-white); padding: 1rem 1.2rem;
        font-size: 1rem; font-family: 'Poppins', sans-serif;
        transition: var(--transition-smooth);
    }
    .form-group input:focus, .form-group select:focus {
        outline: none; border-color: var(--color-primary);
        background: rgba(0,0,0,0.5);
        box-shadow: 0 0 20px -5px var(--color-primary-glow);
    }
    .form-group input::placeholder { color: rgba(160, 160, 176, 0.5); }

    /* Select personalizado (igual que en filtros) */
    .form-group select {
        appearance: none; -webkit-appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23a0a0b0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat; background-position: right 1.2rem center; background-size: 1.2em;
        padding-right: 3rem; cursor: pointer;
    }
    .form-group select option {
        background-color: #0a0a0f !important; color: var(--color-text-white); padding: 12px;
    }

    input[type="date"] { color-scheme: dark; }

    /* --- CARGA DE FOTO DE PERFIL (Elite Style) --- */
    .profile-pic-group {
        display: flex; flex-direction: column; align-items: center;
        gap: 1.5rem; text-align: center; padding: 1rem;
        background: rgba(0,0,0,0.2); border-radius: 20px; border: 1px dashed var(--color-border);
    }
    .profile-pic-wrapper {
        position: relative; width: 160px; height: 160px; cursor: pointer;
        border-radius: 50%; padding: 5px; /* Espacio para el borde gradiente */
        background: linear-gradient(135deg, var(--color-border), transparent);
        transition: var(--transition-smooth);
    }
    .profile-pic-wrapper:hover {
        background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
        transform: scale(1.05);
        box-shadow: 0 0 30px var(--color-primary-glow);
    }
    .profile-pic-inner { /* Contenedor interno para la imagen */
        width: 100%; height: 100%; border-radius: 50%; overflow: hidden;
        position: relative; background: var(--color-bg-main);
    }
    #profilePicPreview {
        width: 100%; height: 100%; object-fit: cover;
        transition: var(--transition-smooth);
    }
    .profile-pic-wrapper:hover #profilePicPreview { opacity: 0.5; transform: scale(1.1); }

    .upload-icon {
        position: absolute; inset: 0;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        color: var(--color-text-white); opacity: 0; transition: var(--transition-smooth);
        z-index: 2;
    }
    .profile-pic-wrapper:hover .upload-icon { opacity: 1; }
    .upload-icon i { font-size: 2.5rem; margin-bottom: 0.5rem; color: var(--color-primary); }
    .upload-icon span { font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

    #foto_perfil_input { display: none; }

    /* --- BOTÓN DE ENVÍO (Elite Button) --- */
    .submit-button-container {
        text-align: center; margin-top: 3rem; padding-top: 2rem;
        border-top: 1px solid var(--color-border);
    }
    .btn-submit {
        padding: 1rem 3rem; font-size: 1.1rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 1.5px;
        background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
        border: none; border-radius: 14px; color: #ffffff; cursor: pointer;
        transition: var(--transition-smooth);
        display: inline-flex; align-items: center; gap: 1rem;
        box-shadow: 0 10px 30px -10px var(--color-primary-glow);
        font-family: 'Orbitron', sans-serif;
    }
    .btn-submit:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px -5px var(--color-primary-glow);
    }
    .btn-submit:active { transform: scale(0.98); }

    /* Responsive */
    @media (max-width: 768px) {
        .form-wrapper-card { padding: 2rem 1.5rem; }
        .page-header h1 { font-size: 1.8rem; }
        .form-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="main-container">
    <div class="page-header">
        <h1><i class="ph-duotone ph-user-plus"></i> Registrar Nuevo Alumno</h1>
    </div>
    
    <div class="form-wrapper-card">
        <form action="guardar_alumno.php" method="POST" enctype="multipart/form-data">
            
            <div class="form-section">
                <h2><i class="ph-duotone ph-identification-card"></i> Datos Personales</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre(s)</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Ej. Juan" required>
                    </div>
                    <div class="form-group">
                        <label for="apellidos">Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos" placeholder="Ej. Pérez López" required>
                    </div>
                    <div class="form-group">
                        <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento">
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" placeholder="Ej. 686-123-4567">
                    </div>
                    <div class="form-group">
                        <label for="telefono_emergencia">Teléfono de Emergencia</label>
                        <input type="tel" id="telefono_emergencia" name="telefono_emergencia" placeholder="Contacto de emergencia" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="correo@ejemplo.com">
                    </div>
                    <div class="form-group full-width">
                        <label for="direccion">Dirección</label>
                        <input type="text" id="direccion" name="direccion" placeholder="Ej. Calle Falsa 123, Colonia Centro">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2><i class="ph-duotone ph-medal"></i> Datos de Entrenamiento</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="disciplina_id">Disciplina</label>
                        <select id="disciplina_id" name="disciplina_id" required>
                            <option value="">Seleccione una disciplina</option>
                            <?php mysqli_data_seek($disciplinas_result, 0); while($row = $disciplinas_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nivel_id">Cinta / Nivel</label>
                        <select id="nivel_id" name="nivel_id">
                            <option value="">Seleccione un nivel</option>
                            <?php mysqli_data_seek($niveles_result, 0); while($row = $niveles_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="peso">Peso (kg)</label>
                        <input type="number" id="peso" step="0.1" name="peso" placeholder="Ej. 75.5">
                    </div>
                    <div class="form-group">
                        <label for="estatura">Estatura (m)</label>
                        <input type="number" id="estatura" step="0.01" name="estatura" placeholder="Ej. 1.82">
                    </div>
                    <div class="form-group">
                        <label for="talla_dojo">Talla de Ropa/Dojo</label>
                        <input type="text" id="talla_dojo" name="talla_dojo" placeholder="Ej. A2, CH, M">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2><i class="ph-duotone ph-credit-card"></i> Membresía y Pago</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="grupo_id">Grupo</label>
                        <select id="grupo_id" name="grupo_id">
                            <option value="">Seleccione un grupo</option>
                            <?php mysqli_data_seek($grupos_result, 0); while($row = $grupos_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tipo_pago_id">Tipo de Cuota</label>
                        <select id="tipo_pago_id" name="tipo_pago_id">
                            <option value="">Seleccione la cuota</option>
                            <?php mysqli_data_seek($tipos_pago_result, 0); while($row = $tipos_pago_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>">
                                    <?php echo htmlspecialchars($row['concepto']) . ' ($' . number_format($row['monto'], 2) . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="estudiante">¿Es Estudiante?</label>
                        <select id="estudiante" name="estudiante">
                            <option value="No">No</option>
                            <option value="Si">Si</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2><i class="ph-duotone ph-camera"></i> Foto de Perfil</h2>
                <div class="profile-pic-group">
                    <div class="profile-pic-wrapper" onclick="document.getElementById('foto_perfil_input').click();" title="Haz clic para seleccionar una imagen">
                        <div class="profile-pic-inner">
                            <img id="profilePicPreview" src="/MOKUSO/assets/img/uploads/default.png" alt="Vista previa">
                            <div class="upload-icon">
                                <i class="ph-bold ph-camera-plus"></i>
                                <span>Cambiar Foto</span>
                            </div>
                        </div>
                    </div>
                    <input type="file" id="foto_perfil_input" name="foto_perfil" accept="image/*">
                    <p style="color: var(--color-text-gray); font-size: 0.9rem;">Formatos: JPG, PNG. Máx 2MB.</p>
                </div>
            </div>

            <div class="submit-button-container">
                <button type="submit" class="btn-submit">
                    <i class="ph-bold ph-check-circle"></i> Registrar Alumno
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('foto_perfil_input');
    const previewImage = document.getElementById('profilePicPreview');

    // Previsualización de imagen de perfil
    if (fileInput && previewImage) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.setAttribute('src', e.target.result);
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // Copiar teléfono principal a teléfono de emergencia
    const telefonoInput = document.getElementById('telefono');
    const telefonoEmergenciaInput = document.getElementById('telefono_emergencia');

    if (telefonoInput && telefonoEmergenciaInput) {
        telefonoInput.addEventListener('input', function() {
            // Solo copia el valor si el campo de emergencia está vacío
            if (telefonoEmergenciaInput.value.trim() === '') {
                telefonoEmergenciaInput.value = this.value;
            }
        });
    }
});
</script>

<?php include '../templates/footer.php'; ?>
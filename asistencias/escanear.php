<?php
session_start();
// Seguridad: Solo un 'maestro' puede operar la página del escáner.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'maestro') {
    header("Location: /MOKUSO/login.php"); // Ajusta tu ruta de login
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escanear Asistencia</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex; 
            flex-direction: column;
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            background: #f0f2f5; 
            margin: 0;
            padding: 20px 0;
            box-sizing: border-box;
        }
        #reader {
            width: 400px;
            max-width: 90%;
            border: 2px solid #007bff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #333; 
            text-align: center;
        }
        #status-container {
            margin-top: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            display: none; /* Oculto por defecto */
            width: 400px;
            max-width: 90%;
            box-sizing: border-box; 
            text-align: center;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <h1>Escanear Código QR de Asistencia</h1>
    
    <div id="reader"></div>
    
    <div id="status-container"></div>

    <audio id="audio-success" src="https://actions.google.com/sounds/v1/alarms/beep_short.ogg" preload="auto"></audio>
    <audio id="audio-warning" src="https://actions.google.com/sounds/v1/alerts/medium_fault.ogg" preload="auto"></audio>


    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script>
        // ¡NUEVO! Obtenemos los elementos de audio
        var audioSuccess = document.getElementById('audio-success');
        var audioWarning = document.getElementById('audio-warning');
        
        var statusContainer = document.getElementById('status-container');
        var lastScanTime = 0; 

        // Función para mostrar mensajes de estado
        function showMessage(message, type) {
            statusContainer.textContent = message;
            statusContainer.className = 'status-' + type; 
            statusContainer.style.display = 'block';
            
            // --- ¡NUEVO! Reproducir sonido basado en el tipo de mensaje ---
            try {
                if (type === 'success') {
                    audioSuccess.currentTime = 0; // Reinicia el sonido por si se repite rápido
                    audioSuccess.play();
                } else if (type === 'warning' || type === 'error') {
                    audioWarning.currentTime = 0; // Reinicia el sonido
                    audioWarning.play();
                }
            } catch (e) {
                console.warn("No se pudo reproducir el sonido:", e);
            }
            // --- Fin de la sección de sonido ---

            setTimeout(() => {
                statusContainer.style.display = 'none';
            }, 3000);
        }

        // Función que se llama cuando el escáner tiene éxito
        function onScanSuccess(decodedText, decodedResult) {
            
            var now = Date.now();
            if (now - lastScanTime < 2000) { // 2 segundos de espera
                return;
            }
            lastScanTime = now;

            console.log(`Resultado del escaneo: ${decodedText}`);
            
            const formData = new FormData();
            formData.append('alumno_id', decodedText);

            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            const fecha_hoy = `${yyyy}-${mm}-${dd}`;
            
            formData.append('fecha_asistencia', fecha_hoy);

            // --- ¡IMPORTANTE! Asegúrate que esta ruta sea correcta ---
            // Si 'escanear.php' está en /asistencias/ y tu script está en /alumnos/
            // la ruta sería '../alumnos/registrar_asistencia.php'
            // Si ambos están en /asistencias/, la ruta sería 'registrar_asistencia.php'
            fetch('guardar_asistencia.php', { // <-- REVISA ESTA RUTA
                method: 'POST',
                body: formData 
            })
            .then(response => response.json()) 
            .then(data => {
                console.log('Respuesta del servidor:', data);
                // La función showMessage ahora se encarga del sonido
                showMessage(data.message, data.status); 
            })
            .catch(error => {
                console.error('Error en fetch:', error);
                // También reproducirá el sonido de error
                showMessage('Error al conectar con el servidor.', 'error');
            });
        }

        // Configuración e inicio del escáner
        var html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", 
            { 
                fps: 10, 
                qrbox: { width: 250, height: 250 } 
            }, 
            false 
        );
        
        html5QrcodeScanner.render(onScanSuccess);
    </script>
</body>
</html>
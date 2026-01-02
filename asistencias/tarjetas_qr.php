<?php
session_start();
// La ruta ahora sube un nivel ('..') para encontrar la carpeta 'config'
include '../config/db.php'; 

// 1. VERIFICACIÓN DE SEGURIDAD
// Asegura que solo un 'maestro' pueda ver esta página
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'maestro') {
    header("Location: /MOKUSO/login.php"); // Ajusta tu ruta de login
    exit();
}

// 2. OBTENER ALUMNOS
// Obtenemos todos los alumnos de la tabla 'alumnos' para generar sus tarjetas
$sql_alumnos = "SELECT id, nombre, apellidos FROM alumnos ORDER BY nombre";
$result_alumnos = $conn->query($sql_alumnos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarjetas de Alumnos (QR)</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f0f2f5; 
            padding: 20px; 
            color: #333;
        }
        h1 { 
            text-align: center; 
            color: #2c3e50;
        }
        .print-instructions {
            text-align: center;
            font-size: 1.1rem;
            color: #555;
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            max-width: 800px;
            margin: 0 auto 25px auto;
            border: 1px solid #ddd;
        }

        /* 3. REJILLA RESPONSIVA DE TARJETAS */
        .grid-tarjetas {
            display: grid;
            /* Crea columnas de 250px, y llena el espacio */
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* 4. ESTILO DE CADA TARJETA */
        .tarjeta {
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .tarjeta:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        .tarjeta h3 { 
            margin: 10px 0 5px 0; 
            font-size: 1.25rem;
            color: #007bff;
        }
        .tarjeta p { 
            margin: 0; 
            color: #6c757d; 
            font-size: 14px; 
            font-weight: 600;
        }

        /* 5. CONTENEDOR DEL QR */
        .qr-code {
            width: 200px;       /* Tamaño fijo para el QR */
            height: 200px;
            margin: 20px auto 0 auto;
            border: 2px solid #f0f0f0;
            padding: 5px;       /* Un pequeño borde blanco */
            border-radius: 5px;
        }

        /* 6. ESTILOS DE IMPRESIÓN */
        @media print {
            body { 
                background: #fff; 
                padding: 0; 
            }
            /* Oculta el título y las instrucciones al imprimir */
            h1, .print-instructions { 
                display: none; 
            } 
            .grid-tarjetas { 
                display: block; /* Quita la rejilla */
            }
            .tarjeta {
                box-shadow: none;
                border: 1px solid #000; /* Borde simple para el corte */
                /* Evita que una tarjeta se corte entre dos páginas */
                page-break-inside: avoid; 
                margin-bottom: 20px; /* Espacio entre tarjetas en la hoja */
            }
        }
    </style>
</head>
<body>

    <h1>Tarjetas de Asistencia de Alumnos</h1>
    <p class="print-instructions">
        Usa <strong>Ctrl+P</strong> (o <strong>Cmd+P</strong> en Mac) para imprimir estas tarjetas y repartirlas a tus alumnos.
    </p>
    
    <div class="grid-tarjetas">
        <?php if ($result_alumnos->num_rows > 0): ?>
            <?php while($alumno = $result_alumnos->fetch_assoc()): ?>
                <div class="tarjeta">
                    <h3><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></h3>
                    <p>ID de Alumno: <?php echo $alumno['id']; ?></p>
                    
                    <div class="qr-code" data-id="<?php echo $alumno['id']; ?>"></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align: center;">No se encontraron alumnos en la base de datos.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    
    <script>
        // Espera a que todo el HTML esté cargado
        document.addEventListener("DOMContentLoaded", function() {
            
            // Busca todos los elementos que tienen la clase 'qr-code'
            var qrContainers = document.querySelectorAll('.qr-code');
            
            // Recorre cada uno de los contenedores que encontró
            qrContainers.forEach(function(container) {
                
                // Lee el ID del alumno guardado en el atributo 'data-id'
                var alumnoId = container.getAttribute('data-id');
                
                // Si el ID existe, genera el QR
                if (alumnoId) {
                    new QRCode(container, {
                        text: alumnoId, // Contenido del QR (¡solo el ID!)
                        width: 200,     // Ancho (debe coincidir con el CSS)
                        height: 200,    // Alto (debe coincidir con el CSS)
                        correctLevel : QRCode.CorrectLevel.H // Alta corrección de errores
                    });
                }
            });
        });
    </script>

</body>
</html>
<?php
$conn->close();
?>
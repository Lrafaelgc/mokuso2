<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mokuso Manager | Acceso de Élite</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>

    <style>
        :root {
            /* COLORES BASADOS EN TU LOGO */
            --color-primary: #8cc63f;        /* Verde similar al de tu logo */
            --color-primary-dark: #6a9e2d;   /* Versión más oscura para hovers */
            --color-primary-glow: rgba(140, 198, 63, 0.4);

            --color-bg-main: #0a0a0f;        /* Fondo casi negro, muy elegante */
            --color-bg-secondary: #12121a;   /* Para gradientes sutiles */

            --color-surface: rgba(22, 22, 30, 0.75); /* Cristal oscuro */
            --color-border: rgba(255, 255, 255, 0.08);

            --color-text-white: #ffffff;
            --color-text-gray: #a0a0b0;

            --color-error: #ff4757;

            /* EFECTOS */
            --shadow-heavy: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            --backdrop-blur: 20px;
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            background-color: var(--color-bg-main);
            /* Fondo con un gradiente radial sutil para dar profundidad sin ser caótico */
            background-image:
                radial-gradient(circle at 10% 20%, rgba(140, 198, 63, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(20, 100, 255, 0.08) 0%, transparent 40%);
            color: var(--color-text-white);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            overflow-x: hidden;
        }

        /* Patrón de cuadrícula sutil para efecto "Tech" */
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -1;
            mask-image: radial-gradient(circle at center, black 40%, transparent 100%);
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            perspective: 1000px; /* Para efectos 3D sutiles si se desean */
        }

        .login-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-top: 1px solid rgba(255, 255, 255, 0.15); /* Luz superior */
            backdrop-filter: blur(var(--backdrop-blur));
            -webkit-backdrop-filter: blur(var(--backdrop-blur));
            border-radius: 24px;
            padding: 3rem 2.5rem;
            box-shadow: var(--shadow-heavy);
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: cardEntrance 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        /* Barra superior de energía */
        .login-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--color-primary), transparent);
            opacity: 0.8;
        }

        @keyframes cardEntrance {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .logo-container {
            margin-bottom: 2rem;
            position: relative;
            display: inline-block;
        }

        .logo {
            width: 120px;
            height: auto;
            /* Sutil sombra verde para hacer que el logo "flote" y brille */
            filter: drop-shadow(0 0 20px var(--color-primary-glow));
            transition: var(--transition-smooth);
        }
        .logo:hover {
            transform: scale(1.05);
            filter: drop-shadow(0 0 30px var(--color-primary-glow));
        }

        h1 {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1.8rem;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, #fff, var(--color-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--color-text-gray);
            font-size: 0.95rem;
            margin-bottom: 2.5rem;
            font-weight: 400;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-gray);
            font-size: 1.4rem;
            transition: var(--transition-smooth);
            z-index: 2;
        }

        .form-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid var(--color-border);
            padding: 1rem 1rem 1rem 3.5rem; /* Espacio para el icono */
            border-radius: 14px;
            color: var(--color-text-white);
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: var(--transition-smooth);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            background: rgba(0, 0, 0, 0.5);
            box-shadow: 0 0 25px -5px var(--color-primary-glow);
        }

        .form-input:focus + .input-icon {
            color: var(--color-primary);
        }

        .form-label {
            /* Etiqueta flotante moderna */
            position: absolute;
            left: 3.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-gray);
            pointer-events: none;
            transition: var(--transition-smooth);
            font-size: 1rem;
            background: transparent;
            padding: 0 5px;
        }

        /* Estado activo de la etiqueta (cuando hay foco o texto) */
        .form-input:focus ~ .form-label,
        .form-input:not(:placeholder-shown) ~ .form-label {
            top: 0;
            left: 1rem;
            transform: translateY(-50%) scale(0.85);
            color: var(--color-primary);
            font-weight: 600;
            background: var(--color-bg-main); /* Truco para tapar la línea si fuera necesario, ajustable */
             /* O mejor, un background que coincida con el input si es sólido,
                pero como es transparente, usamos un gradiente o lo dejamos transparente
                si el diseño lo permite. En este caso, lo mejor es moverlo ARRIBA del input. */
            top: -10px;
            transform: translateY(0) scale(0.85);
            z-index: 3;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            margin-top: 1.5rem;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: #fff; /* Texto blanco para mejor contraste sobre verde */
            font-family: 'Orbitron', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 1px;
            cursor: pointer;
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px -10px var(--color-primary-glow);
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px -5px var(--color-primary-glow);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        /* Efecto de brillo al pasar el mouse por el botón */
        .btn-submit::after {
            content: '';
            position: absolute;
            top: -50%; right: -50%; bottom: -50%; left: -50%;
            background: linear-gradient(to bottom, rgba(255,255,255,0) 20%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0) 80%);
            transform: rotateZ(60deg) translate(-5em, 7.5em);
            opacity: 0;
            transition: var(--transition-smooth);
        }
        .btn-submit:hover::after {
            animation: sheen 1s forwards;
            opacity: 1;
        }
        @keyframes sheen {
            100% { transform: rotateZ(60deg) translate(1em, -9em); }
        }

        .error-message {
            background: rgba(255, 71, 87, 0.1);
            color: var(--color-error);
            padding: 1rem;
            border-radius: 12px;
            border-left: 4px solid var(--color-error);
            margin-top: 1.5rem;
            font-size: 0.9rem;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
             0%, 100% { transform: translateX(0); }
             20%, 60% { transform: translateX(-5px); }
             40%, 80% { transform: translateX(5px); }
        }

        .footer {
            position: absolute;
            bottom: 1.5rem;
            color: var(--color-text-gray);
            font-size: 0.8rem;
            opacity: 0.6;
            letter-spacing: 1px;
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {
            .login-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }
            h1 { font-size: 1.5rem; }
            .logo { width: 100px; }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-card">
            <div class="logo-container">
                <img src="/MOKUSO/assets/img/logo2.png" alt="Mokuso Logo" class="logo">
            </div>
            
            <h1>SISTEMA MOKUSO</h1>
            <p class="subtitle">Inicia sesión para entrar al Dojo Digital</p>

            <form action="/MOKUSO/config/login.php" method="POST" autocomplete="off">
                
                <div class="form-group">
                    <input type="text" id="username" name="username" class="form-input" placeholder=" " required>
                    <label for="username" class="form-label">Usuario</label>
                    <i class="ph-bold ph-user input-icon"></i>
                </div>

                <div class="form-group">
                    <input type="password" id="password" name="password" class="form-input" placeholder=" " required>
                    <label for="password" class="form-label">Contraseña</label>
                    <i class="ph-bold ph-lock-key input-icon"></i> 
                </div>

                <button type="submit" class="btn-submit">
                    ENTRAR <i class="ph-bold ph-arrow-right" style="vertical-align: middle; margin-left: 8px;"></i>
                </button>

                <?php
                if (isset($_GET['error']) && $_GET['error'] == '1') {
                    echo '
                    <div class="error-message">
                        <i class="ph-bold ph-warning-circle" style="font-size: 1.5rem;"></i>
                        <span>Credenciales incorrectas. Inténtalo nuevamente.</span>
                    </div>';
                }
                ?>
            </form>
        </div>
    </div>

    <div class="footer">
        © <?php echo date("Y"); ?> MOKUSO ACADEMY | v2.0 Elite
    </div>

</body>
</html>
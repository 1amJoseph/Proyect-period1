<?php
// Inicia la sesión PHP para poder usar variables de sesión ($_SESSION)
session_start();

// Carga el archivo de credenciales ubicado fuera del webroot (no accesible por URL)
// define() en credentials.php establece AUTH_USER y AUTH_PASS como constantes globales
require_once 'credentials.php';

// isset() verifica que la clave 'logueado' exista en $_SESSION
// Si el usuario ya tiene sesión activa, se redirige directamente al dashboard
if (isset($_SESSION['logueado']) && $_SESSION['logueado'] === true) {
    // header() envía una cabecera HTTP al navegador; 'Location' redirige a otra página
    header('Location: stats.php');
    // exit detiene la ejecución del script para que no continúe después del redirect
    exit;
}

$error = null;

// $_SERVER['REQUEST_METHOD'] contiene el método HTTP de la petición (GET, POST, etc.)
// Solo se procesa el formulario si fue enviado por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // trim() elimina espacios en blanco al inicio y al final de una cadena
    // ?? '' es el operador null coalescing: si $_POST['usuario'] no existe, usa ''
    $usuario  = trim($_POST['usuario']  ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validar que ninguno de los campos esté vacío
    if ($usuario === '' || $password === '') {
        $error = "Completa todos los campos.";

        // Verificar credenciales contra las constantes definidas en credentials.php
        // password_verify() compara una contraseña en texto plano contra su hash bcrypt
        // AUTH_USER y AUTH_PASS son constantes definidas con define() en credentials.php
    } elseif ($usuario !== AUTH_USER || !password_verify($password, AUTH_PASS)) {

        // sleep() pausa la ejecución N segundos; aquí dificulta ataques de fuerza bruta
        // ya que cada intento fallido tarda al menos 1 segundo
        sleep(1);
        $error = "Usuario o contrasena incorrectos.";
    } else {
        // Credenciales correctas: guardar estado de sesión
        $_SESSION['logueado'] = true;
        // Guardar el nombre de usuario en sesión para usarlo en otras páginas
        $_SESSION['usuario']  = $usuario;

        // session_regenerate_id(true) genera un nuevo ID de sesión y destruye el anterior
        // Esto previene ataques de "session fixation"
        session_regenerate_id(true);

        // Redirigir al dashboard principal
        header('Location: stats.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesion - Panes Bea</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header img {
            width: 80px;
            margin-bottom: 1rem;
        }

        .login-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
        }

        .login-header p {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }

        .login-btn {
            width: 100%;
            padding: 11px;
            border-radius: 10px;
            border: none;
            background: #1e293b;
            color: white;
            font-size: 15px;
            font-weight: 600;
            font-family: Arial, Helvetica, sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
        }

        .login-btn:hover {
            background: #0f172a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>

<body>
    <div class="login-card">

        <div class="login-header">
            <img src="pfp.webp" alt="PanesBea">
            <h2>PanesBea</h2>
            <p>Ingresa tus credenciales para continuar</p>
        </div>

        <?php if ($error): ?>
            <!-- htmlspecialchars() convierte caracteres especiales (<, >, &, ", ')
                 a entidades HTML para prevenir ataques XSS (inyección de código) -->
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label>Usuario</label>
                <!-- htmlspecialchars() se usa aquí para que el valor previo del campo
                     se muestre de forma segura si el formulario fue enviado con error -->
                <input
                    type="text"
                    name="usuario"
                    autocomplete="username"
                    value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                    required
                    autofocus>
            </div>
            <div class="field">
                <label>Contrasena</label>
                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required>
            </div>
            <button type="submit" class="login-btn">Iniciar sesion</button>
        </form>

    </div>
</body>

</html>
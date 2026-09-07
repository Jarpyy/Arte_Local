<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/bd.php';

redirigirSiAutenticado('dashboard/dashboard.php');

$csrfToken = tokenCSRF();

$error = '';
$email = '';


if (isset($_GET['registro']) && $_GET['registro'] === 'ok') {
    $registro_exitoso = true;
} else {
    $registro_exitoso = false;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';


    if (!validarCSRF($csrf_token)) {
        $error = 'Solicitud no válida.';
    }


    if ($error === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Las credenciales no son válidas.';
    }


    if ($error === '' && $password === '') {
        $error = 'Las credenciales no son válidas.';
    }


    if ($error === '') {

        // POR:
$consulta = $conexion->prepare(
    'SELECT
        id,
        nombre,
        email,
        password_hash,
        rol
    FROM usuarios
    WHERE email = :email
    LIMIT 1'
);

        $consulta->execute([
            'email' => $email
        ]);

        $usuario = $consulta->fetch();


        if (
            !$usuario ||
            !password_verify($password, $usuario['password'])
        ) {
            $error = 'Las credenciales no son válidas.';
        }


        if (
            $error === '' &&
            $usuario['estado'] !== 'activo'
        ) {
            $error = 'La cuenta no está disponible.';
        }


        if ($error === '') {

            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_rol'] = $usuario['rol'];

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: dashboard/dashboard.php');
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | Arte Local</title>

    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>

    <main class="auth-page">

        <section class="auth-card">

            <a href="index.php" class="auth-logo">
                ARTE LOCAL
            </a>

            <span class="auth-label">
                ACCESO
            </span>

            <h1>
                Iniciar sesión
            </h1>

            <p class="auth-description">
                Ingresá a tu cuenta para continuar.
            </p>


            <?php if ($registro_exitoso): ?>

                <div class="auth-success">
                    Cuenta creada correctamente. Ahora podés iniciar sesión.
                </div>

            <?php endif; ?>


            <?php if ($error !== ''): ?>

                <div class="auth-error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <form action="login.php" method="post" class="auth-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken) ?>"
                >


                <div class="form-group">

                    <label for="email">
                        Correo electrónico
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($email) ?>"
                        placeholder="tu@email.com"
                        maxlength="255"
                        autocomplete="email"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="password">
                        Contraseña
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Tu contraseña"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <button type="submit" class="aero-button">
                    Iniciar sesión
                </button>

            </form>


            <p class="auth-footer">
                ¿Todavía no tenés una cuenta?
                <a href="register.php">
                    Registrate
                </a>
            </p>

            <a href="index.php" class="back-link">
                ← Volver
            </a>

        </section>

    </main>

</body>

</html>
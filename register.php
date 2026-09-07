<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
iniciarSesionSegura();  // AGREGAR: asegura bootstrap de sesión explícito, igual que en logout.php

$csrfToken = tokenCSRF();

$errores = [];

$nombre = '';
$email = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirmation = $_POST['password_confirmation'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';


    if (!validarCSRF($csrf_token)) {
        $errores[] = 'Solicitud no válida.';
    }


    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($nombre) < 2) {
        $errores[] = 'El nombre debe tener al menos 2 caracteres.';
    } elseif (mb_strlen($nombre) > 100) {
        $errores[] = 'El nombre no puede superar los 100 caracteres.';
    }


    if ($email === '') {
        $errores[] = 'El correo electrónico es obligatorio.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no es válido.';
    } elseif (mb_strlen($email) > 255) {
        $errores[] = 'El correo electrónico es demasiado largo.';
    }


    if ($password === '') {
        $errores[] = 'La contraseña es obligatoria.';
    } elseif (strlen($password) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif (strlen($password) > 255) {
        $errores[] = 'La contraseña es demasiado larga.';
    }


    if ($password !== $password_confirmation) {
        $errores[] = 'Las contraseñas no coinciden.';
    }


    if (empty($errores)) {

        $consulta = $conexion->prepare(
            'SELECT id FROM usuarios WHERE email = :email LIMIT 1'
        );

        $consulta->execute([
            'email' => $email
        ]);

        if ($consulta->fetch()) {
            $errores[] = 'No se pudo crear la cuenta con esos datos.';
        }
    }


    if (empty($errores)) {

        $password_hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        try {
            $consulta = $conexion->prepare(
                'INSERT INTO usuarios
                (nombre, email, password_hash, rol)
                VALUES
                (:nombre, :email, :password_hash, :rol)'
            );

            $consulta->execute([
                'nombre' => $nombre,
                'email' => $email,
                'password_hash' => $password_hash,
                'rol' => 'cliente'
            ]);

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: login.php?registro=ok');
            exit;

        } catch (PDOException $e) {

            $errores[] = 'No se pudo crear la cuenta. Inténtalo nuevamente.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Crear cuenta | Arte Local</title>

    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>

    <main class="auth-page">

        <section class="auth-card">

            <a href="index.php" class="auth-logo">
                ARTE LOCAL
            </a>

            <span class="auth-label">
                REGISTRO
            </span>

            <h1>
                Crear cuenta
            </h1>

            <p class="auth-description">
                Creá tu cuenta para formar parte de Arte Local.
            </p>


            <?php if (!empty($errores)): ?>

                <div class="auth-error">

                    <?php foreach ($errores as $error): ?>

                        <p>
                            <?= htmlspecialchars($error) ?>
                        </p>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>


            <form action="register.php" method="post" class="auth-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken) ?>"
                >

                <div class="form-group">

                    <label for="nombre">
                        Nombre completo
                    </label>

                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        value="<?= htmlspecialchars($nombre) ?>"
                        placeholder="Tu nombre"
                        maxlength="100"
                        autocomplete="name"
                        required
                    >

                </div>


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
                        placeholder="Creá una contraseña"
                        autocomplete="new-password"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="password_confirmation">
                        Confirmar contraseña
                    </label>

                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Repetí tu contraseña"
                        autocomplete="new-password"
                        required
                    >

                </div>


                <button type="submit" class="aero-button">
                    Crear cuenta
                </button>

            </form>


            <p class="auth-footer">
                ¿Ya tenés una cuenta?
                <a href="login.php">
                    Iniciá sesión
                </a>
            </p>

            <a href="index.php" class="back-link">
                ← Volver
            </a>

        </section>

    </main>

</body>

</html>
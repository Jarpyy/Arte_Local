<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

$usuarioId = $_SESSION['usuario_id'];

// --- Si ya tiene perfil de artista, no tiene sentido mostrarle este formulario ---
$consultaExistente = $conexion->prepare('SELECT id FROM artistas WHERE usuario_id = :usuario');
$consultaExistente->execute(['usuario' => $usuarioId]);
$artistaExistente = $consultaExistente->fetch();

if ($artistaExistente) {
    header('Location: perfil.php?id=' . (int) $artistaExistente['id']);
    exit;
}

$csrfToken = tokenCSRF();

$errores = $_SESSION['convertirse_errores'] ?? [];
$valores = $_SESSION['convertirse_valores'] ?? [];
unset($_SESSION['convertirse_errores'], $_SESSION['convertirse_valores']);

const EXTENSIONES_FOTO_PERMITIDAS = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
const TAMANO_MAXIMO_FOTO = 5 * 1024 * 1024; // 5 MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $errores = [];

    $nombre = trim($_POST['nombre'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    $valores = [
        'nombre' => $nombre,
        'bio' => $bio,
    ];

    if (!validarCSRF($_POST['csrf_token'] ?? null)) {
        $errores[] = 'Solicitud no válida. Volvé a intentarlo.';
    }

    if ($nombre === '' || mb_strlen($nombre) > 150) {
        $errores[] = 'El nombre artístico es obligatorio y debe tener hasta 150 caracteres.';
    }

    // bio es nullable en el esquema: si viene vacía, no es un error.
    if (mb_strlen($bio) > 2000) {
        $errores[] = 'La biografía es demasiado larga.';
    }

    // --- Foto: opcional. Si no se envía archivo, se guarda NULL. ---
    $rutaFotoGuardada = null;
    $archivo = $_FILES['foto'] ?? null;
    $hayArchivo = $archivo && $archivo['error'] !== UPLOAD_ERR_NO_FILE;

    if ($hayArchivo) {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'Ocurrió un error al subir la foto.';
        } elseif ($archivo['size'] > TAMANO_MAXIMO_FOTO) {
            $errores[] = 'La foto no puede superar los 5 MB.';
        } else {
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $mimeReal = mime_content_type($archivo['tmp_name']);

            $extensionValida = array_key_exists($extension, EXTENSIONES_FOTO_PERMITIDAS);
            $mimeValido = $extensionValida && $mimeReal === EXTENSIONES_FOTO_PERMITIDAS[$extension];

            if (!$extensionValida || !$mimeValido) {
                $errores[] = 'La foto debe ser JPG, PNG o WEBP.';
            } else {
                $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
                $directorioDestino = __DIR__ . '/../assets/images/artistas/';

                if (!is_dir($directorioDestino)) {
                    mkdir($directorioDestino, 0755, true);
                }

                $rutaDestino = $directorioDestino . $nombreArchivo;

                if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                    $rutaFotoGuardada = 'assets/images/artistas/' . $nombreArchivo;
                } else {
                    $errores[] = 'No se pudo guardar la foto. Intentá nuevamente.';
                }
            }
        }
    }

    if (empty($errores)) {
        try {
            // --- Re-chequeo dentro del mismo request: evita alta duplicada por doble click/doble tab ---
            $rechequeo = $conexion->prepare('SELECT id FROM artistas WHERE usuario_id = :usuario');
            $rechequeo->execute(['usuario' => $usuarioId]);

            if ($rechequeo->fetch()) {
                if ($rutaFotoGuardada !== null) {
                    $rutaAbsoluta = __DIR__ . '/../' . $rutaFotoGuardada;
                    if (is_file($rutaAbsoluta)) {
                        unlink($rutaAbsoluta);
                    }
                }
                header('Location: convertirse.php');
                exit;
            }

            $insertar = $conexion->prepare(
                'INSERT INTO artistas (nombre, bio, foto, usuario_id)
                 VALUES (:nombre, :bio, :foto, :usuario)'
            );
            $insertar->execute([
                'nombre' => $nombre,
                'bio' => $bio !== '' ? $bio : null,
                'foto' => $rutaFotoGuardada,
                'usuario' => $usuarioId,
            ]);

            header('Location: perfil.php?id=' . (int) $conexion->lastInsertId());
            exit;

        } catch (PDOException $e) {
            // No se expone el mensaje real de PDO. Si la foto ya se guardó y el INSERT falla, se limpia.
            if ($rutaFotoGuardada !== null) {
                $rutaAbsoluta = __DIR__ . '/../' . $rutaFotoGuardada;
                if (is_file($rutaAbsoluta)) {
                    unlink($rutaAbsoluta);
                }
            }
            $errores[] = 'No se pudo crear tu perfil de artista. Intentá nuevamente.';
        }
    } elseif ($rutaFotoGuardada !== null) {
        // Datos inválidos pero la foto ya se había guardado: se limpia.
        $rutaAbsoluta = __DIR__ . '/../' . $rutaFotoGuardada;
        if (is_file($rutaAbsoluta)) {
            unlink($rutaAbsoluta);
        }
    }

    $_SESSION['convertirse_errores'] = $errores;
    $_SESSION['convertirse_valores'] = $valores;
    header('Location: convertirse.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convertirme en artista | Arte Local</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-cliente">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local</div>
        <nav class="panel-header__nav">
            <a href="../dashboard.php" class="activo">Mi Panel</a>
            <a href="../gallery/index.php">Galería</a>
            <a href="../pedidos/index.php">Mis Pedidos</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">

        <section class="tarjeta">
            <h1>Quiero vender mis obras</h1>
            <p>Creá tu perfil público de artista para poder publicar obras en la galería.</p>

            <?php if (!empty($errores)): ?>
                <ul>
                    <?php foreach ($errores as $err): ?>
                        <li class="badge badge--agotado"><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" action="convertirse.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-group">
                    <label for="nombre">Nombre artístico</label>
                    <input type="text" id="nombre" name="nombre" maxlength="150" required
                           value="<?= htmlspecialchars($valores['nombre'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="bio">Biografía (opcional)</label>
                    <textarea id="bio" name="bio" rows="4"><?= htmlspecialchars($valores['bio'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="foto">Foto de perfil (opcional, JPG/PNG/WEBP, máx. 5 MB)</label>
                    <input type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png,.webp">
                </div>

                <button type="submit" class="aero-button primary-button">Crear mi perfil de artista</button>
            </form>
        </section>

    </main>
</body>
</html>
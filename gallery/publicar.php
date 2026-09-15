<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

$usuarioId = $_SESSION['usuario_id'];

// --- Resolver artista desde la sesión, nunca desde el formulario ---
$consultaArtista = $conexion->prepare('SELECT id FROM artistas WHERE usuario_id = :usuario');
$consultaArtista->execute(['usuario' => $usuarioId]);
$artista = $consultaArtista->fetch();

if (!$artista) {
    // Dependencia: requiere que exista el alta de artista (Fase 1).
    // Si ese archivo todavía no existe, este redirect será un dead-end temporal.
    header('Location: ../artistas/convertirse.php');
    exit;
}

$artistaId = (int) $artista['id'];

$csrfToken = tokenCSRF();

// --- Flash de sesión: patrón POST/Redirect/GET, nunca se renderiza directo tras un POST ---
$errores = $_SESSION['publicar_errores'] ?? [];
$valores = $_SESSION['publicar_valores'] ?? [];
$exito   = $_SESSION['publicar_exito'] ?? null;
unset($_SESSION['publicar_errores'], $_SESSION['publicar_valores'], $_SESSION['publicar_exito']);

$categorias = $conexion->query('SELECT id, nombre FROM categorias ORDER BY nombre ASC')->fetchAll();

const TIPOS_PERMITIDOS = ['digital', 'fisica'];
const EXTENSIONES_PERMITIDAS = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
const TAMANO_MAXIMO_IMAGEN = 5 * 1024 * 1024; // 5 MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $errores = [];

    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precioRaw = trim($_POST['precio'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $stockRaw = trim($_POST['stock'] ?? '');
    $categoriaIdRaw = $_POST['categoria_id'] ?? '';

    // Se guarda para repoblar el formulario si algo falla (nunca la imagen, por seguridad/simplicidad)
    $valores = [
        'titulo' => $titulo,
        'descripcion' => $descripcion,
        'precio' => $precioRaw,
        'tipo' => $tipo,
        'stock' => $stockRaw,
        'categoria_id' => $categoriaIdRaw,
    ];

    if (!validarCSRF($_POST['csrf_token'] ?? null)) {
        $errores[] = 'Solicitud no válida. Volvé a intentarlo.';
    }

    if ($titulo === '' || mb_strlen($titulo) > 150) {
        $errores[] = 'El título es obligatorio y debe tener hasta 150 caracteres.';
    }

    if (mb_strlen($descripcion) < 10) {
        $errores[] = 'La descripción debe tener al menos 10 caracteres.';
    }

    $precio = filter_var($precioRaw, FILTER_VALIDATE_FLOAT);
    if ($precio === false || $precio <= 0) {
        $errores[] = 'El precio debe ser un número mayor a cero.';
    }

    if (!in_array($tipo, TIPOS_PERMITIDOS, true)) {
        $errores[] = 'El tipo de obra no es válido.';
    }

    $stock = null;
    if ($tipo === 'fisica') {
        $stock = filter_var($stockRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($stock === false) {
            $errores[] = 'El stock debe ser un número entero mayor o igual a cero.';
        }
    }
    // Si es digital, stock se guarda como NULL — misma regla que ya usa gallery/index.php.

    $categoriaId = filter_var($categoriaIdRaw, FILTER_VALIDATE_INT);
    if (empty($categorias)) {
        $errores[] = 'Todavía no hay categorías cargadas. Avisá al administrador antes de publicar.';
    } elseif ($categoriaId === false) {
        $errores[] = 'Seleccioná una categoría.';
    } else {
        $chequeoCategoria = $conexion->prepare('SELECT id FROM categorias WHERE id = :id');
        $chequeoCategoria->execute(['id' => $categoriaId]);
        if (!$chequeoCategoria->fetch()) {
            $errores[] = 'La categoría seleccionada no existe.';
        }
    }

    // --- Validación de archivo ---
    $rutaImagenGuardada = null;
    $archivo = $_FILES['imagen'] ?? null;

    if (!$archivo || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        $errores[] = 'Debés subir una imagen de la obra.';
    } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'Ocurrió un error al subir la imagen.';
    } elseif ($archivo['size'] > TAMANO_MAXIMO_IMAGEN) {
        $errores[] = 'La imagen no puede superar los 5 MB.';
    } else {
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $mimeReal = mime_content_type($archivo['tmp_name']);

        $extensionValida = array_key_exists($extension, EXTENSIONES_PERMITIDAS);
        $mimeValido = $extensionValida && $mimeReal === EXTENSIONES_PERMITIDAS[$extension];

        if (!$extensionValida || !$mimeValido) {
            $errores[] = 'La imagen debe ser JPG, PNG o WEBP.';
        } else {
            $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
            $directorioDestino = __DIR__ . '/../assets/images/obras/';

            if (!is_dir($directorioDestino)) {
                mkdir($directorioDestino, 0755, true);
            }

            $rutaDestino = $directorioDestino . $nombreArchivo;

            if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                $rutaImagenGuardada = 'assets/images/obras/' . $nombreArchivo;
            } else {
                $errores[] = 'No se pudo guardar la imagen. Intentá nuevamente.';
            }
        }
    }

    if (empty($errores)) {
        try {
            $insertar = $conexion->prepare(
                'INSERT INTO obras (titulo, descripcion, precio, tipo, stock, categoria_id, artista_id, imagen, disponible)
                 VALUES (:titulo, :descripcion, :precio, :tipo, :stock, :categoria, :artista, :imagen, 1)'
            );
            $insertar->execute([
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'precio' => number_format($precio, 2, '.', ''),
                'tipo' => $tipo,
                'stock' => $stock,
                'categoria' => $categoriaId,
                'artista' => $artistaId,
                'imagen' => $rutaImagenGuardada,
            ]);

            $_SESSION['publicar_exito'] = '¡Obra publicada! Ya está disponible en la galería.';
            header('Location: publicar.php');
            exit;

        } catch (PDOException $e) {
            // No se expone el mensaje real de PDO al usuario.
            // Si el archivo ya se guardó y el INSERT falla, se elimina para no dejar huérfanos.
            if ($rutaImagenGuardada !== null) {
                $rutaAbsoluta = __DIR__ . '/../' . $rutaImagenGuardada;
                if (is_file($rutaAbsoluta)) {
                    unlink($rutaAbsoluta);
                }
            }
            $errores[] = 'No se pudo publicar la obra. Intentá nuevamente.';
        }
    } elseif ($rutaImagenGuardada !== null) {
        // Datos inválidos pero la imagen ya se había guardado: se limpia.
        $rutaAbsoluta = __DIR__ . '/../' . $rutaImagenGuardada;
        if (is_file($rutaAbsoluta)) {
            unlink($rutaAbsoluta);
        }
    }

    $_SESSION['publicar_errores'] = $errores;
    $_SESSION['publicar_valores'] = $valores;
    header('Location: publicar.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publicar obra | Arte Local</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-cliente">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local</div>
        <nav class="panel-header__nav">
            <a href="../dashboard.php">Mi Panel</a>
            <a href="index.php" class="activo">Galería</a>
            <a href="../pedidos/index.php">Mis Pedidos</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">

        <section class="tarjeta">
            <h1>Publicar obra</h1>

            <?php if ($exito): ?>
                <p class="badge badge--activo"><?= htmlspecialchars($exito) ?></p>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <ul>
                    <?php foreach ($errores as $err): ?>
                        <li class="badge badge--agotado"><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" action="publicar.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-group">
                    <label for="titulo">Título</label>
                    <input type="text" id="titulo" name="titulo" maxlength="150" required
                           value="<?= htmlspecialchars($valores['titulo'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="4" required><?= htmlspecialchars($valores['descripcion'] ?? '') ?></textarea>
                </div>

                 <div class="form-group">
                    <label for="categoria_id">Categoría</label>
                    <?php if (empty($categorias)): ?>
                        <p class="badge badge--agotado">Todavía no hay categorías cargadas.</p>
                        <select id="categoria_id" name="categoria_id" disabled>
                            <option value="">Sin categorías disponibles</option>
                        </select>
                    <?php else: ?>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Elegí una categoría</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= (int) $categoria['id'] ?>"
                                    <?= (isset($valores['categoria_id']) && (int) $valores['categoria_id'] === (int) $categoria['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoria['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo" onchange="document.getElementById('campo-stock').style.display = this.value === 'fisica' ? 'flex' : 'none';" required>
                        <option value="">Elegí un tipo</option>
                        <option value="digital" <?= ($valores['tipo'] ?? '') === 'digital' ? 'selected' : '' ?>>Digital</option>
                        <option value="fisica" <?= ($valores['tipo'] ?? '') === 'fisica' ? 'selected' : '' ?>>Física</option>
                    </select>
                </div>

                <div class="form-group" id="campo-stock" style="<?= ($valores['tipo'] ?? '') === 'fisica' ? '' : 'display:none;' ?>">
                    <label for="stock">Stock disponible</label>
                    <input type="number" id="stock" name="stock" min="0" step="1"
                           value="<?= htmlspecialchars($valores['stock'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="precio">Precio</label>
                    <input type="number" id="precio" name="precio" min="0.01" step="0.01" required
                           value="<?= htmlspecialchars($valores['precio'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="imagen">Imagen (JPG, PNG o WEBP, máx. 5 MB)</label>
                    <input type="file" id="imagen" name="imagen" accept=".jpg,.jpeg,.png,.webp" required>
                </div>

                <button type="submit" class="aero-button primary-button">Publicar obra</button>
            </form>
        </section>

    </main>
</body>
</html>
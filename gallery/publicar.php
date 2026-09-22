<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validacion.php';

requerirAutenticacion('../login.php');

$usuarioId = $_SESSION['usuario_id'];


    $directorioPublico = __DIR__ . '/../assets/images/obras/';
    $directorioOriginal = __DIR__ . '/../storage/obras_originales/';

    foreach ([$directorioPublico, $directorioOriginal] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'No se pudo preparar el almacenamiento de imágenes.'];
        }
    }

    $identificador = bin2hex(random_bytes(16));
    $rutaOriginal = $directorioOriginal . $identificador . '.' . $extension;

    // 1) El original se conserva sin recodificar, para no perder calidad de entrega futura.
    if (!move_uploaded_file($tmpPath, $rutaOriginal)) {
        return ['ok' => false, 'error' => 'No se pudo guardar la imagen. Intentá nuevamente.'];
    }

    // 2) Cargar con GD según el tipo real ya validado afuera (finfo + getimagesize)
    $tieneAlfaOrigen = in_array($extension, ['png', 'webp'], true);

    $imagen = match ($extension) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($rutaOriginal),
        'png' => @imagecreatefrompng($rutaOriginal),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($rutaOriginal) : false,
        default => false,
    };

    if ($imagen === false) {
        unlink($rutaOriginal);
        return ['ok' => false, 'error' => 'La imagen está dañada o no se pudo procesar.'];
    }

    // 3) Redimensionar solo si excede el ancho máximo (nunca se agranda)
    $anchoOriginal = imagesx($imagen);
    $altoOriginal = imagesy($imagen);

    if ($anchoOriginal > ANCHO_MAXIMO_PUBLICO) {
        $altoNuevo = (int) round($altoOriginal * (ANCHO_MAXIMO_PUBLICO / $anchoOriginal));
        $redimensionada = imagecreatetruecolor(ANCHO_MAXIMO_PUBLICO, $altoNuevo);

        // Lienzo transparente antes de copiar encima: evita fondos negros con PNG/WebP
        imagealphablending($redimensionada, false);
        imagesavealpha($redimensionada, true);
        $transparente = imagecolorallocatealpha($redimensionada, 0, 0, 0, 127);
        imagefill($redimensionada, 0, 0, $transparente);

        imagealphablending($redimensionada, true);
        imagecopyresampled($redimensionada, $imagen, 0, 0, 0, 0, ANCHO_MAXIMO_PUBLICO, $altoNuevo, $anchoOriginal, $altoOriginal);
        imagedestroy($imagen);
        $imagen = $redimensionada;
    }

    // gallery/publicar.php — REEMPLAZAR SOLO ESTE BLOQUE (paso 4, dentro de procesarImagenObra(), entre el resize y el guardado)
    // 4) Marca de agua diagonal, grande y repetida sin depender de fuente .ttf externa.
    //    El texto se dibuja primero en un lienzo chico (fuente bitmap) y se escala hacia
    //    arriba con imagecopyresampled — así se agranda sin necesitar una fuente TTF.
    $ancho = imagesx($imagen);
    $alto = imagesy($imagen);

    $texto = 'Arte Local';
    $fuente = 5; // fuente bitmap más grande disponible nativamente en GD
    $anchoTextoBase = imagefontwidth($fuente) * strlen($texto);
    $altoTextoBase = imagefontheight($fuente);

    // --- Tile base: el texto "crudo", con trazo doble para simular negrita ---
    $tileBase = imagecreatetruecolor($anchoTextoBase + 2, $altoTextoBase + 2);
    imagealphablending($tileBase, false);
    imagesavealpha($tileBase, true);
    $transparenteBase = imagecolorallocatealpha($tileBase, 0, 0, 0, 127);
    imagefill($tileBase, 0, 0, $transparenteBase);

    imagealphablending($tileBase, true);
    $colorTexto = imagecolorallocatealpha($tileBase, 255, 255, 255, 55); // más opaco que antes (era 90)
    imagestring($tileBase, $fuente, 0, 0, $texto, $colorTexto);
    imagestring($tileBase, $fuente, 1, 0, $texto, $colorTexto); // trazo duplicado = negrita
    imagestring($tileBase, $fuente, 0, 1, $texto, $colorTexto);
    imagestring($tileBase, $fuente, 1, 1, $texto, $colorTexto);

    // --- Escalar el tile hacia arriba: esto es lo que hace el texto "más grande" ---
    $escala = max(3, (int) round($ancho / 400)); // se agranda más todavía en imágenes grandes
    $anchoTileGrande = $anchoTextoBase * $escala;
    $altoTileGrande = $altoTextoBase * $escala;

    $tileGrande = imagecreatetruecolor($anchoTileGrande, $altoTileGrande);
    imagealphablending($tileGrande, false);
    imagesavealpha($tileGrande, true);
    $transparenteGrande = imagecolorallocatealpha($tileGrande, 0, 0, 0, 127);
    imagefill($tileGrande, 0, 0, $transparenteGrande);
    imagecopyresampled($tileGrande, $tileBase, 0, 0, 0, 0, $anchoTileGrande, $altoTileGrande, $anchoTextoBase + 2, $altoTextoBase + 2);
    imagedestroy($tileBase);

    // --- Lienzo diagonal: se tilea el texto ya agrandado, más denso que antes ---
    $diagonal = (int) ceil(sqrt($ancho ** 2 + $alto ** 2));
    $lienzoMarca = imagecreatetruecolor($diagonal, $diagonal);
    imagealphablending($lienzoMarca, false);
    imagesavealpha($lienzoMarca, true);
    $fondoTransparente = imagecolorallocatealpha($lienzoMarca, 0, 0, 0, 127);
    imagefill($lienzoMarca, 0, 0, $fondoTransparente);
    imagealphablending($lienzoMarca, true);

    // Espaciado ajustado: más repeticiones = look "desbordante" en vez de una marca aislada
    $espacioX = (int) ($anchoTileGrande * 1.3);
    $espacioY = (int) ($altoTileGrande * 2.2);

    for ($y = -$altoTileGrande; $y < $diagonal; $y += $espacioY) {
        for ($x = -$anchoTileGrande; $x < $diagonal; $x += $espacioX) {
            imagecopy($lienzoMarca, $tileGrande, $x, $y, 0, 0, $anchoTileGrande, $altoTileGrande);
        }
    }
    imagedestroy($tileGrande);

    $lienzoRotado = imagerotate($lienzoMarca, 30, $fondoTransparente);
    imagedestroy($lienzoMarca);
    imagesavealpha($lienzoRotado, true);

    // Recortar el centro del lienzo rotado al tamaño exacto de la imagen final
    $offsetX = (int) ((imagesx($lienzoRotado) - $ancho) / 2);
    $offsetY = (int) ((imagesy($lienzoRotado) - $alto) / 2);

   // gallery/publicar.php — REEMPLAZAR SOLO ESTE BLOQUE (falta insertar TODO esto entre el final de la marca de agua y el "if (!$guardadoOk)")
    imagealphablending($imagen, true);
    imagecopy($imagen, $lienzoRotado, 0, 0, $offsetX, $offsetY, $ancho, $alto);
    imagedestroy($lienzoRotado);

    // 5) Guardar versión pública: WebP siempre que esté disponible; si no, fallback controlado.
    //    La extensión guardada siempre coincide con el formato real escrito en disco.
    if (function_exists('imagewebp')) {
        $extensionPublica = 'webp';
        imagesavealpha($imagen, true);
        $guardadoOk = imagewebp($imagen, $directorioPublico . $identificador . '.webp', 75);
    } elseif ($tieneAlfaOrigen) {
        $extensionPublica = 'png';
        imagesavealpha($imagen, true);
        $guardadoOk = imagepng($imagen, $directorioPublico . $identificador . '.png', 6);
    } else {
        $extensionPublica = 'jpg';
        // JPEG no soporta transparencia: se compone sobre fondo blanco antes de exportar
        $imagenJpeg = imagecreatetruecolor($ancho, $alto);
        $blanco = imagecolorallocate($imagenJpeg, 255, 255, 255);
        imagefill($imagenJpeg, 0, 0, $blanco);
        imagealphablending($imagenJpeg, true);
        imagecopy($imagenJpeg, $imagen, 0, 0, 0, 0, $ancho, $alto);
        $guardadoOk = imagejpeg($imagenJpeg, $directorioPublico . $identificador . '.jpg', 82);
        imagedestroy($imagenJpeg);
    }

    imagedestroy($imagen);

    if (!$guardadoOk) {
        unlink($rutaOriginal);
        return ['ok' => false, 'error' => 'No se pudo procesar la imagen. Probá con otro archivo.'];
    }

    return [
        'ok' => true,
        'imagen' => 'assets/images/obras/' . $identificador . '.' . $extensionPublica,
        'original' => 'storage/obras_originales/' . $identificador . '.' . $extension,
    ];
}


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

const EXTENSIONES_PERMITIDAS = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
const TAMANO_MAXIMO_IMAGEN = 5 * 1024 * 1024; // 5 MB
const MEGAPIXELES_MAXIMOS = 25_000_000; // ~25 MP, evita reservar memoria con imágenes desproporcionadas

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

    // gallery/publicar.php — REEMPLAZAR SOLO ESTE BLOQUE ("Validación de archivo" completo)
    // --- Validación de archivo ---
    $rutaImagenGuardada = null;
    $rutaOriginalGuardada = null;
    $archivo = $_FILES['imagen'] ?? null;

        if (!$archivo || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        $errores[] = 'Debés subir una imagen de la obra.';
    } else {
        $resultadoArchivo = validarArchivoImagen($archivo, TAMANO_MAXIMO_IMAGEN, EXTENSIONES_PERMITIDAS);

        if (!$resultadoArchivo['ok']) {
            $errores[] = $resultadoArchivo['error'];
        } else {
            $resultadoDimensiones = validarDimensionesImagen($archivo['tmp_name'], MEGAPIXELES_MAXIMOS);

            if (!$resultadoDimensiones['ok']) {
                $errores[] = $resultadoDimensiones['error'];
            } else {
                $resultado = procesarImagenObra($archivo['tmp_name'], $resultadoArchivo['extension']);

                if ($resultado['ok']) {
                    $rutaImagenGuardada = $resultado['imagen'];
                    $rutaOriginalGuardada = $resultado['original'];
                } else {
                    $errores[] = $resultado['error'];
                }
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

        // gallery/publicar.php — REEMPLAZAR SOLO ESTE BLOQUE (limpieza: ahora borra público + original en el catch de PDO)
        } catch (PDOException $e) {
            // No se expone el mensaje real de PDO al usuario.
            // Si los archivos ya se guardaron y el INSERT falla, se eliminan para no dejar huérfanos.
            foreach ([$rutaImagenGuardada, $rutaOriginalGuardada] as $ruta) {
                if ($ruta !== null) {
                    $rutaAbsoluta = __DIR__ . '/../' . $ruta;
                    if (is_file($rutaAbsoluta)) {
                        unlink($rutaAbsoluta);
                    }
                }
            }
            $errores[] = 'No se pudo publicar la obra. Intentá nuevamente.';
        }
    } elseif ($rutaImagenGuardada !== null || $rutaOriginalGuardada !== null) {
        // Datos inválidos pero los archivos ya se habían guardado: se limpian.
        foreach ([$rutaImagenGuardada, $rutaOriginalGuardada] as $ruta) {
            if ($ruta !== null) {
                $rutaAbsoluta = __DIR__ . '/../' . $ruta;
                if (is_file($rutaAbsoluta)) {
                    unlink($rutaAbsoluta);
                }
            }
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
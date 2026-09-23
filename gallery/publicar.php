<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validacion.php';

requerirAutenticacion('../login.php');

$usuarioId = $_SESSION['usuario_id'];
$csrfToken = tokenCSRF();

// Definición de constantes estructurales y límites de seguridad
const EXTENSIONES_PERMITIDAS = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
const TAMANO_MAXIMO_IMAGEN = 5 * 1024 * 1024; // Límite estricto: 5 MB
const MEGAPIXELES_MAXIMOS = 25_000_000; // Prevención de ataques de agotamiento de memoria (Billion Laughs style en imágenes)
if (!defined('ANCHO_MAXIMO_PUBLICO')) {
    define('ANCHO_MAXIMO_PUBLICO', 1200); // Límite de resolución para renderizado en frontend
}
const TIPOS_PERMITIDOS = ['digital', 'fisica'];

/**
 * Procesa la imagen subida, aplica marca de agua, redimensiona y guarda copias.
 * Esta función aísla la carga computacional de la librería GD.
 *
 * @param string $tmpPath Ruta temporal del archivo subido.
 * @param string $extension Extensión validada del archivo.
 * @return array Arreglo asociativo con el estado y las rutas resultantes.
 */
function procesarImagenObra(string $tmpPath, string $extension): array {
    $directorioPublico = __DIR__ . '/../assets/images/obras/';
    $directorioOriginal = __DIR__ . '/../storage/obras_originales/';

    // Aprovisionamiento del sistema de archivos con permisos estrictos
    foreach ([$directorioPublico, $directorioOriginal] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Error de infraestructura: No se pudo preparar el almacenamiento.'];
        }
    }

    // Generación de entropía criptográfica para evitar colisiones de nombres y enumeración
    $identificador = bin2hex(random_bytes(16));
    $rutaOriginal = $directorioOriginal . $identificador . '.' . $extension;

    // 1. Conservación del original (Master File) sin pérdida generacional (Lossless retention)
    if (!move_uploaded_file($tmpPath, $rutaOriginal)) {
        return ['ok' => false, 'error' => 'Fallo de I/O: No se pudo persistir el archivo original.'];
    }

    // 2. Decodificación en memoria según el tipo de compresión
    $tieneAlfaOrigen = in_array($extension, ['png', 'webp'], true);
    
    $imagen = match ($extension) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($rutaOriginal),
        'png'         => @imagecreatefrompng($rutaOriginal),
        'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($rutaOriginal) : false,
        default       => false,
    };

    if ($imagen === false) {
        unlink($rutaOriginal);
        return ['ok' => false, 'error' => 'Análisis fallido: El flujo de datos de la imagen está corrupto o es incompatible.'];
    }

    // 3. Normalización dimensional (Downscaling algorítmico)
    $anchoOriginal = imagesx($imagen);
    $altoOriginal = imagesy($imagen);

    if ($anchoOriginal > ANCHO_MAXIMO_PUBLICO) {
        $altoNuevo = (int) round($altoOriginal * (ANCHO_MAXIMO_PUBLICO / $anchoOriginal));
        $redimensionada = imagecreatetruecolor(ANCHO_MAXIMO_PUBLICO, $altoNuevo);

        // Preservación del canal alfa para evitar artefactos negros en la interpolación
        imagealphablending($redimensionada, false);
        imagesavealpha($redimensionada, true);
        $transparente = imagecolorallocatealpha($redimensionada, 0, 0, 0, 127);
        imagefill($redimensionada, 0, 0, $transparente);

        imagealphablending($redimensionada, true);
        imagecopyresampled($redimensionada, $imagen, 0, 0, 0, 0, ANCHO_MAXIMO_PUBLICO, $altoNuevo, $anchoOriginal, $altoOriginal);
        imagedestroy($imagen);
        $imagen = $redimensionada;
    }

    // 4. Composición de Marca de Agua (Tesselation & Rotational Transformation)
    $ancho = imagesx($imagen);
    $alto = imagesy($imagen);

    $texto = 'Arte Local';
    $fuente = 5; // Fuente interna bitmap optimizada
    $anchoTextoBase = imagefontwidth($fuente) * strlen($texto);
    $altoTextoBase = imagefontheight($fuente);

    // Renderizado del sub-lienzo tipográfico (Bold sintético mediante superposición)
    $tileBase = imagecreatetruecolor($anchoTextoBase + 2, $altoTextoBase + 2);
    imagealphablending($tileBase, false);
    imagesavealpha($tileBase, true);
    $transparenteBase = imagecolorallocatealpha($tileBase, 0, 0, 0, 127);
    imagefill($tileBase, 0, 0, $transparenteBase);

    imagealphablending($tileBase, true);
    $colorTexto = imagecolorallocatealpha($tileBase, 255, 255, 255, 55); 
    imagestring($tileBase, $fuente, 0, 0, $texto, $colorTexto);
    imagestring($tileBase, $fuente, 1, 0, $texto, $colorTexto);
    imagestring($tileBase, $fuente, 0, 1, $texto, $colorTexto);
    imagestring($tileBase, $fuente, 1, 1, $texto, $colorTexto);

    // Amplificación vectorial heurística adaptada a las proporciones de la imagen
    $escala = max(3, (int) round($ancho / 400));
    $anchoTileGrande = $anchoTextoBase * $escala;
    $altoTileGrande = $altoTextoBase * $escala;

    $tileGrande = imagecreatetruecolor($anchoTileGrande, $altoTileGrande);
    imagealphablending($tileGrande, false);
    imagesavealpha($tileGrande, true);
    $transparenteGrande = imagecolorallocatealpha($tileGrande, 0, 0, 0, 127);
    imagefill($tileGrande, 0, 0, $transparenteGrande);
    imagecopyresampled($tileGrande, $tileBase, 0, 0, 0, 0, $anchoTileGrande, $altoTileGrande, $anchoTextoBase + 2, $altoTextoBase + 2);
    imagedestroy($tileBase);

    // Mosaico proyectado matemáticamente sobre la matriz principal
    $diagonal = (int) ceil(sqrt($ancho ** 2 + $alto ** 2));
    $lienzoMarca = imagecreatetruecolor($diagonal, $diagonal);
    imagealphablending($lienzoMarca, false);
    imagesavealpha($lienzoMarca, true);
    $fondoTransparente = imagecolorallocatealpha($lienzoMarca, 0, 0, 0, 127);
    imagefill($lienzoMarca, 0, 0, $fondoTransparente);
    imagealphablending($lienzoMarca, true);

    $espacioX = (int) ($anchoTileGrande * 1.3);
    $espacioY = (int) ($altoTileGrande * 2.2);

    for ($y = -$altoTileGrande; $y < $diagonal; $y += $espacioY) {
        for ($x = -$anchoTileGrande; $x < $diagonal; $x += $espacioX) {
            imagecopy($lienzoMarca, $tileGrande, $x, $y, 0, 0, $anchoTileGrande, $altoTileGrande);
        }
    }
    imagedestroy($tileGrande);

    // Transformación afín de rotación y mapeo de coordenadas centrales
    $lienzoRotado = imagerotate($lienzoMarca, 30, $fondoTransparente);
    imagedestroy($lienzoMarca);
    imagesavealpha($lienzoRotado, true);

    $offsetX = (int) ((imagesx($lienzoRotado) - $ancho) / 2);
    $offsetY = (int) ((imagesy($lienzoRotado) - $alto) / 2);

    imagealphablending($imagen, true);
    imagecopy($imagen, $lienzoRotado, 0, 0, $offsetX, $offsetY, $ancho, $alto);
    imagedestroy($lienzoRotado);

    // 5. Estrategia de exportación (Graceful degradation) basada en capacidades del servidor
    if (function_exists('imagewebp')) {
        $extensionPublica = 'webp';
        imagesavealpha($imagen, true);
        $guardadoOk = imagewebp($imagen, $directorioPublico . $identificador . '.webp', 75);
    } elseif ($tieneAlfaOrigen) {
        $extensionPublica = 'png';
        imagesavealpha($imagen, true);
        $guardadoOk = imagepng($imagen, $directorioPublico . $identificador . '.png', 6); // Zlib compresión moderada
    } else {
        $extensionPublica = 'jpg';
        // Mitigación de pérdida de canal alfa: rasterizado forzado sobre fondo neutro (#FFFFFF)
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
        return ['ok' => false, 'error' => 'Fallo de codificación: Imposible escribir la variante pública en el almacenamiento.'];
    }

    return [
        'ok' => true,
        'imagen' => 'assets/images/obras/' . $identificador . '.' . $extensionPublica,
        'original' => 'storage/obras_originales/' . $identificador . '.' . $extension,
    ];
}

// --- Resolución del Actor (Artista) ---
// Se garantiza tempranamente que el flujo asocie la entidad a un artista válido para prevenir condiciones de carrera
$consultaArtista = $conexion->prepare('SELECT id FROM artistas WHERE usuario_id = :usuario');
$consultaArtista->execute(['usuario' => $usuarioId]);
$artista = $consultaArtista->fetch();

if (!$artista) {
    header('Location: ../artistas/convertirse.php');
    exit;
}

$artistaId = (int) $artista['id'];

// --- Gestión de Estado Flash (Patrón Post/Redirect/Get) ---
$errores = $_SESSION['publicar_errores'] ?? [];
$valores = $_SESSION['publicar_valores'] ?? [];
$exito   = $_SESSION['publicar_exito'] ?? null;
unset($_SESSION['publicar_errores'], $_SESSION['publicar_valores'], $_SESSION['publicar_exito']);

$categorias = $conexion->query('SELECT id, nombre FROM categorias ORDER BY nombre ASC')->fetchAll();

// --- Controlador Transaccional de Peticiones POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errores = [];

    // Sanitización y normalización de entradas de texto perimetrales
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precioRaw = trim($_POST['precio'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $stockRaw = trim($_POST['stock'] ?? '');
    $categoriaIdRaw = $_POST['categoria_id'] ?? '';

    // Snapshot del estado actual para reinyección en el DOM en caso de error
    $valores = [
        'titulo' => $titulo,
        'descripcion' => $descripcion,
        'precio' => $precioRaw,
        'tipo' => $tipo,
        'stock' => $stockRaw,
        'categoria_id' => $categoriaIdRaw,
    ];

    if (!validarCSRF($_POST['csrf_token'] ?? null)) {
        $errores[] = 'Error de validación cruzada (CSRF). Por seguridad, la operación ha sido bloqueada.';
    }

    if ($titulo === '' || mb_strlen($titulo) > 150) {
        $errores[] = 'El título es obligatorio y debe tener un máximo de 150 caracteres para preservar la integridad de la interfaz.';
    }

    if (mb_strlen($descripcion) < 10) {
        $errores[] = 'El manifiesto o descripción exige un mínimo descriptivo de 10 caracteres.';
    }

    $precio = filter_var($precioRaw, FILTER_VALIDATE_FLOAT);
    if ($precio === false || $precio <= 0) {
        $errores[] = 'La cuantía económica debe representarse mediante un valor numérico estricto superior a cero.';
    }

    if (!in_array($tipo, TIPOS_PERMITIDOS, true)) {
        $errores[] = 'Tipología estructural denegada. Modificación del DOM detectada.';
    }

    $stock = null;
    if ($tipo === 'fisica') {
        $stock = filter_var($stockRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($stock === false) {
            $errores[] = 'El inventario de obras físicas requiere un número entero, positivo o cero absoluto.';
        }
    }

    $categoriaId = filter_var($categoriaIdRaw, FILTER_VALIDATE_INT);
    if (empty($categorias)) {
        $errores[] = 'Fallo de orquestación: No se detecta taxonomía en la base de datos (Categorías vacías).';
    } elseif ($categoriaId === false) {
        $errores[] = 'Clasificación taxonómica omitida. Debe seleccionar una categoría.';
    } else {
        $chequeoCategoria = $conexion->prepare('SELECT id FROM categorias WHERE id = :id');
        $chequeoCategoria->execute(['id' => $categoriaId]);
        if (!$chequeoCategoria->fetch()) {
            $errores[] = 'Vulneración de integridad referencial: La categoría elegida ha sido eliminada o es inválida.';
        }
    }

    // --- Pipeline de Procesamiento Binario (Carga de Archivo) ---
    $rutaImagenGuardada = null;
    $rutaOriginalGuardada = null;
    $archivo = $_FILES['imagen'] ?? null;

    if (!$archivo || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        $errores[] = 'El registro maestro requiere forzosamente evidencia fotográfica (Imagen de la obra).';
    } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'Fallo a nivel de protocolo HTTP en la transmisión del binario (Código de error de subida: ' . $archivo['error'] . ').';
    } else {
        // Delegación a la capa de validación externa
        $resultadoArchivo = validarArchivoImagen($archivo, TAMANO_MAXIMO_IMAGEN, EXTENSIONES_PERMITIDAS);

        if (!$resultadoArchivo['ok']) {
            $errores[] = $resultadoArchivo['error'];
        } else {
            $resultadoDimensiones = validarDimensionesImagen($archivo['tmp_name'], MEGAPIXELES_MAXIMOS);

            if (!$resultadoDimensiones['ok']) {
                $errores[] = $resultadoDimensiones['error'];
            } else {
                // Invocación segura del servicio de manipulación, asegurando que las variables temporales ya existen
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

    // --- Resolución Transaccional a Base de Datos ---
    if (empty($errores)) {
        try {
            $conexion->beginTransaction();

            $insertar = $conexion->prepare(
                'INSERT INTO obras (titulo, descripcion, precio, tipo, stock, categoria_id, artista_id, imagen, disponible)
                 VALUES (:titulo, :descripcion, :precio, :tipo, :stock, :categoria, :artista, :imagen, 1)'
            );
            
            $insertar->execute([
                'titulo'      => $titulo,
                'descripcion' => $descripcion,
                'precio'      => number_format($precio, 2, '.', ''),
                'tipo'        => $tipo,
                'stock'       => $stock,
                'categoria'   => $categoriaId,
                'artista'     => $artistaId,
                'imagen'      => $rutaImagenGuardada,
            ]);

            $conexion->commit();

            $_SESSION['publicar_exito'] = 'Despliegue exitoso: La obra ha sido catalogada e indexada en la galería principal.';
            header('Location: publicar.php');
            exit;

        } catch (PDOException $e) {
            $conexion->rollBack();
            // Rollback del sistema de archivos: Compensación en caso de fallo de escritura SQL
            foreach ([$rutaImagenGuardada, $rutaOriginalGuardada] as $ruta) {
                if ($ruta !== null) {
                    $rutaAbsoluta = __DIR__ . '/../' . $ruta;
                    if (is_file($rutaAbsoluta)) {
                        unlink($rutaAbsoluta);
                    }
                }
            }
            // Registro silencioso del error para operaciones de servidor, evitando la filtración de metadata de DB
            error_log("Fallo crítico en inserción de obra (Usuario ID: $usuarioId): " . $e->getMessage());
            $errores[] = 'Inconsistencia de red o fallo de base de datos interrumpió la escritura. Alteraciones revertidas.';
        }
    } else {
        // Ejecución de limpieza predictiva (Garbage Collection explícita) si fallan validaciones tardías tras la manipulación
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
    <title>Desplegar Registro de Obra | Arte Local</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-cliente">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local</div>
        <nav class="panel-header__nav">
            <a href="../dashboard.php">Mi Panel</a>
            <a href="index.php" class="activo">Galería</a>
            <a href="../pedidos/index.php">Mis Transacciones</a>
            <a href="../logout.php">Cierre de Sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">
        <section class="tarjeta">
            <h1>Catalogación de Nueva Obra</h1>
            <p class="instruccion-contextual">Asegúrese de cargar documentación visual nítida (archivos comprimidos menores a 5 MB).</p>

            <?php if ($exito): ?>
                <p class="badge badge--activo"><?= htmlspecialchars($exito) ?></p>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="alerta alerta--error">
                    <strong>Incidencias detectadas durante el despliegue:</strong>
                    <ul>
                        <?php foreach ($errores as $err): ?>
                            <li class="badge badge--agotado"><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="publicar.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-group">
                    <label for="titulo">Denominación / Título de la Obra</label>
                    <input type="text" id="titulo" name="titulo" maxlength="150" required
                           placeholder="Ej. Ocaso en las Salinas"
                           value="<?= htmlspecialchars($valores['titulo'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="descripcion">Manifiesto o Especificaciones (Materialidad y Técnica)</label>
                    <textarea id="descripcion" name="descripcion" rows="5" required 
                              placeholder="Detalle la técnica, las dimensiones reales y cualquier historia vinculada a esta pieza..."><?= htmlspecialchars($valores['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="categoria_id">Taxonomía / Categoría</label>
                    <?php if (empty($categorias)): ?>
                        <p class="badge badge--agotado">Alerta Administrativa: Taxonomía de sistema vacía.</p>
                        <select id="categoria_id" name="categoria_id" disabled>
                            <option value="">Servicio interrumpido temporalmente</option>
                        </select>
                    <?php else: ?>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Definir agrupación estilística</option>
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
                    <label for="tipo">Tipología Estructural</label>
                    <select id="tipo" name="tipo" onchange="document.getElementById('campo-stock').style.display = this.value === 'fisica' ? 'flex' : 'none';" required>
                        <option value="">Seleccione formato base</option>
                        <option value="digital" <?= ($valores['tipo'] ?? '') === 'digital' ? 'selected' : '' ?>>Activo Digital (Descargable / Venta Ilimitada)</option>
                        <option value="fisica" <?= ($valores['tipo'] ?? '') === 'fisica' ? 'selected' : '' ?>>Bien Tangible (Requiere control de inventario y envío)</option>
                    </select>
                </div>

                <div class="form-group" id="campo-stock" style="<?= ($valores['tipo'] ?? '') === 'fisica' ? '' : 'display:none;' ?>">
                    <label for="stock">Disponibilidad de Inventario Físico (Unidades)</label>
                    <input type="number" id="stock" name="stock" min="0" step="1"
                           placeholder="Unidades únicas o reproducciones impresas"
                           value="<?= htmlspecialchars($valores['stock'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="precio">Cuantía de Intercambio (Valor Económico Base)</label>
                    <input type="number" id="precio" name="precio" min="0.01" step="0.01" required
                           placeholder="0.00"
                           value="<?= htmlspecialchars($valores['precio'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="imagen">Evidencia Visual (Soporta JPG, PNG transparente o WebP optimizado - Límite de Cuota: 5 MB)</label>
                    <input type="file" id="imagen" name="imagen" accept=".jpg,.jpeg,.png,.webp" required>
                </div>

                <button type="submit" class="aero-button primary-button">Indexar y Publicar Obra</button>
            </form>
        </section>
    </main>
</body>
</html>
<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validacion.php';

requerirAutenticacion('../login.php');

if (($_SESSION['usuario_rol'] ?? null) !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$csrfToken = tokenCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (validarCSRF($_POST['csrf_token'] ?? null)) {
        $accion = $_POST['accion'] ?? '';
        $obraId = filter_input(INPUT_POST, 'obra_id', FILTER_VALIDATE_INT);

        if ($obraId && $accion === 'toggle') {
            $toggle = $conexion->prepare(
                'UPDATE obras SET disponible = IF(disponible = 1, 0, 1) WHERE id = :id'
            );
            $toggle->execute(['id' => $obraId]);
        }

        if ($obraId && $accion === 'eliminar') {
            // --- Borrar archivo físico solo después de confirmar que la fila existe ---
            $buscarImagen = $conexion->prepare('SELECT imagen FROM obras WHERE id = :id');
            $buscarImagen->execute(['id' => $obraId]);
            $fila = $buscarImagen->fetch();

            if ($fila) {
                $borrar = $conexion->prepare('DELETE FROM obras WHERE id = :id');
                $borrar->execute(['id' => $obraId]);

                if (!empty($fila['imagen'])) {
                    $rutaAbsoluta = __DIR__ . '/../' . $fila['imagen'];
                    if (is_file($rutaAbsoluta)) {
                        unlink($rutaAbsoluta);
                    }
                }
            }
        }

        // --- Alta o edición de obra: mismas funciones de validación/procesamiento
        //     de imagen que gallery/publicar.php (centralizadas en includes/validacion.php) ---
        if ($accion === 'guardar') {
            $esEdicion = $obraId !== null && $obraId !== false && $obraId > 0;

            $erroresGuardar = [];

            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precioRaw = trim($_POST['precio'] ?? '');
            $tipo = $_POST['tipo'] ?? '';
            $stockRaw = trim($_POST['stock'] ?? '');
            $categoriaIdRaw = $_POST['categoria_id'] ?? '';
            $artistaIdRaw = $_POST['artista_id'] ?? '';

            $valoresGuardar = [
                'obra_id' => $esEdicion ? $obraId : null,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'precio' => $precioRaw,
                'tipo' => $tipo,
                'stock' => $stockRaw,
                'categoria_id' => $categoriaIdRaw,
                'artista_id' => $artistaIdRaw,
            ];

            if ($titulo === '' || mb_strlen($titulo) > 150) {
                $erroresGuardar[] = 'El título es obligatorio y debe tener hasta 150 caracteres.';
            }

            if (mb_strlen($descripcion) < 10) {
                $erroresGuardar[] = 'La descripción debe tener al menos 10 caracteres.';
            }

            $precio = filter_var($precioRaw, FILTER_VALIDATE_FLOAT);
            if ($precio === false || $precio <= 0) {
                $erroresGuardar[] = 'El precio debe ser un número mayor a cero.';
            }

            if (!in_array($tipo, TIPOS_PERMITIDOS, true)) {
                $erroresGuardar[] = 'El tipo de obra no es válido.';
            }

            $stock = null;
            if ($tipo === 'fisica') {
                $stock = filter_var($stockRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                if ($stock === false) {
                    $erroresGuardar[] = 'El stock debe ser un número entero mayor o igual a cero.';
                }
            }

            $categoriaId = filter_var($categoriaIdRaw, FILTER_VALIDATE_INT);
            if ($categoriaId === false) {
                $erroresGuardar[] = 'Seleccioná una categoría.';
            } else {
                $chequeoCategoria = $conexion->prepare('SELECT id FROM categorias WHERE id = :id');
                $chequeoCategoria->execute(['id' => $categoriaId]);
                if (!$chequeoCategoria->fetch()) {
                    $erroresGuardar[] = 'La categoría seleccionada no existe.';
                }
            }

            $artistaId = filter_var($artistaIdRaw, FILTER_VALIDATE_INT);
            if ($artistaId === false) {
                $erroresGuardar[] = 'Seleccioná un artista.';
            } else {
                $chequeoArtista = $conexion->prepare('SELECT id FROM artistas WHERE id = :id');
                $chequeoArtista->execute(['id' => $artistaId]);
                if (!$chequeoArtista->fetch()) {
                    $erroresGuardar[] = 'El artista seleccionado no existe.';
                }
            }

            // --- Imagen: obligatoria en alta; opcional en edición (si no se sube, se conserva la actual) ---
            $rutaImagenGuardada = null;
            $rutaOriginalGuardada = null;
            $rutaImagenAnterior = null;
            $archivoImagen = $_FILES['imagen'] ?? null;
            $hayArchivoNuevo = $archivoImagen && $archivoImagen['error'] !== UPLOAD_ERR_NO_FILE;

            if (!$esEdicion && !$hayArchivoNuevo) {
                $erroresGuardar[] = 'Debés subir una imagen de la obra.';
            } elseif ($hayArchivoNuevo) {
                $resultadoArchivo = validarArchivoImagen($archivoImagen, TAMANO_MAXIMO_IMAGEN, EXTENSIONES_PERMITIDAS);

                if (!$resultadoArchivo['ok']) {
                    $erroresGuardar[] = $resultadoArchivo['error'];
                } else {
                    $resultadoDimensiones = validarDimensionesImagen($archivoImagen['tmp_name'], MEGAPIXELES_MAXIMOS);

                    if (!$resultadoDimensiones['ok']) {
                        $erroresGuardar[] = $resultadoDimensiones['error'];
                    } else {
                        $resultadoProcesado = procesarImagenObra($archivoImagen['tmp_name'], $resultadoArchivo['extension']);

                        if ($resultadoProcesado['ok']) {
                            $rutaImagenGuardada = $resultadoProcesado['imagen'];
                            $rutaOriginalGuardada = $resultadoProcesado['original'];
                        } else {
                            $erroresGuardar[] = $resultadoProcesado['error'];
                        }
                    }
                }
            }

            if ($esEdicion && empty($erroresGuardar)) {
                $buscarActual = $conexion->prepare('SELECT imagen FROM obras WHERE id = :id');
                $buscarActual->execute(['id' => $obraId]);
                $filaActual = $buscarActual->fetch();

                if (!$filaActual) {
                    $erroresGuardar[] = 'La obra que intentás editar ya no existe.';
                } elseif ($hayArchivoNuevo) {
                    $rutaImagenAnterior = $filaActual['imagen'];
                }
            }

            if (empty($erroresGuardar)) {
                try {
                    if ($esEdicion) {
                        $camposImagen = $rutaImagenGuardada !== null ? ', imagen = :imagen' : '';
                        $actualizar = $conexion->prepare(
                            "UPDATE obras SET titulo = :titulo, descripcion = :descripcion, precio = :precio,
                                    tipo = :tipo, stock = :stock, categoria_id = :categoria, artista_id = :artista
                                    $camposImagen
                             WHERE id = :id"
                        );
                        $paramsActualizar = [
                            'titulo' => $titulo,
                            'descripcion' => $descripcion,
                            'precio' => number_format($precio, 2, '.', ''),
                            'tipo' => $tipo,
                            'stock' => $stock,
                            'categoria' => $categoriaId,
                            'artista' => $artistaId,
                            'id' => $obraId,
                        ];
                        if ($rutaImagenGuardada !== null) {
                            $paramsActualizar['imagen'] = $rutaImagenGuardada;
                        }
                        $actualizar->execute($paramsActualizar);

                        // --- Limpieza best-effort de la imagen reemplazada: público (ruta conocida)
                        //     + original (derivado por convención de nombre, igual que publicar.php) ---
                        if ($rutaImagenAnterior) {
                            $rutaAbsolutaAnterior = __DIR__ . '/../' . $rutaImagenAnterior;
                            if (is_file($rutaAbsolutaAnterior)) {
                                unlink($rutaAbsolutaAnterior);
                            }
                            $identificadorAnterior = pathinfo($rutaImagenAnterior, PATHINFO_FILENAME);
                            foreach (glob(__DIR__ . '/../storage/obras_originales/' . $identificadorAnterior . '.*') as $original) {
                                unlink($original);
                            }
                        }
                    } else {
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
                    }

                    $_SESSION['obras_exito'] = $esEdicion ? 'Obra actualizada correctamente.' : 'Obra publicada correctamente.';
                    header('Location: obras.php');
                    exit;

                } catch (PDOException $e) {
                    // No se expone el mensaje real de PDO. Si la imagen nueva ya se guardó
                    // y el UPDATE/INSERT falla, se limpia para no dejar archivos huérfanos.
                    foreach ([$rutaImagenGuardada, $rutaOriginalGuardada] as $ruta) {
                        if ($ruta !== null) {
                            $rutaAbsoluta = __DIR__ . '/../' . $ruta;
                            if (is_file($rutaAbsoluta)) {
                                unlink($rutaAbsoluta);
                            }
                        }
                    }
                    $erroresGuardar[] = 'No se pudo guardar la obra. Intentá nuevamente.';
                }
            } elseif ($rutaImagenGuardada !== null || $rutaOriginalGuardada !== null) {
                foreach ([$rutaImagenGuardada, $rutaOriginalGuardada] as $ruta) {
                    if ($ruta !== null) {
                        $rutaAbsoluta = __DIR__ . '/../' . $ruta;
                        if (is_file($rutaAbsoluta)) {
                            unlink($rutaAbsoluta);
                        }
                    }
                }
            }

            $_SESSION['obras_errores'] = $erroresGuardar;
            $_SESSION['obras_valores'] = $valoresGuardar;
            header('Location: obras.php' . ($esEdicion ? '?editar=' . $obraId : '?nueva=1'));
            exit;
        }
    }

    header('Location: obras.php');
    exit;
}

$categorias = $conexion->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();

$categoriaId = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT);
if ($categoriaId === false || ($categoriaId !== null && $categoriaId <= 0)) {
    $categoriaId = null;
}

$tipoParam = $_GET['tipo'] ?? null;
if (!in_array($tipoParam, ['digital', 'fisica'], true)) {
    $tipoParam = null;
}

$estadoParam = $_GET['estado'] ?? null;
if (!in_array($estadoParam, ['activa', 'pausada'], true)) {
    $estadoParam = null;
}

$busqueda = trim($_GET['q'] ?? '');
if (mb_strlen($busqueda) > 100) {
    $busqueda = mb_substr($busqueda, 0, 100);
}

$ordenesPermitidos = [
    'recientes'   => 'o.created_at DESC',
    'antiguas'    => 'o.created_at ASC',
    'precio_asc'  => 'o.precio ASC',
    'precio_desc' => 'o.precio DESC',
    'titulo'      => 'o.titulo ASC',
];
$ordenParam = $_GET['orden'] ?? 'recientes';
if (!array_key_exists($ordenParam, $ordenesPermitidos)) {
    $ordenParam = 'recientes';
}

$condiciones = [];
$params = [];

if ($categoriaId !== null) {
    $condiciones[] = 'o.categoria_id = :categoria';
    $params['categoria'] = $categoriaId;
}
if ($tipoParam !== null) {
    $condiciones[] = 'o.tipo = :tipo';
    $params['tipo'] = $tipoParam;
}
if ($estadoParam !== null) {
    $condiciones[] = 'o.disponible = :disponible';
    $params['disponible'] = $estadoParam === 'activa' ? 1 : 0;
}
if ($busqueda !== '') {
    $condiciones[] = 'o.titulo LIKE :busqueda';
    $params['busqueda'] = '%' . $busqueda . '%';
}

$sql = 'SELECT o.id, o.titulo, o.precio, o.tipo, o.stock, o.disponible, o.imagen,
               a.nombre AS artista_nombre, c.nombre AS categoria_nombre
        FROM obras o
        INNER JOIN artistas a ON a.id = o.artista_id
        INNER JOIN categorias c ON c.id = o.categoria_id';

if (!empty($condiciones)) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}

$sql .= ' ORDER BY ' . $ordenesPermitidos[$ordenParam];

$consultaObras = $conexion->prepare($sql);
$consultaObras->execute($params);
$obras = $consultaObras->fetchAll();

// --- Datos para el formulario de alta/edición: artistas (dropdown) y flash de sesión ---
$artistas = $conexion->query('SELECT id, nombre FROM artistas ORDER BY nombre')->fetchAll();

$exitoObra = $_SESSION['obras_exito'] ?? null;
$erroresObra = $_SESSION['obras_errores'] ?? [];
$valoresObra = $_SESSION['obras_valores'] ?? [];
unset($_SESSION['obras_exito'], $_SESSION['obras_errores'], $_SESSION['obras_valores']);

// --- ?editar=ID precarga el formulario con la obra existente; ?nueva=1 lo abre vacío ---
$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
$obraEditando = null;
if ($editarId) {
    $consultaEditar = $conexion->prepare(
        'SELECT id, titulo, descripcion, precio, tipo, stock, categoria_id, artista_id, imagen
         FROM obras WHERE id = :id'
    );
    $consultaEditar->execute(['id' => $editarId]);
    $obraEditando = $consultaEditar->fetch();
}

$mostrarFormulario = isset($_GET['nueva']) || $obraEditando !== null || !empty($erroresObra);

// Precarga: primero valores de un intento fallido (flash), si no hay, la obra en edición.
$valoresForm = !empty($valoresObra) ? $valoresObra : ($obraEditando ?: []);
$obraIdForm = $valoresForm['obra_id'] ?? ($obraEditando['id'] ?? null);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obras | Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-admin">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local — Admin</div>
        <nav class="panel-header__nav">
            <a href="dashboard.php">Panel</a>
            <a href="obras.php" class="activo">Obras</a>
            <a href="pedidos.php">Pedidos</a>
            <a href="usuarios.php">Usuarios</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">

        <section class="tarjeta">
            <h1>
                Obras (<?= count($obras) ?>)
                <a href="obras.php?nueva=1" class="aero-button primary-button" style="float:right;">+ Nueva obra</a>
            </h1>

            <?php if ($exitoObra): ?>
                <p class="badge badge--activo"><?= htmlspecialchars($exitoObra) ?></p>
            <?php endif; ?>

            <?php if ($mostrarFormulario): ?>

                <?php if (!empty($erroresObra)): ?>
                    <ul>
                        <?php foreach ($erroresObra as $err): ?>
                            <li class="badge badge--agotado"><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="obras.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="accion" value="guardar">
                    <?php if ($obraIdForm): ?>
                        <input type="hidden" name="obra_id" value="<?= (int) $obraIdForm ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="titulo">Título</label>
                        <input type="text" id="titulo" name="titulo" maxlength="150" required
                               value="<?= htmlspecialchars($valoresForm['titulo'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="4" required><?= htmlspecialchars($valoresForm['descripcion'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="categoria_id">Categoría</label>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Elegí una categoría</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= (int) $categoria['id'] ?>"
                                    <?= (isset($valoresForm['categoria_id']) && (int) $valoresForm['categoria_id'] === (int) $categoria['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoria['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="artista_id">Artista</label>
                        <select id="artista_id" name="artista_id" required>
                            <option value="">Elegí un artista</option>
                            <?php foreach ($artistas as $artistaOpcion): ?>
                                <option value="<?= (int) $artistaOpcion['id'] ?>"
                                    <?= (isset($valoresForm['artista_id']) && (int) $valoresForm['artista_id'] === (int) $artistaOpcion['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($artistaOpcion['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" onchange="document.getElementById('campo-stock').style.display = this.value === 'fisica' ? 'flex' : 'none';" required>
                            <option value="">Elegí un tipo</option>
                            <option value="digital" <?= ($valoresForm['tipo'] ?? '') === 'digital' ? 'selected' : '' ?>>Digital</option>
                            <option value="fisica" <?= ($valoresForm['tipo'] ?? '') === 'fisica' ? 'selected' : '' ?>>Física</option>
                        </select>
                    </div>

                    <div class="form-group" id="campo-stock" style="<?= ($valoresForm['tipo'] ?? '') === 'fisica' ? '' : 'display:none;' ?>">
                        <label for="stock">Stock disponible</label>
                        <input type="number" id="stock" name="stock" min="0" step="1"
                               value="<?= htmlspecialchars((string) ($valoresForm['stock'] ?? '')) ?>">
                    </div>

                    <div class="form-group">
                        <label for="precio">Precio</label>
                        <input type="number" id="precio" name="precio" min="0.01" step="0.01" required
                               value="<?= htmlspecialchars((string) ($valoresForm['precio'] ?? '')) ?>">
                    </div>

                    <div class="form-group">
                        <label for="imagen">
                            Imagen (JPG, PNG o WEBP, máx. 5 MB)
                            <?= $obraIdForm ? '— dejar vacío para conservar la actual' : '' ?>
                        </label>
                        <?php if ($obraEditando && !empty($obraEditando['imagen'])): ?>
                            <img src="<?= htmlspecialchars('../' . $obraEditando['imagen']) ?>" alt="" style="max-width:120px;display:block;margin-bottom:0.5rem;">
                        <?php endif; ?>
                        <input type="file" id="imagen" name="imagen" accept=".jpg,.jpeg,.png,.webp" <?= $obraIdForm ? '' : 'required' ?>>
                    </div>

                    <button type="submit" class="aero-button primary-button"><?= $obraIdForm ? 'Guardar cambios' : 'Publicar obra' ?></button>
                    <a href="obras.php" class="aero-button secondary-button">Cancelar</a>
                </form>

            <?php endif; ?>

            <?php if (empty($obras)): ?>
                <div class="estado-vacio">
                    <span class="estado-vacio__icono">🖼️</span>
                    <p>No hay obras publicadas todavía.</p>
                </div>
            <?php else: ?>
                <div class="tabla-aero-wrap">
                    <table class="tabla-aero">
                        <thead>
                            <tr>
                                <th>Obra</th>
                                <th>Artista</th>
                                <th>Categoría</th>
                                <th>Tipo</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($obras as $obra): ?>
                                <tr>
                                    <td><?= htmlspecialchars($obra['titulo']) ?></td>
                                    <td><?= htmlspecialchars($obra['artista_nombre']) ?></td>
                                    <td><?= htmlspecialchars($obra['categoria_nombre']) ?></td>
                                    <td><?= $obra['tipo'] === 'digital' ? 'Digital' : 'Física' ?></td>
                                    <td>$<?= number_format((float) $obra['precio'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= $obra['disponible'] ? 'badge--activo' : 'badge--agotado' ?>">
                                            <?= $obra['disponible'] ? 'Activa' : 'Pausada' ?>
                                        </span>
                                    </td>
                                    <td style="display:flex; gap:0.5rem;">
                                        <a href="obras.php?editar=<?= (int) $obra['id'] ?>" class="aero-button secondary-button">Editar</a>
                                        <form method="post" action="obras.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="toggle">
                                            <input type="hidden" name="obra_id" value="<?= (int) $obra['id'] ?>">
                                            <button type="submit" class="aero-button secondary-button">
                                                <?= $obra['disponible'] ? 'Suspender' : 'Reactivar' ?>
                                            </button>
                                        </form>
                                        <form method="post" action="obras.php" onsubmit="return confirm('¿Eliminar esta obra permanentemente?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="obra_id" value="<?= (int) $obra['id'] ?>">
                                            <button type="submit" class="aero-button secondary-button">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
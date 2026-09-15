<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

$csrfToken = tokenCSRF();

// gallery/index.php — REEMPLAZAR SOLO ESTE BLOQUE (filtro categoría + orden)
$categoriaId = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT);
if ($categoriaId === false || ($categoriaId !== null && $categoriaId <= 0)) {
    $categoriaId = null;
}

// --- Filtro por tipo: whitelist fija ---
$tiposPermitidos = ['digital', 'fisica'];
$tipoParam = $_GET['tipo'] ?? null;
if (!in_array($tipoParam, $tiposPermitidos, true)) {
    $tipoParam = null;
}

// --- Ordenamiento: whitelist fija, nunca se interpola el valor del GET directamente ---
$ordenesPermitidos = [
    'recientes'   => 'o.created_at DESC',
    'antiguas'    => 'o.created_at ASC',
    'precio_asc'  => 'o.precio ASC',
    'precio_desc' => 'o.precio DESC',
    'nombre'      => 'o.titulo ASC',
];
$ordenParam = $_GET['orden'] ?? 'recientes';
if (!array_key_exists($ordenParam, $ordenesPermitidos)) {
    $ordenParam = 'recientes';
}
$ordenSql = $ordenesPermitidos[$ordenParam];

// --- Categorías para el filtro (siempre todas, para poblar el <select>) ---
$categorias = $conexion
    ->query('SELECT id, nombre FROM categorias ORDER BY nombre')
    ->fetchAll();

// --- Obras: JOIN con artistas para el nombre, filtro opcional por categoría ---
$sql = 'SELECT
            o.id,
            o.titulo,
            o.precio,
            o.tipo,
            o.stock,
            o.disponible,
            o.imagen,
            a.nombre AS artista_nombre
        FROM obras o
        INNER JOIN artistas a ON a.id = o.artista_id';

// gallery/index.php — REEMPLAZAR SOLO ESTE BLOQUE (WHERE dinámico + bind tipo)
if ($categoriaId !== null) {
    $sql .= ' WHERE o.categoria_id = :categoria';
}

if ($tipoParam !== null) {
    $sql .= $categoriaId !== null ? ' AND o.tipo = :tipo' : ' WHERE o.tipo = :tipo';
}

$sql .= ' ORDER BY ' . $ordenSql;

$consulta = $conexion->prepare($sql);

$params = [];
if ($categoriaId !== null) {
    $params['categoria'] = $categoriaId;
}
if ($tipoParam !== null) {
    $params['tipo'] = $tipoParam;
}
$consulta->execute($params);

$obras = $consulta->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galería | Arte Local</title>
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

        <section class="tarjeta tarjeta--filtros">

            <h2>Explorar galería</h2>

            <form method="get" action="index.php" class="galeria-filtros">

                <div class="form-group">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo" onchange="this.form.submit()">
                        <option value="">Digital y física</option>
                        <option value="digital" <?= $tipoParam === 'digital' ? 'selected' : '' ?>>Digital</option>
                        <option value="fisica" <?= $tipoParam === 'fisica' ? 'selected' : '' ?>>Física</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="orden">Ordenar por</label>
                    <select id="orden" name="orden" onchange="this.form.submit()">
                        <option value="recientes" <?= $ordenParam === 'recientes' ? 'selected' : '' ?>>Más recientes</option>
                        <option value="antiguas" <?= $ordenParam === 'antiguas' ? 'selected' : '' ?>>Más antiguas</option>
                        <option value="precio_asc" <?= $ordenParam === 'precio_asc' ? 'selected' : '' ?>>Precio: menor a mayor</option>
                        <option value="precio_desc" <?= $ordenParam === 'precio_desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
                        <option value="nombre" <?= $ordenParam === 'nombre' ? 'selected' : '' ?>>Nombre</option>
                    </select>
                </div>

                <noscript>
                    <button type="submit" class="aero-button secondary-button">Aplicar</button>
                </noscript>

            </form>

        </section>


        <?php if (empty($obras)): ?>

            <section class="tarjeta estado-vacio">
                <span class="estado-vacio__icono">🖼️</span>
                <p>No hay obras que coincidan con este filtro por el momento.</p>
            </section>

        <?php else: ?>

            <section class="galeria-grid">

                <?php foreach ($obras as $indice => $obra):

                    $stockFinito  = $obra['stock'] !== null;
                    $disponible   = (bool) $obra['disponible'] && (!$stockFinito || (int) $obra['stock'] > 0);
                    $esDestacada  = ($indice % 5 === 0);
                    $tipoEtiqueta = $obra['tipo'] === 'digital' ? 'Digital' : 'Física';
                    $precioTexto  = '$' . number_format((float) $obra['precio'], 2, ',', '.');

                ?>

                    <article class="tarjeta obra-card <?= $esDestacada ? 'obra-card--destacada' : '' ?>">

                        <div class="obra-card__imagen">
                            <a href="obra.php?id=<?= (int) $obra['id'] ?>">
                                <?php if (!empty($obra['imagen'])): ?>
                                    <img
                                        src="<?= htmlspecialchars('../' . $obra['imagen']) ?>"
                                        alt="<?= htmlspecialchars($obra['titulo']) ?>"
                                        <?= $indice === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                                    >
                                <?php else: ?>
                                    <div class="obra-card__imagen-placeholder"></div>
                                <?php endif; ?>
                            </a>

                            <span class="badge <?= $disponible ? 'badge--activo' : 'badge--agotado' ?> obra-card__badge">
                                <?= $disponible ? 'Disponible' : 'Agotado' ?>
                            </span>
                        </div>

                        <div class="obra-card__info">

                            <span class="obra-card__tipo"><?= $tipoEtiqueta ?></span>

                            <h3 class="obra-card__titulo">
                                <a href="obra.php?id=<?= (int) $obra['id'] ?>"><?= htmlspecialchars($obra['titulo']) ?></a>
                            </h3>

                            <p class="obra-card__artista"><?= htmlspecialchars($obra['artista_nombre']) ?></p>
                            <div class="obra-card__pie">

                                <span class="obra-card__precio"><?= $precioTexto ?></span>

                                <form method="post" action="../carrito/index.php" class="obra-card__form">

                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="obra_id" value="<?= (int) $obra['id'] ?>">
                                    <input type="hidden" name="accion" value="agregar">

                                    <button
                                        type="submit"
                                        class="aero-button primary-button obra-card__boton"
                                        <?= $disponible ? '' : 'disabled' ?>
                                    >
                                        Agregar al carrito
                                    </button>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </section>

        <?php endif; ?>

    </main>

</body>
</html>
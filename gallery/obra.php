<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

$csrfToken = tokenCSRF();

$obraId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$obraId || $obraId <= 0) {
    header('Location: index.php');
    exit;
}

$consulta = $conexion->prepare(
    'SELECT o.id, o.titulo, o.descripcion, o.precio, o.tipo, o.stock, o.disponible,
            o.imagen, o.artista_id, a.nombre AS artista_nombre, c.nombre AS categoria_nombre
     FROM obras o
     INNER JOIN artistas a ON a.id = o.artista_id
     INNER JOIN categorias c ON c.id = o.categoria_id
     WHERE o.id = :id'
);
$consulta->execute(['id' => $obraId]);
$obra = $consulta->fetch();

if (!$obra) {
    header('Location: index.php');
    exit;
}

$stockFinito = $obra['stock'] !== null;
$disponible  = (bool) $obra['disponible'] && (!$stockFinito || (int) $obra['stock'] > 0);
$precioTexto = '$' . number_format((float) $obra['precio'], 2, ',', '.');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($obra['titulo']) ?> | Arte Local</title>
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

            <p><a href="index.php">&larr; Volver a la galería</a></p>

            <h1><?= htmlspecialchars($obra['titulo']) ?></h1>

            <?php if (!empty($obra['imagen'])): ?>
                <img src="<?= htmlspecialchars('../' . $obra['imagen']) ?>" alt="<?= htmlspecialchars($obra['titulo']) ?>" style="max-width:100%;border-radius:12px;">
            <?php endif; ?>

            <p>
                <a href="../artistas/perfil.php?id=<?= (int) $obra['artista_id'] ?>">Por <?= htmlspecialchars($obra['artista_nombre']) ?></a>
                &middot; <?= htmlspecialchars($obra['categoria_nombre']) ?>
                &middot; <?= $obra['tipo'] === 'digital' ? 'Digital' : 'Física' ?>
            </p>

            <p>
                <span class="badge <?= $disponible ? 'badge--activo' : 'badge--agotado' ?>">
                    <?= $disponible ? 'Disponible' : 'Agotado' ?>
                </span>
                &nbsp; <strong><?= $precioTexto ?></strong>
            </p>

            <?php if (!empty($obra['descripcion'])): ?>
                <p><?= nl2br(htmlspecialchars($obra['descripcion'])) ?></p>
            <?php endif; ?>

            <form method="post" action="../carrito/index.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="obra_id" value="<?= (int) $obra['id'] ?>">
                <input type="hidden" name="accion" value="agregar">
                <button type="submit" class="aero-button primary-button" <?= $disponible ? '' : 'disabled' ?>>
                    Agregar al carrito
                </button>
            </form>

        </section>

    </main>
</body>
</html>
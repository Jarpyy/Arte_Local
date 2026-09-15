<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

$artistaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$artistaId || $artistaId <= 0) {
    header('Location: ../gallery/index.php');
    exit;
}

$consultaArtista = $conexion->prepare('SELECT id, nombre, bio, foto FROM artistas WHERE id = :id');
$consultaArtista->execute(['id' => $artistaId]);
$artista = $consultaArtista->fetch();

if (!$artista) {
    header('Location: ../gallery/index.php');
    exit;
}

$consultaObras = $conexion->prepare(
    'SELECT id, titulo, imagen, precio FROM obras WHERE artista_id = :artista ORDER BY created_at DESC'
);
$consultaObras->execute(['artista' => $artistaId]);
$obras = $consultaObras->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($artista['nombre']) ?> | Arte Local</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-cliente">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local</div>
        <nav class="panel-header__nav">
            <a href="../dashboard.php">Mi Panel</a>
            <a href="../gallery/index.php" class="activo">Galería</a>
            <a href="../pedidos/index.php">Mis Pedidos</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">

        <section class="tarjeta">
            <p><a href="../gallery/index.php">&larr; Volver a la galería</a></p>

            <?php if (!empty($artista['foto'])): ?>
                <img
                    src="<?= htmlspecialchars('../' . $artista['foto']) ?>"
                    alt="Foto de <?= htmlspecialchars($artista['nombre']) ?>"
                    style="width:140px;height:140px;object-fit:cover;border-radius:50%;"
                >
            <?php else: ?>
                <div style="width:140px;height:140px;border-radius:50%;background:linear-gradient(135deg,#bfe6fb,#7fc4ea);"></div>
            <?php endif; ?>

            <h1><?= htmlspecialchars($artista['nombre']) ?></h1>
            <?php if (!empty($artista['bio'])): ?>
                <p><?= nl2br(htmlspecialchars($artista['bio'])) ?></p>
            <?php endif; ?>
        </section>

        <section class="tarjeta">
            <h2>Obras publicadas</h2>
            <?php if (empty($obras)): ?>
                <div class="estado-vacio">
                    <span class="estado-vacio__icono">🖼️</span>
                    <p>Este artista todavía no tiene obras publicadas.</p>
                </div>
            <?php else: ?>
                <ul>
                    <?php foreach ($obras as $obra): ?>
                        <li>
                            <a href="../gallery/obra.php?id=<?= (int) $obra['id'] ?>">
                                <?= htmlspecialchars($obra['titulo']) ?>
                            </a>
                            — $<?= number_format((float) $obra['precio'], 2, ',', '.') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </main>
</body>
</html>
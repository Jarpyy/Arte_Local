<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requerirAutenticacion('login.php');

$rolActual = $_SESSION['usuario_rol'] ?? null;

// --- Gatekeeping: admin nunca ve esta vista, se redirige de inmediato ---
if ($rolActual === 'admin') {
    header('Location: admin/dashboard.php');
    exit;
}

// --- Cualquier rol distinto de 'admin' se trata como cliente ---
// (Si en el futuro aparecen más roles, este es el único punto que
// habría que tocar para agregar una nueva rama de enrutamiento.)
$nombreUsuario = htmlspecialchars($_SESSION['usuario_nombre'] ?? '', ENT_QUOTES, 'UTF-8');

// --- ¿Este usuario tiene además un perfil de artista vinculado? ---
$consultaArtista = $conexion->prepare('SELECT id FROM artistas WHERE usuario_id = :usuario_id');
$consultaArtista->execute(['usuario_id' => $_SESSION['usuario_id']]);
$artistaPropio = $consultaArtista->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel | Arte Local</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body class="dashboard-cliente">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local</div>
         <nav class="panel-header__nav">
            <a href="dashboard.php" class="activo">Mi Panel</a>
            <a href="gallery/index.php">Galería</a>
            <a href="pedidos/index.php">Mis Pedidos</a>
            <a href="logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">

        <section class="tarjeta tarjeta--bienvenida">
            <h1>Hola, <?= $nombreUsuario ?> </h1>
            <p>Este es tu panel personal. Desde aquí podés revisar tus pedidos y el estado de tus envíos.</p>
        </section>

        <!--
            Las secciones "Mis Pedidos" y "Mis Envíos" con sus datos reales
            (consulta a `pedidos` filtrada por usuario_id, snapshot de
            `pedido_items` y estado de `envios`) se implementan en el
            siguiente paso, dentro de pedidos/index.php, siguiendo la
            documentación de la sección 3. Aquí solo se muestran los
            accesos directos para no duplicar lógica de datos en dos
            archivos distintos.
        -->
            <h2>Accesos rápidos</h2>
            <div class="accesos-grid">
                <a class="acceso-card" href="gallery/index.php">
                    <span class="acceso-card__icono">🖼️</span>
                    <span class="acceso-card__titulo">Galería</span>
                    <span class="acceso-card__descripcion">Explorá obras de la comunidad</span>
                </a>
                <a class="acceso-card" href="pedidos/index.php">
                    <span class="acceso-card__icono">📦</span>
                    <span class="acceso-card__titulo">Mis Pedidos</span>
                    <span class="acceso-card__descripcion">Historial y estado de tus compras</span>
                </a>
                <a class="acceso-card" href="pedidos/index.php#envios">
                    <span class="acceso-card__icono">🚚</span>
                    <span class="acceso-card__titulo">Mis Envíos</span>
                    <span class="acceso-card__descripcion">Seguimiento de retiro o domicilio</span>
                </a>
                <?php if ($artistaPropio): ?>
                    <a class="acceso-card" href="artistas/perfil.php?id=<?= (int) $artistaPropio['id'] ?>">
                        <span class="acceso-card__icono">🎨</span>
                        <span class="acceso-card__titulo">Mi perfil de artista</span>
                        <span class="acceso-card__descripcion">Así te ven en tu página pública</span>
                    </a>
                    <a class="acceso-card" href="gallery/publicar.php">
                        <span class="acceso-card__icono">➕</span>
                        <span class="acceso-card__titulo">Publicar obra</span>
                        <span class="acceso-card__descripcion">Subí una nueva obra a la galería</span>
                    </a>
                <?php else: ?>
                    <a class="acceso-card" href="artistas/convertirse.php">
                        <span class="acceso-card__icono">🖌️</span>
                        <span class="acceso-card__titulo">Quiero vender mis obras</span>
                        <span class="acceso-card__descripcion">Creá tu perfil de artista para empezar a publicar</span>
                    </a>
                <?php endif; ?>
            </div>
        </section>

    </main>

</body>
</html>
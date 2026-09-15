<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

$usuarioId = $_SESSION['usuario_id'];

$consultaArtista = $conexion->prepare('SELECT id, nombre FROM artistas WHERE usuario_id = :usuario');
$consultaArtista->execute(['usuario' => $usuarioId]);
$artista = $consultaArtista->fetch();

if (!$artista) {
    header('Location: convertirse.php');
    exit;
}

$artistaId = (int) $artista['id'];
$csrfToken = tokenCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (validarCSRF($_POST['csrf_token'] ?? null) && ($_POST['accion'] ?? '') === 'toggle') {
        $obraId = filter_input(INPUT_POST, 'obra_id', FILTER_VALIDATE_INT);

        if ($obraId) {
            // --- Ownership: solo puede togglear obras del propio artista ---
            $toggle = $conexion->prepare(
                'UPDATE obras SET disponible = IF(disponible = 1, 0, 1)
                 WHERE id = :id AND artista_id = :artista'
            );
            $toggle->execute(['id' => $obraId, 'artista' => $artistaId]);
        }
    }

    header('Location: mis-obras.php');
    exit;
}

$consultaObras = $conexion->prepare(
    'SELECT id, titulo, imagen, precio, tipo, stock, disponible
     FROM obras
     WHERE artista_id = :artista
     ORDER BY created_at DESC'
);
$consultaObras->execute(['artista' => $artistaId]);
$obras = $consultaObras->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis obras | Arte Local</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-cliente">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local</div>
        <nav class="panel-header__nav">
            <a href="../dashboard.php">Mi Panel</a>
            <a href="../gallery/index.php">Galería</a>
            <a href="../gallery/publicar.php">Publicar obra</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">

        <section class="tarjeta">
            <h1>Mis obras</h1>
            <p><a href="../gallery/publicar.php">+ Publicar una nueva obra</a></p>

            <?php if (empty($obras)): ?>
                <div class="estado-vacio">
                    <span class="estado-vacio__icono">🖼️</span>
                    <p>Todavía no publicaste ninguna obra.</p>
                </div>
            <?php else: ?>
                <div class="tabla-aero-wrap">
                    <table class="tabla-aero">
                        <thead>
                            <tr>
                                <th>Obra</th>
                                <th>Tipo</th>
                                <th>Stock</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($obras as $obra): ?>
                                <tr>
                                    <td>
                                        <a href="../gallery/obra.php?id=<?= (int) $obra['id'] ?>">
                                            <?= htmlspecialchars($obra['titulo']) ?>
                                        </a>
                                    </td>
                                    <td><?= $obra['tipo'] === 'digital' ? 'Digital' : 'Física' ?></td>
                                    <td><?= $obra['stock'] === null ? '—' : (int) $obra['stock'] ?></td>
                                    <td>$<?= number_format((float) $obra['precio'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= $obra['disponible'] ? 'badge--activo' : 'badge--agotado' ?>">
                                            <?= $obra['disponible'] ? 'Activa' : 'Pausada' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" action="mis-obras.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="toggle">
                                            <input type="hidden" name="obra_id" value="<?= (int) $obra['id'] ?>">
                                            <button type="submit" class="aero-button secondary-button">
                                                <?= $obra['disponible'] ? 'Pausar' : 'Reactivar' ?>
                                            </button>
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
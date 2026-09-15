<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

$csrfToken = tokenCSRF();
$usuarioId = $_SESSION['usuario_id'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Solicitud no válida.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'agregar') {
            $obraId = filter_input(INPUT_POST, 'obra_id', FILTER_VALIDATE_INT);

            if ($obraId) {
                // --- Solo se agregan obras existentes y disponibles; nunca se confía en el POST ---
                $chequeo = $conexion->prepare(
                    'SELECT id, stock, disponible FROM obras WHERE id = :id'
                );
                $chequeo->execute(['id' => $obraId]);
                $obraExiste = $chequeo->fetch();

                $stockFinito = $obraExiste && $obraExiste['stock'] !== null;
                $disponible  = $obraExiste
                    && (bool) $obraExiste['disponible']
                    && (!$stockFinito || (int) $obraExiste['stock'] > 0);

                if ($disponible) {
                    $insertar = $conexion->prepare(
                        'INSERT INTO carrito_items (usuario_id, obra_id, cantidad)
                         VALUES (:usuario, :obra, 1)
                         ON DUPLICATE KEY UPDATE cantidad = cantidad + 1'
                    );
                    $insertar->execute(['usuario' => $usuarioId, 'obra' => $obraId]);
                } else {
                    $error = 'Esa obra ya no está disponible.';
                }
            }
        }

        if ($accion === 'eliminar') {
            $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);

            if ($itemId) {
                // --- Ownership check: solo borra items del propio usuario ---
                $borrar = $conexion->prepare(
                    'DELETE FROM carrito_items WHERE id = :id AND usuario_id = :usuario'
                );
                $borrar->execute(['id' => $itemId, 'usuario' => $usuarioId]);
            }
        }
    }

    header('Location: index.php');
    exit;
}

$items = $conexion->prepare(
    'SELECT ci.id AS item_id, ci.cantidad, o.id AS obra_id, o.titulo, o.precio, o.imagen
     FROM carrito_items ci
     INNER JOIN obras o ON o.id = ci.obra_id
     WHERE ci.usuario_id = :usuario
     ORDER BY ci.added_at DESC'
);
$items->execute(['usuario' => $usuarioId]);
$itemsCarrito = $items->fetchAll();

$total = 0;
foreach ($itemsCarrito as $item) {
    $total += (float) $item['precio'] * (int) $item['cantidad'];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Carrito | Arte Local</title>
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
            <h1>Mi carrito</h1>

            <?php if ($error): ?>
                <p class="badge badge--agotado"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <?php if (empty($itemsCarrito)): ?>
                <div class="estado-vacio">
                    <span class="estado-vacio__icono">🛒</span>
                    <p>Tu carrito está vacío.</p>
                    <p><a href="../gallery/index.php">Ir a la galería &rarr;</a></p>
                </div>
            <?php else: ?>
                <div class="tabla-aero-wrap">
                    <table class="tabla-aero">
                        <thead>
                            <tr>
                                <th>Obra</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itemsCarrito as $item): ?>
                                <tr>
                                    <td>
                                        <a href="../gallery/obra.php?id=<?= (int) $item['obra_id'] ?>">
                                            <?= htmlspecialchars($item['titulo']) ?>
                                        </a>
                                    </td>
                                    <td><?= (int) $item['cantidad'] ?></td>
                                    <td>$<?= number_format((float) $item['precio'], 2, ',', '.') ?></td>
                                    <td>
                                        <form method="post" action="index.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                            <button type="submit" class="aero-button secondary-button">Quitar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p><strong>Total: $<?= number_format($total, 2, ',', '.') ?></strong></p>
                <p><a href="../checkout/index.php" class="aero-button primary-button">Finalizar compra</a></p>
            <?php endif; ?>

        </section>

    </main>
</body>
</html>
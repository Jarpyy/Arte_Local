<?php
// checkout/comprobante.php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

$usuarioId = $_SESSION['usuario_id'];

$pedidoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$pedidoId || $pedidoId <= 0) {
    header('Location: ../pedidos/index.php');
    exit;
}

// --- Ownership: el pedido tiene que pertenecer al usuario en sesión.
//     Cambiar ?id=123 por ?id=124 nunca debe mostrar un pedido ajeno. ---
$consultaPedido = $conexion->prepare(
    'SELECT p.id, p.estado, p.total, p.created_at, u.nombre AS comprador_nombre, u.email AS comprador_email
     FROM pedidos p
     INNER JOIN usuarios u ON u.id = p.usuario_id
     WHERE p.id = :id AND p.usuario_id = :usuario'
);
$consultaPedido->execute(['id' => $pedidoId, 'usuario' => $usuarioId]);
$pedido = $consultaPedido->fetch();

if (!$pedido) {
    header('Location: ../pedidos/index.php');
    exit;
}

$consultaItems = $conexion->prepare(
    'SELECT titulo, tipo, precio_unitario, cantidad FROM pedido_items WHERE pedido_id = :pedido'
);
$consultaItems->execute(['pedido' => $pedidoId]);
$items = $consultaItems->fetchAll();

$consultaEnvio = $conexion->prepare('SELECT direccion, metodo FROM envios WHERE pedido_id = :pedido');
$consultaEnvio->execute(['pedido' => $pedidoId]);
$envio = $consultaEnvio->fetch();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante #<?= (int) $pedido['id'] ?> | Arte Local</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-cliente">

    <header class="panel-header no-imprimir">
        <div class="panel-header__brand">Arte Local</div>
        <nav class="panel-header__nav">
            <a href="../dashboard.php">Mi Panel</a>
            <a href="../gallery/index.php">Galería</a>
            <a href="../pedidos/index.php" class="activo">Mis Pedidos</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">

        <section class="tarjeta no-imprimir">
            <h1>¡Compra confirmada!</h1>
            <p>Pago simulado aprobado. Guardá o imprimí tu comprobante.</p>
            <div class="acciones-comprobante">
                <button type="button" onclick="window.print()" class="aero-button primary-button">Imprimir</button>
                <button type="button" onclick="window.print()" class="aero-button secondary-button">Descargar PDF</button>
                <a href="../pedidos/index.php" class="aero-button secondary-button">Ver mis pedidos</a>
            </div>
        </section>

        <section class="tarjeta comprobante">
            <header class="comprobante__encabezado">
                <div>
                    <h2>Arte Local</h2>
                    <p>Comprobante de compra</p>
                </div>
                <div class="comprobante__numero">
                    <span>Pedido</span>
                    <strong>#<?= (int) $pedido['id'] ?></strong>
                </div>
            </header>

            <div class="comprobante__datos">
                <div>
                    <span>Comprador</span>
                    <strong><?= htmlspecialchars($pedido['comprador_nombre'] ?? $pedido['comprador_email']) ?></strong>
                </div>
                <div>
                    <span>Fecha</span>
                    <strong><?= htmlspecialchars($pedido['created_at']) ?></strong>
                </div>
                <div>
                    <span>Estado del pago</span>
                    <strong>Pago simulado aprobado</strong>
                </div>
                <?php if ($envio): ?>
                    <div>
                        <span>Entrega</span>
                        <strong><?= $envio['metodo'] === 'domicilio' ? 'A domicilio' : 'Retiro en local' ?> — <?= htmlspecialchars($envio['direccion']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tabla-aero-wrap">
                <table class="tabla-aero">
                    <thead>
                        <tr>
                            <th>Obra</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['titulo']) ?></td>
                                <td><?= $item['tipo'] === 'digital' ? 'Digital' : 'Física' ?></td>
                                <td><?= (int) $item['cantidad'] ?></td>
                                <td>$<?= number_format((float) $item['precio_unitario'], 2, ',', '.') ?></td>
                                <td>$<?= number_format((float) $item['precio_unitario'] * (int) $item['cantidad'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="comprobante__total">
                <span>Total</span>
                <strong>$<?= number_format((float) $pedido['total'], 2, ',', '.') ?></strong>
            </div>
        </section>

    </main>
</body>
</html>
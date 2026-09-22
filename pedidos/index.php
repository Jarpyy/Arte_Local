<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

$usuarioId = $_SESSION['usuario_id'];

$consulta = $conexion->prepare(
    'SELECT p.id, p.estado, p.total, p.created_at, e.metodo, e.estado AS estado_envio
     FROM pedidos p
     LEFT JOIN envios e ON e.pedido_id = p.id
     WHERE p.usuario_id = :usuario
     ORDER BY p.created_at DESC'
);
$consulta->execute(['usuario' => $usuarioId]);
$pedidos = $consulta->fetchAll();

$badgePedido = [
    'pendiente' => 'badge--pendiente',
    'pagado'    => 'badge--activo',
    'cancelado' => 'badge--agotado',
];

$badgeEnvio = [
    'pendiente'  => 'badge--pendiente',
    'preparando' => 'badge--preparando',
    'enviado'    => 'badge--enviado',
    'entregado'  => 'badge--activo',
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos | Arte Local</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-cliente">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local</div>
        <nav class="panel-header__nav">
            <a href="../dashboard.php">Mi Panel</a>
            <a href="../gallery/index.php">Galería</a>
            <a href="index.php" class="activo">Mis Pedidos</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">

        <section class="tarjeta">
            <h1>Mis pedidos</h1>

            <?php if (empty($pedidos)): ?>
                <div class="estado-vacio">
                    <span class="estado-vacio__icono">📦</span>
                    <p>Todavía no tenés pedidos.</p>
                    <p><a href="../gallery/index.php">Ir a la galería &rarr;</a></p>
                </div>
            <?php else: ?>
                <div class="tabla-aero-wrap" id="envios">
                    <table class="tabla-aero">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Envío</th>
                                <th>Comprobante</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td>#<?= (int) $pedido['id'] ?></td>
                                    <td><?= htmlspecialchars($pedido['created_at']) ?></td>
                                    <td>$<?= number_format((float) $pedido['total'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= $badgePedido[$pedido['estado']] ?? '' ?>">
                                            <?= htmlspecialchars($pedido['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($pedido['estado_envio']): ?>
                                            <span class="badge <?= $badgeEnvio[$pedido['estado_envio']] ?? '' ?>">
                                                <?= htmlspecialchars($pedido['estado_envio']) ?>
                                            </span>
                                        <?php else: ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../checkout/comprobante.php?id=<?= (int) $pedido['id'] ?>">Ver comprobante</a>
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
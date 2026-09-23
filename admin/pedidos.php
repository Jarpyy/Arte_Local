<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirRol('admin');

$pedidos = $conexion->query(
    'SELECT p.id, p.estado, p.total, p.created_at, u.nombre AS usuario_nombre, u.email
     FROM pedidos p
     INNER JOIN usuarios u ON u.id = p.usuario_id
     ORDER BY p.created_at DESC'
)->fetchAll();

$badgePedido = [
    'pendiente' => 'badge--pendiente',
    'pagado'    => 'badge--activo',
    'cancelado' => 'badge--agotado',
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos | Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-admin">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local — Admin</div>
        <nav class="panel-header__nav">
            <a href="dashboard.php">Panel</a>
            <a href="obras.php">Obras</a>
            <a href="pedidos.php" class="activo">Pedidos</a>
            <a href="categorias.php">Categorías</a>
            <a href="usuarios.php">Usuarios</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">
        <section class="tarjeta">
            <h1>Pedidos (<?= count($pedidos) ?>)</h1>

            <?php if (empty($pedidos)): ?>
                <div class="estado-vacio">
                    <span class="estado-vacio__icono">📦</span>
                    <p>No hay pedidos todavía.</p>
                </div>
            <?php else: ?>
                <div class="tabla-aero-wrap">
                    <table class="tabla-aero">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td>#<?= (int) $pedido['id'] ?></td>
                                    <td><?= htmlspecialchars($pedido['usuario_nombre'] ?? $pedido['email']) ?></td>
                                    <td><?= htmlspecialchars($pedido['created_at']) ?></td>
                                    <td>$<?= number_format((float) $pedido['total'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= $badgePedido[$pedido['estado']] ?? '' ?>">
                                            <?= htmlspecialchars($pedido['estado']) ?>
                                        </span>
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
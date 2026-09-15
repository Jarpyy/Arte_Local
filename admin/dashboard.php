<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

// --- Gatekeeping inverso: un cliente no puede ver el panel admin ---
if (($_SESSION['usuario_rol'] ?? null) !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$totalObras = (int) $conexion->query('SELECT COUNT(*) FROM obras')->fetchColumn();
$totalPedidos = (int) $conexion->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();
$totalUsuarios = (int) $conexion->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin | Arte Local</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-admin">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local — Admin</div>
         <nav class="panel-header__nav">
            <a href="dashboard.php" class="activo">Panel</a>
            <a href="obras.php">Obras</a>
            <a href="pedidos.php">Pedidos</a>
            <a href="usuarios.php">Usuarios</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">
        <section class="kpis-grid">
            <a class="kpi-card" href="obras.php">
                <span class="kpi-card__icono">🖼️</span>
                <span class="kpi-card__valor"><?= $totalObras ?></span>
                <span class="kpi-card__etiqueta">Obras</span>
            </a>
            <a class="kpi-card" href="pedidos.php">
                <span class="kpi-card__icono">📦</span>
                <span class="kpi-card__valor"><?= $totalPedidos ?></span>
                <span class="kpi-card__etiqueta">Pedidos</span>
            </a>
            <a class="kpi-card" href="usuarios.php">
                <span class="kpi-card__icono">👤</span>
                <span class="kpi-card__valor"><?= $totalUsuarios ?></span>
                <span class="kpi-card__etiqueta">Usuarios</span>
            </a>
        </section>
    </main>
</body>
</html>
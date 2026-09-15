<?php

require_once __DIR__ . '/../includes/auth.php';

requerirAutenticacion('../login.php');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar compra | Arte Local</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-cliente">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local</div>
        <nav class="panel-header__nav">
            <a href="../dashboard.php">Mi Panel</a>
            <a href="../gallery/index.php">Galería</a>
            <a href="../pedidos/index.php">Mis Pedidos</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">
        <section class="tarjeta estado-vacio">
            <span class="estado-vacio__icono">🚧</span>
            <p>El proceso de pago todavía no está disponible.</p>
            <p><a href="../carrito/index.php">&larr; Volver al carrito</a></p>
        </section>
    </main>
</body>
</html>
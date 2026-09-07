<?php

require_once __DIR__ . '/includes/auth.php';

requerirAutenticacion('login.php');

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Arte Local</title>
</head>
<body>

    <h1>Sesión activa</h1>

    <p>
        Usuario: <?= htmlspecialchars($_SESSION['usuario_nombre']) ?>
        (<?= htmlspecialchars($_SESSION['usuario_email']) ?>)
        — Rol: <?= htmlspecialchars($_SESSION['usuario_rol']) ?>
    </p>

    <a href="logout.php">Cerrar sesión</a>

</body>
</html>
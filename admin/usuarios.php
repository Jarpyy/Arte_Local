<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirRol('admin');

$adminEnSesion = (int) $_SESSION['usuario_id'];
$csrfToken     = tokenCSRF();
$errores       = $_SESSION['usr_errores'] ?? [];
$exito         = $_SESSION['usr_exito']   ?? null;
unset($_SESSION['usr_errores'], $_SESSION['usr_exito']);

// ─── Controlador POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarCSRF($_POST['csrf_token'] ?? null)) {
        $_SESSION['usr_errores'] = ['Token de seguridad inválido. Reintentá la operación.'];
        header('Location: usuarios.php');
        exit;
    }

    $accion     = $_POST['accion']     ?? '';
    $usuarioId  = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);

    if (!$usuarioId) {
        $_SESSION['usr_errores'] = ['ID de usuario inválido.'];
        header('Location: usuarios.php');
        exit;
    }

    // ── Anti self-lock: ninguna acción sobre el propio admin en sesión ─────────
    if ($usuarioId === $adminEnSesion) {
        $_SESSION['usr_errores'] = ['No podés aplicar esta acción sobre tu propia cuenta. Iniciá sesión con otro admin para hacerlo.'];
        header('Location: usuarios.php');
        exit;
    }

    // ── CAMBIAR ROL ────────────────────────────────────────────────────────────
    if ($accion === 'toggle_rol') {
        // Leer el rol actual del usuario objetivo
        $stmt = $conexion->prepare('SELECT rol FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $usuarioId]);
        $fila = $stmt->fetch();

        if (!$fila) {
            $_SESSION['usr_errores'] = ['El usuario no existe.'];
            header('Location: usuarios.php');
            exit;
        }

        $nuevoRol = $fila['rol'] === 'admin' ? 'cliente' : 'admin';

        $update = $conexion->prepare('UPDATE usuarios SET rol = :rol WHERE id = :id');
        $update->execute(['rol' => $nuevoRol, 'id' => $usuarioId]);

        $etiqueta = $nuevoRol === 'admin' ? 'ascendido a Admin' : 'degradado a Cliente';
        $_SESSION['usr_exito'] = "Usuario #{$usuarioId} {$etiqueta} correctamente.";
        header('Location: usuarios.php');
        exit;
    }

    // ── ELIMINAR ───────────────────────────────────────────────────────────────
    if ($accion === 'eliminar') {

        // Bloqueo por historial de compras (registros financieros inmutables)
        $chequeo = $conexion->prepare('SELECT COUNT(*) FROM pedidos WHERE usuario_id = :id');
        $chequeo->execute(['id' => $usuarioId]);
        $totalPedidos = (int) $chequeo->fetchColumn();

        if ($totalPedidos > 0) {
            $_SESSION['usr_errores'] = [
                "No se puede eliminar este usuario porque tiene {$totalPedidos} pedido(s) en el historial de compras. "
                . 'Los registros financieros son inmutables.'
            ];
            header('Location: usuarios.php');
            exit;
        }

        try {
            $stmt = $conexion->prepare('DELETE FROM usuarios WHERE id = :id');
            $stmt->execute(['id' => $usuarioId]);
            $_SESSION['usr_exito'] = "Usuario #{$usuarioId} eliminado correctamente.";
        } catch (PDOException $e) {
            $_SESSION['usr_errores'] = ['Error al eliminar el usuario. Verificá que no tenga datos relacionados.'];
            error_log('Admin usuarios eliminar: ' . $e->getMessage());
        }

        header('Location: usuarios.php');
        exit;
    }
}

// ─── Listar usuarios con conteo de pedidos ────────────────────────────────────
$usuarios = $conexion->query(
    'SELECT u.id, u.nombre, u.email, u.rol, u.created_at,
            COUNT(p.id) AS total_pedidos
     FROM usuarios u
     LEFT JOIN pedidos p ON p.usuario_id = u.id
     GROUP BY u.id, u.nombre, u.email, u.rol, u.created_at
     ORDER BY u.created_at DESC'
)->fetchAll();

$badgeRol = ['admin' => 'badge--activo', 'cliente' => 'badge--pendiente'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | Panel Admin</title>
    <meta name="description" content="Gestión de usuarios registrados en Arte Local.">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-admin">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local — Admin</div>
        <nav class="panel-header__nav">
            <a href="dashboard.php">Panel</a>
            <a href="obras.php">Obras</a>
            <a href="pedidos.php">Pedidos</a>
            <a href="categorias.php">Categorías</a>
            <a href="usuarios.php" class="activo">Usuarios</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">
        <section class="tarjeta">
            <h1>Usuarios (<?= count($usuarios) ?>)</h1>

            <?php if ($exito): ?>
                <div class="alerta alerta--exito" role="alert">
                    <?= htmlspecialchars($exito) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($errores)): ?>
                <div class="alerta alerta--error" role="alert">
                    <?php foreach ($errores as $err): ?>
                        <p><?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($usuarios)): ?>
                <div class="estado-vacio">
                    <span class="estado-vacio__icono">👤</span>
                    <p>No hay usuarios registrados.</p>
                </div>
            <?php else: ?>
                <div class="tabla-aero-wrap">
                    <table class="tabla-aero">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th style="text-align:center;">Pedidos</th>
                                <th>Alta</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <?php $esSelf = (int) $usuario['id'] === $adminEnSesion; ?>
                                <tr <?= $esSelf ? 'class="fila-self"' : '' ?>>
                                    <td><?= (int) $usuario['id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($usuario['nombre'] ?? '—') ?>
                                        <?php if ($esSelf): ?>
                                            <span class="badge badge--pendiente" title="Tu cuenta">Vos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($usuario['email']) ?></td>
                                    <td>
                                        <span class="badge <?= $badgeRol[$usuario['rol']] ?? '' ?>">
                                            <?= htmlspecialchars($usuario['rol']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge <?= $usuario['total_pedidos'] > 0 ? 'badge--activo' : 'badge--agotado' ?>">
                                            <?= (int) $usuario['total_pedidos'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(substr($usuario['created_at'], 0, 10)) ?></td>
                                    <td style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                        <?php if ($esSelf): ?>
                                            <span style="color:var(--dash-blue-dark); font-size:0.8rem; align-self:center;">
                                                (tu cuenta)
                                            </span>
                                        <?php else: ?>
                                            <!-- Toggle de rol -->
                                            <form method="post" action="usuarios.php"
                                                  onsubmit="return confirmarRol(this, '<?= htmlspecialchars($usuario['rol']) ?>', <?= (int) $usuario['id'] ?>)">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="accion" value="toggle_rol">
                                                <input type="hidden" name="usuario_id" value="<?= (int) $usuario['id'] ?>">
                                                <button type="submit" class="aero-button secondary-button">
                                                    <?= $usuario['rol'] === 'admin' ? 'Degradar' : 'Hacer Admin' ?>
                                                </button>
                                            </form>
                                            <!-- Eliminar -->
                                            <form method="post" action="usuarios.php"
                                                  onsubmit="return confirmarEliminar(this, <?= (int) $usuario['total_pedidos'] ?>, <?= (int) $usuario['id'] ?>)">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="usuario_id" value="<?= (int) $usuario['id'] ?>">
                                                <button type="submit" class="aero-button secondary-button">
                                                    Eliminar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        function confirmarRol(form, rolActual, id) {
            const nuevoRol = rolActual === 'admin' ? 'cliente' : 'admin';
            return confirm(
                'Vas a cambiar el rol del usuario #' + id + ' de "'
                + rolActual + '" a "' + nuevoRol + '".\n¿Confirmás?'
            );
        }

        function confirmarEliminar(form, totalPedidos, id) {
            if (totalPedidos > 0) {
                alert(
                    'El usuario #' + id + ' tiene ' + totalPedidos
                    + ' pedido(s) en su historial y no puede eliminarse.\n'
                    + 'Los registros financieros son inmutables.'
                );
                return false;
            }
            return confirm(
                '¿Eliminar permanentemente al usuario #' + id + '?\n'
                + 'Esta acción no se puede deshacer.'
            );
        }
    </script>
</body>
</html>
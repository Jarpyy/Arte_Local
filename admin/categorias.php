<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirRol('admin');

$csrfToken = tokenCSRF();
$errores   = $_SESSION['cat_errores'] ?? [];
$exito     = $_SESSION['cat_exito']   ?? null;
unset($_SESSION['cat_errores'], $_SESSION['cat_exito']);

// ─── Controlador POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarCSRF($_POST['csrf_token'] ?? null)) {
        $_SESSION['cat_errores'] = ['Token de seguridad inválido. Reintentá la operación.'];
        header('Location: categorias.php');
        exit;
    }

    $accion = $_POST['accion'] ?? '';

    // ── CREAR ──────────────────────────────────────────────────────────────────
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');

        if ($nombre === '' || mb_strlen($nombre) > 80) {
            $_SESSION['cat_errores'] = ['El nombre es obligatorio y no puede superar los 80 caracteres.'];
            header('Location: categorias.php');
            exit;
        }

        try {
            $stmt = $conexion->prepare('INSERT INTO categorias (nombre) VALUES (:nombre)');
            $stmt->execute(['nombre' => $nombre]);
            $_SESSION['cat_exito'] = 'Categoría "' . htmlspecialchars($nombre) . '" creada correctamente.';
        } catch (PDOException $e) {
            // Código 23000 = violación de UNIQUE (nombre duplicado)
            if ($e->getCode() === '23000') {
                $_SESSION['cat_errores'] = ['Ya existe una categoría con ese nombre.'];
            } else {
                $_SESSION['cat_errores'] = ['Error al crear la categoría. Intentá nuevamente.'];
                error_log('Admin categorias crear: ' . $e->getMessage());
            }
        }

        header('Location: categorias.php');
        exit;
    }

    // ── EDITAR ─────────────────────────────────────────────────────────────────
    if ($accion === 'editar') {
        $categoriaId = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);
        $nombre      = trim($_POST['nombre'] ?? '');

        if (!$categoriaId || $nombre === '' || mb_strlen($nombre) > 80) {
            $_SESSION['cat_errores'] = ['Datos inválidos para renombrar la categoría.'];
            header('Location: categorias.php');
            exit;
        }

        try {
            $stmt = $conexion->prepare('UPDATE categorias SET nombre = :nombre WHERE id = :id');
            $stmt->execute(['nombre' => $nombre, 'id' => $categoriaId]);
            $_SESSION['cat_exito'] = 'Categoría renombrada a "' . htmlspecialchars($nombre) . '".';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $_SESSION['cat_errores'] = ['Ya existe una categoría con ese nombre.'];
            } else {
                $_SESSION['cat_errores'] = ['Error al renombrar la categoría.'];
                error_log('Admin categorias editar: ' . $e->getMessage());
            }
        }

        header('Location: categorias.php');
        exit;
    }

    // ── ELIMINAR ───────────────────────────────────────────────────────────────
    if ($accion === 'eliminar') {
        $categoriaId = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);

        if (!$categoriaId) {
            $_SESSION['cat_errores'] = ['ID de categoría inválido.'];
            header('Location: categorias.php');
            exit;
        }

        // Verificar integridad referencial antes de intentar borrar
        $chequeo = $conexion->prepare('SELECT COUNT(*) FROM obras WHERE categoria_id = :id');
        $chequeo->execute(['id' => $categoriaId]);
        $totalObras = (int) $chequeo->fetchColumn();

        if ($totalObras > 0) {
            $_SESSION['cat_errores'] = [
                "No se puede eliminar esta categoría porque tiene {$totalObras} obra(s) asociada(s). "
                . 'Reasigná las obras a otra categoría antes de eliminarla.'
            ];
            header('Location: categorias.php');
            exit;
        }

        try {
            $stmt = $conexion->prepare('DELETE FROM categorias WHERE id = :id');
            $stmt->execute(['id' => $categoriaId]);
            $_SESSION['cat_exito'] = 'Categoría eliminada correctamente.';
        } catch (PDOException $e) {
            $_SESSION['cat_errores'] = ['Error al eliminar la categoría.'];
            error_log('Admin categorias eliminar: ' . $e->getMessage());
        }

        header('Location: categorias.php');
        exit;
    }
}

// ─── Leer categorías con conteo de obras ──────────────────────────────────────
$categorias = $conexion->query(
    'SELECT c.id, c.nombre, COUNT(o.id) AS total_obras
     FROM categorias c
     LEFT JOIN obras o ON o.categoria_id = c.id
     GROUP BY c.id, c.nombre
     ORDER BY c.nombre ASC'
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías | Panel Admin</title>
    <meta name="description" content="Gestión de categorías de obras en Arte Local.">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .edit-row { display: none; }
        .edit-row.activo { display: table-row; }
        .edit-row td { padding: 0.5rem 1rem 1rem; background: rgba(255,255,255,0.55); }
        .edit-inline { display: flex; gap: 0.5rem; align-items: center; }
        .edit-inline input[type="text"] { flex: 1; }
    </style>
</head>
<body class="dashboard-admin">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local — Admin</div>
        <nav class="panel-header__nav">
            <a href="dashboard.php">Panel</a>
            <a href="obras.php">Obras</a>
            <a href="pedidos.php">Pedidos</a>
            <a href="categorias.php" class="activo">Categorías</a>
            <a href="usuarios.php">Usuarios</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">

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

        <div style="display:grid; grid-template-columns:1fr 340px; gap:1.5rem; align-items:start;">

            <!-- ── Tabla ── -->
            <section class="tarjeta">
                <h1>Categorías (<?= count($categorias) ?>)</h1>

                <?php if (empty($categorias)): ?>
                    <div class="estado-vacio">
                        <span class="estado-vacio__icono">🗂️</span>
                        <p>No hay categorías cargadas todavía.</p>
                    </div>
                <?php else: ?>
                    <div class="tabla-aero-wrap">
                        <table class="tabla-aero" id="tabla-categorias">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th style="text-align:center;">Obras</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorias as $cat): ?>
                                    <tr id="fila-<?= (int) $cat['id'] ?>">
                                        <td><?= htmlspecialchars($cat['nombre']) ?></td>
                                        <td style="text-align:center;">
                                            <span class="badge <?= $cat['total_obras'] > 0 ? 'badge--activo' : 'badge--agotado' ?>">
                                                <?= (int) $cat['total_obras'] ?>
                                            </span>
                                        </td>
                                        <td style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                            <button type="button"
                                                    class="aero-button secondary-button"
                                                    onclick="toggleEditar(<?= (int) $cat['id'] ?>, <?= htmlspecialchars(json_encode($cat['nombre'])) ?>)">
                                                Renombrar
                                            </button>
                                            <form method="post" action="categorias.php"
                                                  onsubmit="return confirmarEliminar(this, <?= (int) $cat['total_obras'] ?>)">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="categoria_id" value="<?= (int) $cat['id'] ?>">
                                                <button type="submit" class="aero-button secondary-button">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <!-- Fila inline de edición -->
                                    <tr class="edit-row" id="edit-<?= (int) $cat['id'] ?>">
                                        <td colspan="3">
                                            <form method="post" action="categorias.php" class="edit-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="accion" value="editar">
                                                <input type="hidden" name="categoria_id" value="<?= (int) $cat['id'] ?>">
                                                <input type="text" name="nombre" maxlength="80" required
                                                       id="input-edit-<?= (int) $cat['id'] ?>"
                                                       placeholder="Nuevo nombre">
                                                <button type="submit" class="aero-button primary-button">Guardar</button>
                                                <button type="button" class="aero-button secondary-button"
                                                        onclick="toggleEditar(<?= (int) $cat['id'] ?>, null)">
                                                    Cancelar
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

            <!-- ── Formulario de alta ── -->
            <section class="tarjeta">
                <h2>Nueva categoría</h2>
                <form method="post" action="categorias.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="accion" value="crear">
                    <div class="form-group">
                        <label for="nombre-nueva">Nombre</label>
                        <input type="text" id="nombre-nueva" name="nombre"
                               maxlength="80" required
                               placeholder="Ej. Grabado, Acuarela…">
                    </div>
                    <button type="submit" class="aero-button primary-button" style="width:100%;">
                        Crear categoría
                    </button>
                </form>
            </section>

        </div>
    </main>

    <script>
        function toggleEditar(id, nombreActual) {
            const editRow = document.getElementById('edit-' + id);
            const input   = document.getElementById('input-edit-' + id);

            // Cerrar cualquier otra fila de edición abierta
            document.querySelectorAll('.edit-row.activo').forEach(row => {
                if (row.id !== 'edit-' + id) row.classList.remove('activo');
            });

            if (nombreActual !== null) {
                editRow.classList.add('activo');
                input.value = nombreActual;
                input.focus();
                input.select();
            } else {
                editRow.classList.remove('activo');
            }
        }

        function confirmarEliminar(form, totalObras) {
            if (totalObras > 0) {
                alert(
                    'Esta categoría tiene ' + totalObras + ' obra(s) asociada(s) y no puede eliminarse.\n'
                    + 'Reasigná las obras a otra categoría primero.'
                );
                return false;
            }
            return confirm('¿Eliminar esta categoría permanentemente? Esta acción no se puede deshacer.');
        }
    </script>
</body>
</html>

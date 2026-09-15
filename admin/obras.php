<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

if (($_SESSION['usuario_rol'] ?? null) !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$csrfToken = tokenCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (validarCSRF($_POST['csrf_token'] ?? null)) {
        $accion = $_POST['accion'] ?? '';
        $obraId = filter_input(INPUT_POST, 'obra_id', FILTER_VALIDATE_INT);

        if ($obraId && $accion === 'toggle') {
            $toggle = $conexion->prepare(
                'UPDATE obras SET disponible = IF(disponible = 1, 0, 1) WHERE id = :id'
            );
            $toggle->execute(['id' => $obraId]);
        }

        if ($obraId && $accion === 'eliminar') {
            // --- Borrar archivo físico solo después de confirmar que la fila existe ---
            $buscarImagen = $conexion->prepare('SELECT imagen FROM obras WHERE id = :id');
            $buscarImagen->execute(['id' => $obraId]);
            $fila = $buscarImagen->fetch();

            if ($fila) {
                $borrar = $conexion->prepare('DELETE FROM obras WHERE id = :id');
                $borrar->execute(['id' => $obraId]);

                if (!empty($fila['imagen'])) {
                    $rutaAbsoluta = __DIR__ . '/../' . $fila['imagen'];
                    if (is_file($rutaAbsoluta)) {
                        unlink($rutaAbsoluta);
                    }
                }
            }
        }
    }

    header('Location: obras.php');
    exit;
}

$categorias = $conexion->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();

$categoriaId = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT);
if ($categoriaId === false || ($categoriaId !== null && $categoriaId <= 0)) {
    $categoriaId = null;
}

$tipoParam = $_GET['tipo'] ?? null;
if (!in_array($tipoParam, ['digital', 'fisica'], true)) {
    $tipoParam = null;
}

$estadoParam = $_GET['estado'] ?? null;
if (!in_array($estadoParam, ['activa', 'pausada'], true)) {
    $estadoParam = null;
}

$busqueda = trim($_GET['q'] ?? '');
if (mb_strlen($busqueda) > 100) {
    $busqueda = mb_substr($busqueda, 0, 100);
}

$ordenesPermitidos = [
    'recientes'   => 'o.created_at DESC',
    'antiguas'    => 'o.created_at ASC',
    'precio_asc'  => 'o.precio ASC',
    'precio_desc' => 'o.precio DESC',
    'titulo'      => 'o.titulo ASC',
];
$ordenParam = $_GET['orden'] ?? 'recientes';
if (!array_key_exists($ordenParam, $ordenesPermitidos)) {
    $ordenParam = 'recientes';
}

$condiciones = [];
$params = [];

if ($categoriaId !== null) {
    $condiciones[] = 'o.categoria_id = :categoria';
    $params['categoria'] = $categoriaId;
}
if ($tipoParam !== null) {
    $condiciones[] = 'o.tipo = :tipo';
    $params['tipo'] = $tipoParam;
}
if ($estadoParam !== null) {
    $condiciones[] = 'o.disponible = :disponible';
    $params['disponible'] = $estadoParam === 'activa' ? 1 : 0;
}
if ($busqueda !== '') {
    $condiciones[] = 'o.titulo LIKE :busqueda';
    $params['busqueda'] = '%' . $busqueda . '%';
}

$sql = 'SELECT o.id, o.titulo, o.precio, o.tipo, o.stock, o.disponible, o.imagen,
               a.nombre AS artista_nombre, c.nombre AS categoria_nombre
        FROM obras o
        INNER JOIN artistas a ON a.id = o.artista_id
        INNER JOIN categorias c ON c.id = o.categoria_id';

if (!empty($condiciones)) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}

$sql .= ' ORDER BY ' . $ordenesPermitidos[$ordenParam];

$consultaObras = $conexion->prepare($sql);
$consultaObras->execute($params);
$obras = $consultaObras->fetchAll();


$obras = $conexion->query(
    'SELECT o.id, o.titulo, o.precio, o.tipo, o.stock, o.disponible, o.imagen,
            a.nombre AS artista_nombre, c.nombre AS categoria_nombre
     FROM obras o
     INNER JOIN artistas a ON a.id = o.artista_id
     INNER JOIN categorias c ON c.id = o.categoria_id
     ORDER BY o.created_at DESC'
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obras | Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-admin">

    <header class="panel-header">
        <div class="panel-header__brand">Arte Local — Admin</div>
        <nav class="panel-header__nav">
            <a href="dashboard.php">Panel</a>
            <a href="obras.php" class="activo">Obras</a>
            <a href="pedidos.php">Pedidos</a>
            <a href="usuarios.php">Usuarios</a>
            <a href="../logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="panel-contenido">
        <section class="tarjeta">
            <h1>Obras (<?= count($obras) ?>)</h1>

            <?php if (empty($obras)): ?>
                <div class="estado-vacio">
                    <span class="estado-vacio__icono">🖼️</span>
                    <p>No hay obras publicadas todavía.</p>
                </div>
            <?php else: ?>
                <div class="tabla-aero-wrap">
                    <table class="tabla-aero">
                        <thead>
                            <tr>
                                <th>Obra</th>
                                <th>Artista</th>
                                <th>Categoría</th>
                                <th>Tipo</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($obras as $obra): ?>
                                <tr>
                                    <td><?= htmlspecialchars($obra['titulo']) ?></td>
                                    <td><?= htmlspecialchars($obra['artista_nombre']) ?></td>
                                    <td><?= htmlspecialchars($obra['categoria_nombre']) ?></td>
                                    <td><?= $obra['tipo'] === 'digital' ? 'Digital' : 'Física' ?></td>
                                    <td>$<?= number_format((float) $obra['precio'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= $obra['disponible'] ? 'badge--activo' : 'badge--agotado' ?>">
                                            <?= $obra['disponible'] ? 'Activa' : 'Pausada' ?>
                                        </span>
                                    </td>
                                    <td style="display:flex; gap:0.5rem;">
                                        <form method="post" action="obras.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="toggle">
                                            <input type="hidden" name="obra_id" value="<?= (int) $obra['id'] ?>">
                                            <button type="submit" class="aero-button secondary-button">
                                                <?= $obra['disponible'] ? 'Suspender' : 'Reactivar' ?>
                                            </button>
                                        </form>
                                        <form method="post" action="obras.php" onsubmit="return confirm('¿Eliminar esta obra permanentemente?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="obra_id" value="<?= (int) $obra['id'] ?>">
                                            <button type="submit" class="aero-button secondary-button">Eliminar</button>
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
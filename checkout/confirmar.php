<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$usuarioId = $_SESSION['usuario_id'];

if (!validarCSRF($_POST['csrf_token'] ?? null)) {
    $_SESSION['checkout_errores'] = ['Solicitud no válida. Volvé a intentarlo.'];
    header('Location: index.php');
    exit;
}

// --- El servidor exige esto siempre, independientemente de que el checkbox
//     esté deshabilitado o habilitado por JS en el navegador. ---
if (($_POST['acepto_terminos'] ?? '') !== '1') {
    $_SESSION['checkout_errores'] = ['Tenés que aceptar los términos y condiciones para continuar.'];
    header('Location: index.php');
    exit;
}

$metodoEntregaPost = $_POST['metodo_entrega'] ?? '';
$direccionPost = trim($_POST['direccion'] ?? '');

try {
    $conexion->beginTransaction();

    // --- Se relee el carrito real y se bloquean las filas de obras involucradas.
    //     Esto también resuelve el doble envío: si dos requests casi simultáneos
    //     llegan acá, el segundo espera a que el primero confirme, y al continuar
    //     encuentra el carrito ya vacío (ver chequeo de abajo). ---
    $itemsConsulta = $conexion->prepare(
        'SELECT ci.cantidad, o.id AS obra_id, o.titulo, o.tipo, o.precio,
                o.stock, o.disponible AS obra_disponible
         FROM carrito_items ci
         INNER JOIN obras o ON o.id = ci.obra_id
         WHERE ci.usuario_id = :usuario
         FOR UPDATE'
    );
    $itemsConsulta->execute(['usuario' => $usuarioId]);
    $items = $itemsConsulta->fetchAll();

    if (empty($items)) {
        $conexion->rollBack();
        $_SESSION['checkout_errores'] = ['Tu carrito está vacío.'];
        header('Location: ../carrito/index.php');
        exit;
    }

    // --- Revalidación real de disponibilidad y stock: nunca se confía en lo mostrado antes ---
    $hasFisica = false;
    $total = 0;

    foreach ($items as $item) {
        $stockFinito = $item['stock'] !== null;
        $disponible = (bool) $item['obra_disponible'] && (!$stockFinito || (int) $item['stock'] > 0);

        if (!$disponible) {
            $conexion->rollBack();
            $_SESSION['checkout_errores'] = ['"' . $item['titulo'] . '" ya no está disponible. Quitala del carrito.'];
            header('Location: ../carrito/index.php');
            exit;
        }

        if ($stockFinito && (int) $item['stock'] < (int) $item['cantidad']) {
            $conexion->rollBack();
            $_SESSION['checkout_errores'] = ['No hay stock suficiente de "' . $item['titulo'] . '".'];
            header('Location: ../carrito/index.php');
            exit;
        }

        if ($item['tipo'] === 'fisica') {
            $hasFisica = true;
        }

        $total += (float) $item['precio'] * (int) $item['cantidad'];
    }

    // --- Entrega: solo si hay al menos una obra física en el pedido ---
    $metodoEntrega = null;
    $direccion = null;

    if ($hasFisica) {
        if (!in_array($metodoEntregaPost, ['retiro', 'domicilio'], true) || $direccionPost === '') {
            $conexion->rollBack();
            $_SESSION['checkout_errores'] = ['Completá el método y la dirección de entrega.'];
            header('Location: index.php');
            exit;
        }

        $metodoEntrega = $metodoEntregaPost;
        $direccion = mb_substr($direccionPost, 0, 255);
    }

    // --- Pago simulado: se "aprueba" siempre en este sprint académico. Sus datos
    //     (titular/número/vencimiento/código ficticios) nunca se leen ni se guardan. ---
    $insertarPedido = $conexion->prepare(
        'INSERT INTO pedidos (usuario_id, estado, total) VALUES (:usuario, :estado, :total)'
    );
    $insertarPedido->execute([
        'usuario' => $usuarioId,
        'estado' => 'pagado',
        'total' => number_format($total, 2, '.', ''),
    ]);
    $pedidoId = (int) $conexion->lastInsertId();

    $insertarItem = $conexion->prepare(
        'INSERT INTO pedido_items (pedido_id, obra_id, titulo, tipo, precio_unitario, cantidad)
         VALUES (:pedido, :obra, :titulo, :tipo, :precio, :cantidad)'
    );
    $descontarStock = $conexion->prepare(
        'UPDATE obras
         SET stock = stock - :cantidad,
             disponible = IF(stock - :cantidad <= 0, 0, disponible)
         WHERE id = :id'
    );

    foreach ($items as $item) {
        $insertarItem->execute([
            'pedido' => $pedidoId,
            'obra' => $item['obra_id'],
            'titulo' => $item['titulo'],
            'tipo' => $item['tipo'],
            'precio' => $item['precio'],
            'cantidad' => $item['cantidad'],
        ]);

        if ($item['tipo'] === 'fisica') {
            // Descuenta stock y, si llega a 0, marca disponible=0 en el mismo UPDATE
            // atómico, dentro del FOR UPDATE. Así la galería y el carrito reflejan
            // el agotamiento inmediatamente sin necesitar acción manual del admin.
            $descontarStock->execute([
                'cantidad' => $item['cantidad'],
                'id' => $item['obra_id'],
            ]);
        }
    }

    if ($hasFisica) {
        $insertarEnvio = $conexion->prepare(
            'INSERT INTO envios (pedido_id, direccion, metodo) VALUES (:pedido, :direccion, :metodo)'
        );
        $insertarEnvio->execute([
            'pedido' => $pedidoId,
            'direccion' => $direccion,
            'metodo' => $metodoEntrega,
        ]);
    }

    $vaciar = $conexion->prepare('DELETE FROM carrito_items WHERE usuario_id = :usuario');
    $vaciar->execute(['usuario' => $usuarioId]);

    $conexion->commit();

    header('Location: comprobante.php?id=' . $pedidoId);
    exit;

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    // No se expone el mensaje real de PDO al usuario; el carrito no se toca porque el rollback lo preserva.
    $_SESSION['checkout_errores'] = ['No se pudo procesar la compra. Intentá nuevamente.'];
    header('Location: index.php');
    exit;
}
<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requerirAutenticacion('../login.php');

$usuarioId = $_SESSION['usuario_id'];
$csrfToken = tokenCSRF();

$errores = $_SESSION['checkout_errores'] ?? [];
unset($_SESSION['checkout_errores']);

// --- Mismo criterio de disponibilidad que en carrito/index.php: el total mostrado acá
//     es informativo, el real se recalcula siempre en checkout/confirmar.php ---
$items = $conexion->prepare(
    'SELECT ci.cantidad, o.id AS obra_id, o.titulo, o.tipo, o.precio, o.imagen,
            o.stock, o.disponible AS obra_disponible
     FROM carrito_items ci
     INNER JOIN obras o ON o.id = ci.obra_id
     WHERE ci.usuario_id = :usuario
     ORDER BY ci.added_at DESC'
);
$items->execute(['usuario' => $usuarioId]);
$itemsCarrito = $items->fetchAll();

if (empty($itemsCarrito)) {
    header('Location: ../carrito/index.php');
    exit;
}

$total = 0;
$hasFisica = false;

foreach ($itemsCarrito as $item) {
    $stockFinito = $item['stock'] !== null;
    $disponible = (bool) $item['obra_disponible'] && (!$stockFinito || (int) $item['stock'] > 0);

    if (!$disponible) {
        // Si algo cambió justo ahora, se resuelve en el carrito, no acá.
        header('Location: ../carrito/index.php');
        exit;
    }

    if ($item['tipo'] === 'fisica') {
        $hasFisica = true;
    }

    $total += (float) $item['precio'] * (int) $item['cantidad'];
}

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

        <section class="tarjeta">
            <h1>Finalizar compra</h1>

            <?php if (!empty($errores)): ?>
                <ul>
                    <?php foreach ($errores as $err): ?>
                        <li class="badge badge--agotado"><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="tabla-aero-wrap">
                <table class="tabla-aero">
                    <thead>
                        <tr>
                            <th>Obra</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Precio</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itemsCarrito as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['titulo']) ?></td>
                                <td><?= $item['tipo'] === 'digital' ? 'Digital' : 'Física' ?></td>
                                <td><?= (int) $item['cantidad'] ?></td>
                                <td>$<?= number_format((float) $item['precio'], 2, ',', '.') ?></td>
                                <td>$<?= number_format((float) $item['precio'] * (int) $item['cantidad'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p style="text-align:right;"><strong>Total: $<?= number_format($total, 2, ',', '.') ?></strong></p>

            <form method="post" action="confirmar.php" id="form-checkout">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <?php if ($hasFisica): ?>
                    <fieldset class="form-group">
                        <legend>Entrega (tenés obras físicas en el carrito)</legend>

                        <label><input type="radio" name="metodo_entrega" value="retiro" checked> Retiro en el local</label>
                        <label><input type="radio" name="metodo_entrega" value="domicilio"> Envío a domicilio</label>

                        <label for="direccion">Dirección o referencia de retiro</label>
                        <input type="text" id="direccion" name="direccion" maxlength="255" required
                               placeholder="Ej: retiro en local / Av. Siempre Viva 742">
                    </fieldset>
                <?php endif; ?>

                <fieldset class="form-group">
                    <legend>Pago simulado</legend>
                    <p class="badge badge--pendiente">Esta pantalla es una simulación. No ingreses datos de tarjetas reales.</p>

                    <label for="titular">Titular (ficticio)</label>
                    <input type="text" id="titular" name="titular_ficticio" placeholder="Nombre de prueba">

                    <label for="numero">Número (ficticio)</label>
                    <input type="text" id="numero" name="numero_ficticio" placeholder="4111 1111 1111 1111" maxlength="19">

                    <label for="vencimiento">Vencimiento (ficticio)</label>
                    <input type="text" id="vencimiento" name="vencimiento_ficticio" placeholder="MM/AA" maxlength="5">

                    <label for="codigo">Código (ficticio)</label>
                    <input type="text" id="codigo" name="codigo_ficticio" placeholder="123" maxlength="4">

                    <p><small>Estos datos no se almacenan ni se envían a ningún lado — son solo de práctica.</small></p>
                </fieldset>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="acepto-terminos" name="acepto_terminos" value="1">
                        He leído y acepto los <button type="button" id="btn-ver-terminos" class="aero-button secondary-button" style="padding:0.2rem 0.6rem;">términos y condiciones</button> de compra.
                    </label>
                </div>

                <button type="submit" id="btn-confirmar" class="aero-button primary-button">Confirmar y pagar</button>
            </form>

            <p><a href="../carrito/index.php">&larr; Volver al carrito</a></p>
        </section>

    </main>

    <dialog id="modal-terminos">
        <h2>Términos y condiciones de compra</h2>
        <p>Esta es una plataforma académica de demostración. Las compras generan un pedido real dentro
           del sistema (incluyendo descuento de stock), pero el pago es completamente simulado y no
           involucra dinero real ni datos financieros reales.</p>
        <form method="dialog"><button class="aero-button secondary-button">Cerrar</button></form>
    </dialog>

    <dialog id="modal-pago-simulado">
        <h2>Pago simulado</h2>
        <p>Esta pantalla corresponde a un sistema de pago simulado. No ingreses datos de tarjetas reales.</p>
        <button type="button" id="btn-entendido-pago" class="aero-button primary-button">Entendido</button>
    </dialog>

    <dialog id="modal-revision">
        <h2>Estás a punto de confirmar esta compra</h2>
        <ul>
            <?php foreach ($itemsCarrito as $item): ?>
                <li>
                    <?= htmlspecialchars($item['titulo']) ?> ×<?= (int) $item['cantidad'] ?>
                    — $<?= number_format((float) $item['precio'] * (int) $item['cantidad'], 2, ',', '.') ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p><strong>Total: $<?= number_format($total, 2, ',', '.') ?></strong></p>
        <p>Al confirmar se va a generar un pedido real y, si corresponde, se van a descontar las unidades físicas disponibles.</p>
        <button type="button" id="btn-cancelar-revision" class="aero-button secondary-button">Cancelar</button>
        <button type="button" id="btn-confirmar-revision" class="aero-button primary-button">Confirmar y continuar</button>
    </dialog>

    <div id="overlay-procesando" hidden>Procesando pago simulado…</div>

    <script>
        var checkbox = document.getElementById('acepto-terminos');
        var btnConfirmar = document.getElementById('btn-confirmar');
        var form = document.getElementById('form-checkout');
        var modalTerminos = document.getElementById('modal-terminos');
        var modalPago = document.getElementById('modal-pago-simulado');
        var modalRevision = document.getElementById('modal-revision');
        var overlay = document.getElementById('overlay-procesando');
        var confirmadoEnModal = false;

        // Progressive enhancement: si JS está deshabilitado, el botón nunca queda
        // disabled acá y el servidor sigue siendo quien exige el checkbox real.
        btnConfirmar.disabled = true;
        checkbox.addEventListener('change', function () {
            btnConfirmar.disabled = !checkbox.checked;
        });

        document.getElementById('btn-ver-terminos').addEventListener('click', function () {
            modalTerminos.showModal();
        });

        window.addEventListener('DOMContentLoaded', function () {
            modalPago.showModal();
        });
        document.getElementById('btn-entendido-pago').addEventListener('click', function () {
            modalPago.close();
        });

        form.addEventListener('submit', function (evento) {
            if (confirmadoEnModal) {
                return; // segundo submit (real) sí pasa
            }
            evento.preventDefault();

            // --- Validar ANTES de abrir el modal de revisión. Si falta un campo
            //     requerido (ej: dirección de entrega), el usuario se entera acá,
            //     no después de ver "Procesando pago...". ---
            if (!form.reportValidity()) {
                return;
            }

            modalRevision.showModal();
        });

        document.getElementById('btn-cancelar-revision').addEventListener('click', function () {
            modalRevision.close();
        });

        document.getElementById('btn-confirmar-revision').addEventListener('click', function () {
            modalRevision.close();

            // --- Revalidar antes del submit final: los datos pudieron cambiar
            //     entre que se abrió el modal de revisión y este click (ej: el
            //     usuario tocó el campo dirección y lo dejó vacío). Si el overlay
            //     de pantalla completa se muestra primero y la validación nativa
            //     del navegador falla después, el aviso del navegador queda tapado
            //     por el overlay y la compra se congela en "Procesando..." sin
            //     ningún mensaje visible. Por eso se valida ANTES de tocar el overlay. ---
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            overlay.hidden = false;
            btnConfirmar.disabled = true;

            // --- Mecanismo único de recuperación de la interfaz: se reutiliza tanto
            //     desde la red de seguridad (timeout) como desde el catch de
            //     requestSubmit(), para no duplicar la lógica de error. ---
            function recuperarInterfaz(mensaje) {
                overlay.hidden = true;
                btnConfirmar.disabled = false;
                window.alert(mensaje);
            }

            var seEnvio = false;
            var recuperar = window.setTimeout(function () {
                // --- Red de seguridad: si por lo que sea el envío nunca navega
                //     (form bloqueado, extensión del navegador, etc.), la interfaz
                //     se recupera sola en vez de quedar bloqueada para siempre. ---
                if (!seEnvio) {
                    recuperarInterfaz('No se pudo iniciar el envío del pago simulado. Volvé a intentarlo.');
                }
            }, 6000);

            setTimeout(function () {
                if (!form.checkValidity()) {
                    window.clearTimeout(recuperar);
                    overlay.hidden = true;
                    btnConfirmar.disabled = false;
                    form.reportValidity();
                    return;
                }

                // --- La red de seguridad NO se desarma hasta saber si
                //     requestSubmit() pudo iniciar el envío. Si lanza una
                //     excepción síncrona (navegador sin soporte, form
                //     bloqueado, etc.), el catch recupera la interfaz de
                //     inmediato en vez de dejar el overlay congelado. ---
                seEnvio = true;
                confirmadoEnModal = true;

                try {
                    form.requestSubmit();
                    window.clearTimeout(recuperar);
                } catch (error) {
                    window.clearTimeout(recuperar);
                    seEnvio = false;
                    confirmadoEnModal = false;
                    recuperarInterfaz('No se pudo iniciar el envío del pago simulado. Volvé a intentarlo.');
                }
            }, 900);
        });
    </script>
</body>
</html>
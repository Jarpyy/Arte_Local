<?php

/**
 * Helper de sesión y autenticación.
 *
 * Centraliza la configuración de la sesión y las verificaciones de acceso
 * para que cada página no tenga que repetir la lógica (y no pueda olvidarla,
 * que fue justamente el problema que tenía dashboard.php).
 */

/**
 * Inicia la sesión con parámetros de cookie seguros.
 * Segura de llamar más de una vez: si ya hay una sesión activa, no hace nada.
 */
function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,          // expira al cerrar el navegador
        'path' => '/',
        'httponly' => true,       // JS no puede leer la cookie (mitiga XSS -> robo de sesión)
        'samesite' => 'Lax',      // mitiga CSRF vía requests cross-site
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    session_start();
}

/**
 * Exige que exista una sesión de usuario válida.
 * Si no la hay, redirige a login y corta la ejecución con exit.
 *
 * @param string $rutaLogin Ruta relativa a login.php desde el archivo que llama
 *                           (ej: 'login.php' desde la raíz, '../login.php' desde /dashboard).
 */
function requerirAutenticacion(string $rutaLogin = 'login.php'): void
{
    iniciarSesionSegura();

    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . $rutaLogin);
        exit;
    }
}

/**
 * Si ya hay una sesión activa, redirige lejos de páginas públicas
 * (login, register, landing) hacia el dashboard.
 *
 * @param string $rutaDashboard Ruta relativa a dashboard.php desde el archivo que llama.
 */
function redirigirSiAutenticado(string $rutaDashboard = 'dashboard/dashboard.php'): void
{
    iniciarSesionSegura();

    if (!empty($_SESSION['usuario_id'])) {
        header('Location: ' . $rutaDashboard);
        exit;
    }
}

/**
 * Devuelve el token CSRF de la sesión actual, generándolo si todavía no existe.
 * Usar este valor en el input hidden de cada <form> que haga POST.
 */
function tokenCSRF(): string
{
    iniciarSesionSegura();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Valida un token CSRF recibido por POST contra el guardado en sesión.
 * Usa hash_equals para evitar timing attacks.
 */
function validarCSRF(?string $token): bool
{
    iniciarSesionSegura();

    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

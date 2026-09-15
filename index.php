<?php

require_once __DIR__ . '/includes/auth.php';

redirigirSiAutenticado('dashboard.php');

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Arte Local. Galería digital de artistas y obras locales.">
    <link rel="stylesheet" href="assets/css/style.css"> 
    <title>Arte Local</title>
</head>

<body>

    <main class="hero-page">

        <section class="hero-presentation">

            <div class="hero-content">

                <span class="brand">ARTE LOCAL</span>

                <h1>
                    Descubrí el arte de nuestra comunidad
                </h1>

                <p>
                    Explorá obras, conocé artistas locales y encontrá
                    nuevas formas de creatividad.
                </p>

                <div class="hero-actions">

                    <a href="login.php" class="aero-button primary-button">
                        Iniciar sesión
                    </a>

                    <a href="register.php" class="aero-button secondary-button">
                        Registrarme
                    </a>

                </div>

            </div>

        </section>

    </main>

</body>

</html>
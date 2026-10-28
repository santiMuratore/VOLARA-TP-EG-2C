<?php
require_once __DIR__ . '/functionsF2.php';

// Título y descripción de la página.
$titulo = $titulo ?? 'Inicio';
$nombreSitio = 'Volara';

$pageDescription = $pageDescription
    ?? 'Sistema de gestión y reserva de vuelos.';

$extraCss = $extraCss ?? [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#151D25">

    <meta
        name="description"
        content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>"
    >

    <title>
        <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
        | <?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?>
    </title>

    <!-- Optimización de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <?php

    $rutaStyle =
        dirname(__DIR__)
        . '/Assets/style.css';

    $versionStyle =
        is_file($rutaStyle)
            ? filemtime($rutaStyle)
            : time();

    ?>

    <link
        href="<?= BASE_URL ?>/Assets/style.css?v=<?= $versionStyle ?>"
        rel="stylesheet"
    >

    <!-- Estilos específicos de cada página -->
    <?php foreach ($extraCss as $css): ?>
        <link
            href="<?= BASE_URL ?>/<?= htmlspecialchars(ltrim($css, '/'), ENT_QUOTES, 'UTF-8') ?>"
            rel="stylesheet"
        >
    <?php endforeach; ?>

    <!-- Favicon: confirmar que el archivo exista en esta ruta -->
    <link
        rel="icon"
        type="image/png"
        sizes="256x256"
        href="<?= BASE_URL ?>/Assets/Imagenes/volara-mark-256.png"
    >
</head>

<body>
    <a class="skip-link" href="#contenido-principal">
        Saltar al contenido principal
    </a>
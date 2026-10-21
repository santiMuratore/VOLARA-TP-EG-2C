<?php
require_once __DIR__ . '/../Includes/functions.php';

// Determinar tipo de usuario para el mapa de sitio
$tipoUsuario = 'invitado';
if (isset($_SESSION['tipoUsuario'])) {
    $tipoUsuario = $_SESSION['tipoUsuario'];
}

$rutas = obtenerRutasSitio($tipoUsuario);
$pageTitle = 'Mapa de Sitio - Volara';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="<?= BASE_URL ?>/Assets/style.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>

<header>
    <nav class="volara-navbar">
        <div class="container">
            <a class="navbar-brand" href="<?= BASE_URL ?>/Publico/index.php">
                Volara
            </a>
            <ul class="navbar-nav">
                <?php foreach ($rutas as $ruta): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= htmlspecialchars($ruta['url']) ?>"><?= htmlspecialchars($ruta['enlace']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>
</header>

<main class="py-5">
    <div class="container">
        <div class="text-center">
            <h1 class="section-title">Mapa de Sitio</h1>
            <p class="section-subtitle">Navegación según rol de usuario</p>
        </div>
        <div class="row justify-content-center g-4">
            <div class="col-lg-8">
                <div class="volara-card">
                    <div class="card-body">
                        <p class="lead">A continuación se muestran los enlaces disponibles según su tipo de usuario:</p>
                        <ol class="list-group list-group-numbered">
                            <?php foreach ($rutas as $ruta): ?>
                                <li class="list-group-item">
                                    <a href="<?= htmlspecialchars($ruta['url']) ?>"><?= htmlspecialchars($ruta['enlace']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="volara-footer mt-5">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <hr class="my-2">
                <p class="text-muted small">Volara - Gestión de reservas de pasajes aéreos</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
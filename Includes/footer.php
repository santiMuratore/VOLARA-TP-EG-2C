<?php
/*
 * Cargar la configuración solamente si todavía no está disponible.
 */
if (!defined('BASE_URL')) {
    $rutasFuncionesF2 = [
        __DIR__ . '/functionsF2.php',
        dirname(__DIR__) . '/Includes/functionsF2.php',
        dirname(__DIR__, 2) . '/Includes/functionsF2.php',
    ];

    $funcionesF2Encontrado = false;

    foreach ($rutasFuncionesF2 as $rutaFuncionesF2) {
        if (is_file($rutaFuncionesF2)) {
            require_once $rutaFuncionesF2;
            $funcionesF2Encontrado = true;
            break;
        }
    }

    if (!$funcionesF2Encontrado || !defined('BASE_URL')) {
        error_log('VOLARA: no se encontró la configuración BASE_URL.');

        http_response_code(500);
        exit('Error de configuración de VOLARA.');
    }
}

$baseUrl = rtrim(BASE_URL, '/');

$sesionActiva = !empty($_SESSION['codUsuario']);

$rolFooter = trim((string) (
    $_SESSION['tipoUsuario']
    ?? $_SESSION['rol']
    ?? ''
));

$rolFooter = function_exists('mb_strtolower')
    ? mb_strtolower($rolFooter, 'UTF-8')
    : strtolower($rolFooter);

$rolFooter = strtr($rolFooter, [
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
]);

$esPasajeroFooter = in_array(
    $rolFooter,
    ['usuario', 'pasajero', 'cliente'],
    true
);
?>

<footer class="volara-footer" role="contentinfo">
    <div class="container">

        <div class="row g-4 py-5">

            <!-- Marca e información -->
            <div class="col-12 col-md-6 col-lg-4">

                <a
                    href="<?= htmlspecialchars($baseUrl . '/Pages/Publico/index.php', ENT_QUOTES, 'UTF-8') ?>"
                    class="text-white text-decoration-none d-inline-block mb-3"
                    aria-label="VOLARA - Inicio">
                    <h2 class="volara-footer-brand-title mb-0" style="color: white;">
                        VOLARA
                    </h2>
                </a>

                <p class="volara-footer-description mb-3">
                    Sistema de gestión y reserva de vuelos.
                    Buscá, compará y reservá de forma simple.
                </p>

                <p class="mb-2">
                    <a
                        href="https://mail.google.com/mail/?view=cm&fs=1&to=volaraairline@gmail.com&su=Consulta%20sobre%20VOLARA"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <i class="bi bi-envelope me-2" aria-hidden="true"></i>
                        volaraairline@gmail.com
                    </a>
                </p>

                <p class="mb-1">
                    <i class="bi bi-geo-alt me-2" aria-hidden="true"></i>
                    Rosario, Santa Fe, Argentina
                </p>

                <p class="mb-0">
                    Entornos Gráficos — UTN FR Rosario
                </p>

            </div>

            <!-- Navegación -->
            <div class="col-6 col-md-3 col-lg-2">

                <h2 class="volara-footer-title">Navegación</h2>

                <ul class="volara-footer-links">
                    <li>
                        <a href="<?= $baseUrl ?>/Pages/Publico/index.php">
                            Inicio
                        </a>
                    </li>
                    <li>
                        <a href="<?= $baseUrl ?>/Pages/Publico/vuelos.php">
                            Buscar vuelos
                        </a>
                    </li>
                    <li>
                        <a href="<?= $baseUrl ?>/Pages/Publico/aerolineas.php">
                            Aerolíneas
                        </a>
                    </li>
                    <li>
                        <a href="<?= $baseUrl ?>/Pages/Publico/mapa-sitio.php">
                            Mapa del sitio
                        </a>
                    </li>
                </ul>

            </div>

            <!-- Descubrí -->
            <div class="col-6 col-md-3 col-lg-3">

                <h2 class="volara-footer-title">Descubrí</h2>

                <ul class="volara-footer-links">
                    <li>
                        <a href="<?= $baseUrl ?>/Pages/Publico/promociones.php">
                            Promociones
                        </a>
                    </li>
                    <li>
                        <a href="<?= $baseUrl ?>/Pages/Publico/novedades.php">
                            Novedades
                        </a>
                    </li>
                    <li>
                        <a href="<?= $baseUrl ?>/Pages/Publico/ayuda.php">
                            Centro de ayuda
                        </a>
                    </li>
                    <li>
                        <a href="<?= $baseUrl ?>/Pages/Publico/contacto.php">
                            Contacto
                        </a>
                    </li>
                </ul>

            </div>

            <!-- Cuenta -->
            <div class="col-12 col-md-6 col-lg-3">

                <h2 class="volara-footer-title">Mi cuenta</h2>

                <ul class="volara-footer-links">

                    <?php if ($sesionActiva): ?>

                        <?php if ($esPasajeroFooter): ?>
                            <li>
                                <a href="<?= $baseUrl ?>/Pages/Pasajero/mis-reservas.php">
                                    <i class="bi bi-ticket-perforated me-2" aria-hidden="true"></i>
                                    Mis reservas
                                </a>
                            </li>
                        <?php endif; ?>

                        <li>
                            <a href="<?= $baseUrl ?>/Pages/Pasajero/perfil.php">
                                <i class="bi bi-person-circle me-2" aria-hidden="true"></i>
                                Mi perfil
                            </a>
                        </li>

                        <?php if (!$esPasajeroFooter): ?>
                            <li>
                                <span>
                                    Encontrá tus opciones de gestión en el menú superior.
                                </span>
                            </li>
                        <?php endif; ?>

                    <?php else: ?>

                        <li>
                            <a href="<?= $baseUrl ?>/Auth/login.php">
                                <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>
                                Iniciar sesión
                            </a>
                        </li>

                        <li>
                            <a href="<?= $baseUrl ?>/Auth/registro.php">
                                <i class="bi bi-person-plus me-2" aria-hidden="true"></i>
                                Crear una cuenta
                            </a>
                        </li>

                    <?php endif; ?>

                    <li>
                        <a href="<?= $baseUrl ?>/Pages/Publico/mapa-sitio.php">
                            Mapa del sitio
                        </a>
                    </li>

                </ul>


                <p class="volara-footer-description mt-3 mb-0">
                    Tu próximo destino comienza acá.
                </p>

            </div>

        </div>

        <!-- Pie inferior -->
        <div class="volara-footer-bottom">
            <p class="mb-0">
                &copy; <?= date('Y') ?> Volara.
                Todos los derechos reservados.
            </p>
        </div>

    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
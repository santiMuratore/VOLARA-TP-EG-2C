<footer class="volara-footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-md-5 footer-brand">
                <p class="brand-name">VOLARA</p>
                <p>Reservas de vuelos comerciales con una experiencia clara, moderna y confiable.</p>
            </div>
            <div class="col-6 col-md-3">
                <h2 class="footer-heading">Explorar</h2>
                <ul class="footer-links">
                    <li><a href="<?= htmlspecialchars(BASE_URL . '/Pages/Publico/index.php') ?>">Inicio</a></li>
                    <li><a href="<?= htmlspecialchars(BASE_URL . '/Pages/Publico/vuelos.php') ?>">Vuelos</a></li>
                    <li><a href="<?= htmlspecialchars(BASE_URL . '/Pages/Publico/aerolineas.php') ?>">Aerolíneas</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4">
                <h2 class="footer-heading">Sitio</h2>
                <ul class="footer-links">
                    <li><a href="<?= htmlspecialchars(BASE_URL . '/Pages/Publico/mapa-sitio.php') ?>">Mapa de sitio</a></li>
                    <li><a href="<?= htmlspecialchars(BASE_URL . '/Pages/login.php') ?>">Iniciar sesión</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>VOLARA · UTN FR Rosario</span>
            <span>Gestión de reservas de pasajes aéreos</span>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var nav = document.querySelector('.volara-navbar');
    var toggler = document.querySelector('.volara-navbar .navbar-toggler');
    var collapse = document.getElementById('volaraNav');
    var video = document.querySelector('.hero-video');

    function syncNav() {
        if (!nav) return;
        nav.classList.toggle('scrolled', window.scrollY > 8);
        if (toggler) {
            var abierto = toggler.getAttribute('aria-expanded') === 'true';
            nav.classList.toggle('is-open', abierto);
        }
    }

    if (nav) {
        syncNav();
        window.addEventListener('scroll', syncNav, { passive: true });
    }

    if (collapse) {
        collapse.addEventListener('shown.bs.collapse', syncNav);
        collapse.addEventListener('hidden.bs.collapse', syncNav);
    }

    if (video) {
        var reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var escritorio = window.matchMedia('(min-width: 768px)').matches;
        var fuente = video.getAttribute('data-src');
        if (!reducirMovimiento && escritorio && fuente) {
            video.src = fuente;
            video.setAttribute('autoplay', '');
            var intento = video.play();
            if (intento && typeof intento.catch === 'function') {
                intento.catch(function () {});
            }
        }
    }
})();
</script>
</body>
</html>

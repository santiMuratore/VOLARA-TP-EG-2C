<?php
http_response_code(500);

$titulo = 'Error de conexión';
$pageDescription = 'Servicio no disponible temporalmente en VOLARA.';
require_once __DIR__ . '/header.php';
?>
<main id="contenido-principal" class="d-flex align-items-center justify-content-center py-5" style="min-height: 60vh; background-color: var(--volara-gray-light);">
    <div class="container d-flex justify-content-center">
        <div class="volara-card text-center" style="max-width: 500px; width: 100%;">
            
            <div class="stat-icon stat-icon-red mx-auto mb-4" style="width: 72px; height: 72px; font-size: 2.2rem;">
                <i class="bi bi-database-x" aria-hidden="true"></i>
            </div>
            
            <h1 class="h4 mb-3">Problemas de conexión</h1>
            
            <p class="text-muted mb-4">
                En este momento no podemos establecer conexión con la base de datos de VOLARA. 
                Por favor, verificá el estado del servidor e intentá nuevamente.
            </p>
            
            <button onclick="window.location.reload();" class="btn btn-volara w-100">
                <i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>
                Reintentar conexión
            </button>
            
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
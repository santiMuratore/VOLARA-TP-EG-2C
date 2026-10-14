<?php

    /*
        opción de inicio de sesión/registrase. Mi perfil.
        ícono de VOLARA. Al tocarlo te lleva al inicio.
        Buscador de vuelos.
        Centro de ayuda.
        Sección descubre que contenga: Destino, Ofertas.
    */
    require_once __DIR__ . '/functions.php';
    if(isset($_SESSION["codUsuario"])){
        $usuario = usuarioActual();
    }
    else{
        $usuario = null;
    }


?>
<nav>
    <div>
        <a href="" title="INICIO" target=_self>
            <img src="/../VOLARA-TP-EG-2C/Imagenes/Volara-Sistema_de_aerolineas.png" alt="Inicio VOLARA" height="40">
        </a>
    </div>
    <div align="right">
        <?php if($usuario){
            echo "Mi perfil";
        }
        else{
            echo "Registrarse/Iniciar Sesión";
        } ?>
    </div>
</nav>
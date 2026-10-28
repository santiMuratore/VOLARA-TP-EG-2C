<?php

require_once __DIR__ . '/../Librerias/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../Librerias/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../Librerias/PHPMailer/src/SMTP.php';

$configLocal =
    __DIR__ . '/../config/config.local.php';

if (is_file($configLocal)) {
    require_once $configLocal;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarMail(
    string $destino,
    string $asunto,
    string $cuerpo
): bool {

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;

        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = SMTP_PORT;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            MAIL_FROM,
            'Volara'
        );

        $mail->addAddress($destino);
        $logoPath =
            __DIR__
            . '/../Assets/Imagenes/Volara-Sistema_de_aerolineas.png';

        if (is_file($logoPath)) {

            $mail->addEmbeddedImage(
                $logoPath,
                'volara_logo',
                'Volara-Sistema_de_aerolineas.png'
            );
        }

        $mail->isHTML(true);

        $mail->Subject = $asunto;
        $mail->Body = $cuerpo;
        $mail->AltBody =
            strip_tags($cuerpo);

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log(
            'Error de PHPMailer: '
            . $mail->ErrorInfo
        );

        return false;
    }
}

function plantillaValidacionVolara(
    string $nombre,
    string $enlaceValidacion
): string {

    $nombreSeguro = htmlspecialchars(
        $nombre,
        ENT_QUOTES,
        'UTF-8'
    );

    $enlaceSeguro = htmlspecialchars(
        $enlaceValidacion,
        ENT_QUOTES,
        'UTF-8'
    );


    return '
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Validá tu cuenta - Volara
    </title>

</head>


<body
    style="
        margin:0;
        padding:0;
        background-color:#f4f5f7;
        font-family:Arial, Helvetica, sans-serif;
        color:#151D25;
    "
>

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="
        width:100%;
        background-color:#f4f5f7;
        padding:40px 15px;
    "
>

<tr>

<td align="center">


<table
    width="600"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="
        width:100%;
        max-width:600px;
        background-color:#ffffff;
        border-radius:18px;
        overflow:hidden;
        box-shadow:0 10px 35px rgba(21,29,37,0.10);
    "
>


    <!-- CABECERA -->

    <tr>

        <td
            align="center"
            style="
                background-color:#E51B23;
                padding:28px 25px;
            "
        >

            <img
                src="cid:volara_logo"
                alt="Volara"
                width="90"
                style="
                    display:block;
                    width:90px;
                    height:auto;
                    margin:0 auto 12px;
                "
            >


            <div
                style="
                    color:#ffffff;
                    font-size:25px;
                    font-weight:bold;
                    letter-spacing:2px;
                "
            >
                VOLARA
            </div>


            <div
                style="
                    color:#ffffff;
                    font-size:13px;
                    margin-top:6px;
                    opacity:0.9;
                "
            >
                Tu próximo destino comienza acá
            </div>

        </td>

    </tr>


    <!-- CONTENIDO -->

    <tr>

        <td
            style="
                padding:42px 42px 34px;
            "
        >

            <div
                style="
                    display:inline-block;
                    background-color:#FDEAEB;
                    color:#E51B23;
                    border-radius:20px;
                    padding:6px 12px;
                    font-size:11px;
                    font-weight:bold;
                    letter-spacing:0.5px;
                    margin-bottom:18px;
                "
            >
                VALIDACIÓN DE CUENTA
            </div>


            <h1
                style="
                    margin:0 0 18px;
                    color:#151D25;
                    font-size:27px;
                    line-height:1.3;
                "
            >
                ¡Bienvenido a Volara!
            </h1>


            <p
                style="
                    margin:0 0 15px;
                    color:#626B75;
                    font-size:16px;
                    line-height:1.7;
                "
            >
                Hola
                <strong style="color:#151D25;">
                    ' . $nombreSeguro . '
                </strong>.
            </p>


            <p
                style="
                    margin:0 0 15px;
                    color:#626B75;
                    font-size:16px;
                    line-height:1.7;
                "
            >
                Gracias por crear tu cuenta en
                <strong>Volara</strong>.
                Ya estás a un paso de comenzar a buscar
                y reservar tus próximos vuelos.
            </p>


            <p
                style="
                    margin:0 0 28px;
                    color:#626B75;
                    font-size:16px;
                    line-height:1.7;
                "
            >
                Para completar el registro necesitamos
                confirmar que esta dirección de correo
                electrónico te pertenece.
            </p>


            <!-- BOTÓN -->

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                border="0"
            >

                <tr>

                    <td align="center">

                        <a
                            href="' . $enlaceSeguro . '"
                            style="
                                display:inline-block;
                                background-color:#E51B23;
                                color:#ffffff;
                                text-decoration:none;
                                font-size:15px;
                                font-weight:bold;
                                padding:15px 32px;
                                border-radius:30px;
                            "
                        >
                            Validar mi cuenta
                        </a>

                    </td>

                </tr>

            </table>


            <!-- AVISO -->

            <div
                style="
                    margin-top:32px;
                    background-color:#FDEAEB;
                    border-left:4px solid #E51B23;
                    border-radius:8px;
                    padding:16px 18px;
                "
            >

                <p
                    style="
                        margin:0;
                        color:#505963;
                        font-size:13px;
                        line-height:1.6;
                    "
                >
                    <strong>Importante:</strong>
                    tu cuenta permanecerá pendiente de
                    validación hasta que confirmes tu correo.
                </p>

            </div>


            <p
                style="
                    margin:27px 0 7px;
                    color:#9299A1;
                    font-size:12px;
                    line-height:1.5;
                "
            >
                ¿El botón no funciona? Copiá y pegá
                este enlace en tu navegador:
            </p>


            <p
                style="
                    margin:0;
                    color:#E51B23;
                    font-size:11px;
                    line-height:1.5;
                    word-break:break-all;
                "
            >
                ' . $enlaceSeguro . '
            </p>

        </td>

    </tr>


    <!-- SEGURIDAD -->

    <tr>

        <td
            style="
                border-top:1px solid #ECEEF0;
                padding:23px 42px;
            "
        >

            <p
                style="
                    margin:0;
                    color:#9299A1;
                    font-size:12px;
                    line-height:1.6;
                "
            >
                Si no creaste una cuenta en Volara,
                podés ignorar este mensaje.
                No se realizará ninguna acción sobre
                tu correo.
            </p>

        </td>

    </tr>


    <!-- FOOTER -->

    <tr>

        <td
            align="center"
            style="
                background-color:#151D25;
                padding:24px;
            "
        >

            <div
                style="
                    color:#ffffff;
                    font-size:16px;
                    font-weight:bold;
                    letter-spacing:1px;
                "
            >
                VOLARA
            </div>


            <div
                style="
                    color:#AAB1B8;
                    font-size:11px;
                    margin-top:6px;
                "
            >
                Sistema de reservas aéreas
            </div>


            <div
                style="
                    color:#747D86;
                    font-size:10px;
                    margin-top:10px;
                "
            >
                © ' . date('Y') . '
                VOLARA. Todos los derechos reservados.
            </div>

        </td>

    </tr>


</table>


</td>

</tr>

</table>

</body>

</html>
';
}

function plantillaRecuperacionVolara(
    string $nombre,
    string $enlaceRecuperacion
): string {

    $nombreSeguro = htmlspecialchars(
        $nombre,
        ENT_QUOTES,
        'UTF-8'
    );

    $enlaceSeguro = htmlspecialchars(
        $enlaceRecuperacion,
        ENT_QUOTES,
        'UTF-8'
    );

    return '
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Recuperá tu contraseña - Volara
    </title>

</head>


<body
    style="
        margin:0;
        padding:0;
        background-color:#f4f5f7;
        font-family:Arial, Helvetica, sans-serif;
        color:#151D25;
    "
>

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="
        width:100%;
        background-color:#f4f5f7;
        padding:40px 15px;
    "
>

<tr>

<td align="center">


<table
    width="600"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="
        width:100%;
        max-width:600px;
        background-color:#ffffff;
        border-radius:18px;
        overflow:hidden;
        box-shadow:0 10px 35px rgba(21,29,37,0.10);
    "
>


    <!-- CABECERA -->

    <tr>

        <td
            align="center"
            style="
                background-color:#E51B23;
                padding:28px 25px;
            "
        >

            <img
                src="cid:volara_logo"
                alt="Volara"
                width="90"
                style="
                    display:block;
                    width:90px;
                    height:auto;
                    margin:0 auto 12px;
                "
            >


            <div
                style="
                    color:#ffffff;
                    font-size:25px;
                    font-weight:bold;
                    letter-spacing:2px;
                "
            >
                VOLARA
            </div>


            <div
                style="
                    color:#ffffff;
                    font-size:13px;
                    margin-top:6px;
                    opacity:0.9;
                "
            >
                Tu próximo destino comienza acá
            </div>

        </td>

    </tr>


    <!-- CONTENIDO -->

    <tr>

        <td
            style="
                padding:42px 42px 34px;
            "
        >

            <div
                style="
                    display:inline-block;
                    background-color:#FDEAEB;
                    color:#E51B23;
                    border-radius:20px;
                    padding:6px 12px;
                    font-size:11px;
                    font-weight:bold;
                    letter-spacing:0.5px;
                    margin-bottom:18px;
                "
            >
                RECUPERACIÓN DE CONTRASEÑA
            </div>


            <h1
                style="
                    margin:0 0 18px;
                    color:#151D25;
                    font-size:27px;
                    line-height:1.3;
                "
            >
                Recuperá tu contraseña
            </h1>


            <p
                style="
                    margin:0 0 15px;
                    color:#626B75;
                    font-size:16px;
                    line-height:1.7;
                "
            >
                Hola
                <strong style="color:#151D25;">
                    ' . $nombreSeguro . '
                </strong>.
            </p>


            <p
                style="
                    margin:0 0 15px;
                    color:#626B75;
                    font-size:16px;
                    line-height:1.7;
                "
            >
                Recibimos una solicitud para cambiar
                la contraseña de tu cuenta de
                <strong>Volara</strong>.
            </p>


            <p
                style="
                    margin:0 0 28px;
                    color:#626B75;
                    font-size:16px;
                    line-height:1.7;
                "
            >
                Para elegir una nueva contraseña,
                utilizá el siguiente botón.
            </p>


            <!-- BOTÓN -->

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                border="0"
            >

                <tr>

                    <td align="center">

                        <a
                            href="' . $enlaceSeguro . '"
                            style="
                                display:inline-block;
                                background-color:#E51B23;
                                color:#ffffff;
                                text-decoration:none;
                                font-size:15px;
                                font-weight:bold;
                                padding:15px 32px;
                                border-radius:30px;
                            "
                        >
                            Restablecer mi contraseña
                        </a>

                    </td>

                </tr>

            </table>


            <!-- AVISO DE VENCIMIENTO -->

            <div
                style="
                    margin-top:32px;
                    background-color:#FDEAEB;
                    border-left:4px solid #E51B23;
                    border-radius:8px;
                    padding:16px 18px;
                "
            >

                <p
                    style="
                        margin:0;
                        color:#505963;
                        font-size:13px;
                        line-height:1.6;
                    "
                >
                    <strong>Importante:</strong>
                    este enlace será válido durante
                    <strong>una hora</strong>.
                    Después de ese tiempo tendrás que
                    solicitar uno nuevo.
                </p>

            </div>


            <p
                style="
                    margin:27px 0 7px;
                    color:#9299A1;
                    font-size:12px;
                    line-height:1.5;
                "
            >
                ¿El botón no funciona? Copiá y pegá
                este enlace en tu navegador:
            </p>


            <p
                style="
                    margin:0;
                    color:#E51B23;
                    font-size:11px;
                    line-height:1.5;
                    word-break:break-all;
                "
            >
                ' . $enlaceSeguro . '
            </p>

        </td>

    </tr>


    <!-- SEGURIDAD -->

    <tr>

        <td
            style="
                border-top:1px solid #ECEEF0;
                padding:23px 42px;
            "
        >

            <p
                style="
                    margin:0;
                    color:#9299A1;
                    font-size:12px;
                    line-height:1.6;
                "
            >
                Si no solicitaste cambiar tu contraseña,
                podés ignorar este correo.
                Tu contraseña actual seguirá siendo válida.
            </p>

        </td>

    </tr>


    <!-- FOOTER -->

    <tr>

        <td
            align="center"
            style="
                background-color:#151D25;
                padding:24px;
            "
        >

            <div
                style="
                    color:#ffffff;
                    font-size:16px;
                    font-weight:bold;
                    letter-spacing:1px;
                "
            >
                VOLARA
            </div>


            <div
                style="
                    color:#AAB1B8;
                    font-size:11px;
                    margin-top:6px;
                "
            >
                Sistema de reservas aéreas
            </div>


            <div
                style="
                    color:#747D86;
                    font-size:10px;
                    margin-top:10px;
                "
            >
                © ' . date('Y') . '
                VOLARA. Todos los derechos reservados.
            </div>

        </td>

    </tr>


</table>


</td>

</tr>

</table>

</body>

</html>
';
}
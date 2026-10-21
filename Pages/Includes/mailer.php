<?php

require_once __DIR__ . '/../../Librerias/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../Librerias/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../Librerias/PHPMailer/src/SMTP.php';

require_once __DIR__ . '/../../config/config.local.php';

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

        $mail->Port = SMTP_PORT;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            MAIL_FROM,
            'Volara'
        );

        $mail->addAddress($destino);

        $mail->isHTML(true);

        $mail->Subject = $asunto;
        $mail->Body = $cuerpo;

        $mail->send();

        return true;

    } catch (Exception $e) {
        return false;
    }
}

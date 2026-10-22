<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class MailHelper
{
    private $mail;
    private $config;

    public function __construct()
    {
        $configFile = "config/mail.ini";

        if (!file_exists($configFile)) {
            die("Error: no se encontró 'config/mail.ini'. Crea el archivo copiando 'config/mail.example.ini' y configura tus credenciales SMTP.");
        }

        $this->config = parse_ini_file($configFile);

        $this->mail = new PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->Host       = $this->config["smtp_host"];
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $this->config["smtp_user"];
        $this->mail->Password   = $this->config["smtp_pass"];
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = (int) $this->config["smtp_port"];
        $this->mail->CharSet    = 'UTF-8';

        $this->mail->setFrom($this->config["from_email"], $this->config["from_name"]);
    }

    public function enviarVerificacion($email, $nombre, $token)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, $nombre);

            $this->mail->isHTML(true);
            $this->mail->Subject = 'Verificación de cuenta - Preguntados';
            $this->mail->Body    = $this->cuerpoHtml($nombre, $token);
            $this->mail->AltBody = $this->cuerpoPlano($nombre, $token);

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("MailHelper Error: " . $e->getMessage());
            return false;
        }
    }

    private function cuerpoHtml($nombre, $token)
    {
        $url = $this->urlBase() . "/?controller=email&method=verificar&token=" . $token;
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #6c5ce7;'>¡Bienvenido a Preguntados, {$nombre}!</h2>
            <p>Gracias por registrarte. Para activar tu cuenta, hac clic en el siguiente enlace:</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='{$url}' style='background: #6c5ce7; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    Verificar mi cuenta
                </a>
            </p>
            <p>O copia y pega este enlace en tu navegador:</p>
            <p style='background: #f4f4f4; padding: 10px; border-radius: 5px; word-break: break-all; font-size: 13px;'>
                {$url}
            </p>
            <p style='color: #888; font-size: 12px;'>Este enlace expira en 24 horas.</p>
        </div>";
    }

    private function cuerpoPlano($nombre, $token)
    {
        $url = $this->urlBase() . "/?controller=email&method=verificar&token=" . $token;
        return "¡Bienvenido a Preguntados, {$nombre}!\n\nPara activar tu cuenta, visita:\n{$url}\n\nEste enlace expira en 24 horas.";
    }

    private function urlBase()
    {
        $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocolo}://{$host}/Preguntados";
    }
}

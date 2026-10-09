<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partido Despedida de Messi - Entradas</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <style>
        :root {
            --celeste: #75AADB;
            --celeste-oscuro: #4A7AAE;
            --blanco: #FFFFFF;
            --verde-cancha: #2E7D32;
            --dorado: #F4C542;
        }

        body {
            background: linear-gradient(180deg,
            var(--celeste) 0%, var(--celeste) 15%,
            var(--blanco) 15%, var(--blanco) 30%,
            var(--celeste) 30%, var(--celeste) 100%);
            background-attachment: fixed;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .header-evento {
            background-color: var(--celeste-oscuro);
            border-bottom: 6px solid var(--dorado);
        }

        .header-evento h1 {
            color: var(--blanco);
        }

        .card-contenido {
            background-color: var(--blanco);
            border-radius: 8px;
            border-top: 6px solid var(--verde-cancha);
        }

        .btn-cancha {
            background-color: var(--verde-cancha) !important;
            color: var(--blanco) !important;
        }

        .btn-cancha:hover {
            background-color: #1B5E20 !important;
        }

        .footer-evento {
            background-color: var(--celeste-oscuro);
            color: var(--blanco);
        }
    </style>
</head>
<body>

<div class="w3-bar" style="background-color:#2E7D32">
    <a href="/Preguntados/" class="w3-bar-item w3-button">🏠 Inicio</a>
    <?php if (isset($_SESSION['usuario'])): ?>
        <a href="/Preguntados/login/logout" class="w3-bar-item w3-button w3-right">🚪 Salir</a>
    <?php endif; ?>
</div>


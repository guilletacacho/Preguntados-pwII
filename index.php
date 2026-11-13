<?php
use app\Configuration;

// El estado de la partida vive en el servidor: nada de caché ni de
// páginas viejas al apretar "atrás". No afecta a los estáticos (los
// sirve Apache directamente).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
require_once("vendor/autoload.php");
require_once("app/Configuration.php");

$configuration = new Configuration();
$router = $configuration->getRouter();

$router->dispatch(
    Request::get("controller", "login"),
    Request::get("method", "show")
);

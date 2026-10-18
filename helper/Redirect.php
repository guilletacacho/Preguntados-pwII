<?php

class Redirect
{
    public static function to($url)
    {
        $basePath = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php")), "/");
        if (strpos($url, "/Preguntados/") === 0) {
            $target = substr($url, strlen("/Preguntados/"));
            $parts = explode("?", $target, 2);
            $route = explode("/", trim($parts[0], "/"));
            $url = $basePath . "/index.php?controller=" . ($route[0] ?: "login") . "&method=" . ($route[1] ?? "show");
            if (isset($parts[1])) {
                $url .= "&" . $parts[1];
            }
        }
        header("Location: $url");
        exit();
    }

    public static function toIndex()
    {
        self::to('/Preguntados/');
    }
}

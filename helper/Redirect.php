<?php

class Redirect
{
    public static function to($url)
    {
        $basePath = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php")), "/");
        if (strpos($url, "/Preguntados/") === 0) {
            $target = substr($url, strlen("/Preguntados"));
            $url = $basePath . ($target !== "" ? $target : "/");
        }
        header("Location: $url");
        exit();
    }

    public static function toIndex()
    {
        self::to('/Preguntados/');
    }
}

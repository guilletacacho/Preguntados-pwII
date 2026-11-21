<?php

class AdminAccess
{
    public static function exigir($model)
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $userId = (int) $_SESSION['usuario_id'];
        if (!$model->esAdmin($userId)) {
            $_SESSION['rol'] = 'JUGADOR';
            http_response_code(403);
            exit('No tenés permisos para acceder al panel administrable.');
        }

        $_SESSION['rol'] = 'ADMIN';
        return true;
    }
}

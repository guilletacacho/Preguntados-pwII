<?php

class ModeradorAccess
{
    public static function exigir($model)
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::to('/Preguntados/login/show');
        }

        $userId = (int) $_SESSION['usuario_id'];
        if (!$model->puedeModerar($userId)) {
            http_response_code(403);
            $renderer = new MustacheRender($model);
            $renderer->renderiza('errorRol', ['seccion' => 'Moderación']);
            exit;
        }

        return true;
    }
}

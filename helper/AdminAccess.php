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
            http_response_code(403);
            $renderer = new MustacheRender(null, $model);
            $renderer->renderiza('errorRol', ['seccion' => 'Administración']);
            exit;
        }

        $_SESSION['rol'] = 'ADMIN';
        return true;
    }
}

<?php

class Router
{
    private $config;
    private $defaultController;
    private $defaultMethod;

    public function __construct($config, $defaultController, $defaultMethod)
    {
        $this->config            = $config;
        $this->defaultController = $defaultController;
        $this->defaultMethod     = $defaultMethod;
    }

    public function dispatch($controllerName, $method)
    {

        if ($controllerName === 'moderador' && $this->dispatchModerador($method)) {
        return;
    }

        $controller = $this->getController($controllerName);
        $method     = $this->getMethod($controller, $method);
        $controller->{$method}();
    }

    private function getController($controllerName)
    {
        $factoryMethod = $this->getFactoryMethodName($controllerName);

        if (!method_exists($this->config, $factoryMethod)) {
            $factoryMethod = $this->getFactoryMethodName($this->defaultController);
        }

        return $this->config->{$factoryMethod}();
    }

    private function getFactoryMethodName($controllerName)
    {
        return "get" . ucfirst($controllerName) . "Controller";
    }

    private function getMethod($controller, $method)
    {
        return method_exists($controller, $method) ? $method : $this->defaultMethod;
    }

    private function dispatchModerador($method)
    {
        $rutasModerador = [
            'show' => 'getModeradorController',

            // Preguntas
            'preguntas' => 'getModeradorPreguntasController',
            'altaPregunta' => 'getModeradorPreguntasController',
            'crearPregunta' => 'getModeradorPreguntasController',
            'desactivarPregunta' => 'getModeradorPreguntasController',
            'reactivarPregunta' => 'getModeradorPreguntasController',
            'editarPregunta' => 'getModeradorPreguntasController',
            'actualizarPregunta' => 'getModeradorPreguntasController',
            'categorias' => 'getModeradorPreguntasController',
            'guardarCategoria' => 'getModeradorPreguntasController',

            // Sugerencias
            'sugerencias' => 'getModeradorSugerenciasController',
            'editarSugerencia' => 'getModeradorSugerenciasController',
            'publicarSugerencias' => 'getModeradorSugerenciasController',
            'rechazarSugerencia' => 'getModeradorSugerenciasController',

            // Reportes
            'reportes' => 'getModeradorReportesController',
            'detalle' => 'getModeradorReportesController',
            'editar' => 'getModeradorReportesController',
            'corregir' => 'getModeradorReportesController',
            'resolver' => 'getModeradorReportesController',
            'descartar' => 'getModeradorReportesController',
        ];

        if (!isset($rutasModerador[$method])) {
            return false;
        }

        $controller = $this->config->{$rutasModerador[$method]}();

        if (!method_exists($controller, $method)) {
            return false;
        }

        $controller->{$method}();
        return true;
    }
}

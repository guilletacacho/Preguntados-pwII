<?php

namespace app\controller;

use Redirect;
use Request;

class RegistroController {
    private $model;
    private $render;

    public function __construct($model, $render) {
        $this->model = $model;
        $this->render = $render;
    }

    public function show() {
        $this->render->renderiza("registro", ["anioActual" => date('Y')]);
    }

    public function disponibilidad() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $usuario = Request::get('usuario', '');
        echo json_encode(["disponible" => is_string($usuario) && trim($usuario) !== '' && !$this->model->existeUsuario(trim($usuario))]);
    }

    public function registrar() {
        $data = ["anioActual" => date('Y')];
        $errores = [];
        foreach (['nombre', 'anioNacimiento', 'sexo', 'pais', 'usuario'] as $campo) {
            $valor = Request::post($campo, '');
            $data[$campo] = is_string($valor) ? trim($valor) : '';
            if ($data[$campo] === '') {
                $errores[$campo] = 'Completa este campo.';
            }
        }
        if ($data['anioNacimiento'] !== '' && (!ctype_digit($data['anioNacimiento']) || (int)$data['anioNacimiento'] < 1900 || (int)$data['anioNacimiento'] > (int)date('Y'))) {
            $errores['anioNacimiento'] = 'Ingresa un año entre 1900 y ' . date('Y') . '.';
        }
        if (!in_array($data['sexo'], ['Femenino', 'Masculino', 'Otro'], true)) {
            $errores['sexo'] = 'Selecciona una opción válida.';
        }
        $contrasena = Request::post('contrasena', '');
        $repetida = Request::post('repetirContrasena', '');
        if (!is_string($contrasena) || preg_match_all('/./us', $contrasena) < 8) {
            $errores['contrasena'] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!is_string($repetida) || $repetida === '' || $contrasena !== $repetida) {
            $errores['repetirContrasena'] = 'Las contraseñas no coinciden.';
        }
        if ($data['usuario'] !== '' && $this->model->existeUsuario($data['usuario'])) {
            $errores['usuario'] = 'El usuario ya existe.';
        }
        if (!$errores && $this->model->insertar($data['nombre'], $data['anioNacimiento'], $data['sexo'], $data['pais'], $data['usuario'], $contrasena)) {
            Redirect::to('/Preguntados/?registro=ok');
            return;
        }
        $data['errores'] = $errores;
        $data['error'] = $errores ? 'Revisa los campos indicados para completar el registro.' : 'No se pudo completar el registro. Inténtalo de nuevo.';
        foreach (['Femenino', 'Masculino', 'Otro'] as $sexo) {
            $data['sexo' . $sexo] = $data['sexo'] === $sexo;
        }
        $this->render->renderiza('registro', $data);
    }
}

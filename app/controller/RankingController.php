<?php

namespace app\controller;

use Redirect;
use Request;

class RankingController
{
    private $model;
    private $render;

    public function __construct($model, $render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function show()
    {
        $tab = Request::get("tab", "semanal");
        if (!in_array($tab, ["semanal", "mensual", "historico"], true)) {
            $tab = "semanal";
        }

        $semanal = $this->model->obtenerRankingSemanal();
        $mensual = $this->model->obtenerRankingMensual();
        $historico = $this->model->obtenerRankingHistorico();

        $semanal = array_map(function ($item) {
            $item['puesto1'] = $item['puesto'] == 1;
            $item['puesto2'] = $item['puesto'] == 2;
            $item['puesto3'] = $item['puesto'] == 3;
            $item['puestoTop'] = $item['puesto'] >= 4 && $item['puesto'] <= 10;
            return $item;
        }, $semanal);

        $mensual = array_map(function ($item) {
            $item['puesto1'] = $item['puesto'] == 1;
            $item['puesto2'] = $item['puesto'] == 2;
            $item['puesto3'] = $item['puesto'] == 3;
            $item['puestoTop'] = $item['puesto'] >= 4 && $item['puesto'] <= 10;
            return $item;
        }, $mensual);

        $historico = array_map(function ($item) {
            $item['puesto1'] = $item['puesto'] == 1;
            $item['puesto2'] = $item['puesto'] == 2;
            $item['puesto3'] = $item['puesto'] == 3;
            $item['puestoTop'] = $item['puesto'] >= 4 && $item['puesto'] <= 10;
            return $item;
        }, $historico);

        $data = [
            "semanal" => $semanal,
            "mensual" => $mensual,
            "historico" => $historico,
            "tabSemanales" => $tab === "semanal",
            "tabMensuales" => $tab === "mensual",
            "tabHistoricos" => $tab === "historico",
        ];

        $this->render->renderiza("ranking", $data);
    }
}

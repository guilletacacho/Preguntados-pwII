<?php

namespace app\model;

class RankingModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerRankingSemanal()
    {
        return [
            ["puesto" => 1, "usuario" => "Triviamaster99", "puntos" => 2450, "partidas" => 32, "avatar" => "T"],
            ["puesto" => 2, "usuario" => "Cerebrito", "puntos" => 2320, "partidas" => 28, "avatar" => "C"],
            ["puesto" => 3, "usuario" => "Pregunton", "puntos" => 2185, "partidas" => 26, "avatar" => "P"],
            ["puesto" => 4, "usuario" => "Sabelotodo", "puntos" => 2040, "partidas" => 25, "avatar" => "S"],
            ["puesto" => 5, "usuario" => "QuizKing", "puntos" => 1925, "partidas" => 24, "avatar" => "Q"],
            ["puesto" => 6, "usuario" => "Acertijo", "puntos" => 1810, "partidas" => 22, "avatar" => "A"],
            ["puesto" => 7, "usuario" => "Erudito", "puntos" => 1695, "partidas" => 21, "avatar" => "E"],
            ["puesto" => 8, "usuario" => "Sabiondo", "puntos" => 1580, "partidas" => 20, "avatar" => "S"],
            ["puesto" => 9, "usuario" => "Adivinador", "puntos" => 1465, "partidas" => 19, "avatar" => "A"],
            ["puesto" => 10, "usuario" => "Curioso", "puntos" => 1350, "partidas" => 18, "avatar" => "C"],
        ];
    }

    public function obtenerRankingMensual()
    {
        return [
            ["puesto" => 1, "usuario" => "Cerebrito", "puntos" => 9850, "partidas" => 128, "avatar" => "C"],
            ["puesto" => 2, "usuario" => "Triviamaster99", "puntos" => 9420, "partidas" => 120, "avatar" => "T"],
            ["puesto" => 3, "usuario" => "Sabelotodo", "puntos" => 8960, "partidas" => 115, "avatar" => "S"],
            ["puesto" => 4, "usuario" => "Pregunton", "puntos" => 8425, "partidas" => 108, "avatar" => "P"],
            ["puesto" => 5, "usuario" => "QuizKing", "puntos" => 7890, "partidas" => 102, "avatar" => "Q"],
            ["puesto" => 6, "usuario" => "Erudito", "puntos" => 7355, "partidas" => 96, "avatar" => "E"],
            ["puesto" => 7, "usuario" => "Acertijo", "puntos" => 6820, "partidas" => 90, "avatar" => "A"],
            ["puesto" => 8, "usuario" => "Adivinador", "puntos" => 6285, "partidas" => 84, "avatar" => "A"],
            ["puesto" => 9, "usuario" => "Sabiondo", "puntos" => 5750, "partidas" => 78, "avatar" => "S"],
            ["puesto" => 10, "usuario" => "Curioso", "puntos" => 5215, "partidas" => 72, "avatar" => "C"],
        ];
    }

    public function obtenerRankingHistorico()
    {
        return [
            ["puesto" => 1, "usuario" => "Cerebrito", "puntos" => 45890, "partidas" => 642, "avatar" => "C"],
            ["puesto" => 2, "usuario" => "Triviamaster99", "puntos" => 43250, "partidas" => 598, "avatar" => "T"],
            ["puesto" => 3, "usuario" => "Sabelotodo", "puntos" => 40680, "partidas" => 564, "avatar" => "S"],
            ["puesto" => 4, "usuario" => "Pregunton", "puntos" => 38120, "partidas" => 532, "avatar" => "P"],
            ["puesto" => 5, "usuario" => "QuizKing", "puntos" => 35560, "partidas" => 498, "avatar" => "Q"],
            ["puesto" => 6, "usuario" => "Erudito", "puntos" => 33000, "partidas" => 466, "avatar" => "E"],
            ["puesto" => 7, "usuario" => "Acertijo", "puntos" => 30440, "partidas" => 432, "avatar" => "A"],
            ["puesto" => 8, "usuario" => "Adivinador", "puntos" => 27880, "partidas" => 400, "avatar" => "A"],
            ["puesto" => 9, "usuario" => "Sabiondo", "puntos" => 25320, "partidas" => 368, "avatar" => "S"],
            ["puesto" => 10, "usuario" => "Curioso", "puntos" => 22760, "partidas" => 336, "avatar" => "C"],
        ];
    }
}

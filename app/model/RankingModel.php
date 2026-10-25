<?php

namespace app\model;

class RankingModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    private function obtenerRanking($sql){
        $ranking = $this->database->queryAll($sql);

        foreach ($ranking as $indice => $item) {
            $ranking[$indice]['puesto'] = $indice+1;
            $ranking[$indice]['avatar'] = strtoupper(
            substr($item['usuario'], 0, 1));
        }

        return $ranking;
    }
    public function obtenerRankingSemanal()
    {
        $sql = "SELECT 
      u.Id, 
      u.Usuario AS usuario, 
      SUM(g.total_points) AS puntos, 
      COUNT(*) AS partidas 
  FROM usuarios u 
  JOIN game g ON g.user_id = u.Id 
  WHERE g.status = 'FINALIZADA' 
    AND g.finished_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) 
    AND g.finished_at <= NOW() 
  GROUP BY u.Id, u.Usuario 
  ORDER BY puntos DESC, u.Id ASC 
  LIMIT 10;";
        return $this->obtenerRanking($sql);
    }

    public function obtenerRankingMensual()
    {
        $sql = "SELECT 
                  u.Id, 
                  u.Usuario AS usuario, 
                  SUM(g.total_points) AS puntos, 
                  COUNT(*) AS partidas 
              FROM usuarios u 
              JOIN game g ON g.user_id = u.Id 
              WHERE g.status = 'FINALIZADA' 
                AND g.finished_at >= DATE_SUB( 
                    CURDATE(), 
                    INTERVAL (DAYOFMONTH(CURDATE()) - 1) DAY 
                ) 
                AND g.finished_at <= NOW() 
              GROUP BY u.Id, u.Usuario 
              ORDER BY puntos DESC, u.Id ASC 
              LIMIT 10";

        return $this->obtenerRanking($sql);
    }

    public function obtenerRankingHistorico()
    {
        $sql = " 
              SELECT 
                  u.Id, 
                  u.Usuario AS usuario, 
                  SUM(g.total_points) AS puntos, 
                  COUNT(*) AS partidas 
              FROM usuarios u 
              JOIN game g ON g.user_id = u.Id 
              WHERE g.status = 'FINALIZADA' 
              GROUP BY u.Id, u.Usuario 
              ORDER BY puntos DESC, u.Id ASC 
              LIMIT 10";

        return $this->obtenerRanking($sql);
    }
}

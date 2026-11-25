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
            $ranking[$indice]['avatar'] = mb_strtoupper(mb_substr($item['usuario'], 0, 1));
            $ranking[$indice]['usuarioUrl'] = urlencode($item['usuario']);
            $ranking[$indice]['fotoUrl'] = $item['foto_url'] ?? '';
            $ranking[$indice]['tieneFoto'] = !empty($item['foto_url']);
        }

        return $ranking;
    }
    public function obtenerRankingSemanal()
    {
        $sql = "SELECT 
      u.Id, 
      u.Usuario AS usuario, u.Foto_url AS foto_url,
      SUM(g.total_points) AS puntos, 
      COUNT(*) AS partidas 
  FROM usuarios u 
  JOIN game g ON g.user_id = u.Id 
  WHERE g.status = 'FINALIZADA' 
    AND g.finished_at >= DATE_SUB(UTC_DATE(), INTERVAL WEEKDAY(UTC_DATE()) DAY)
    AND g.finished_at <= UTC_TIMESTAMP()
  GROUP BY u.Id, u.Usuario, u.Foto_url
  ORDER BY puntos DESC, u.Id ASC 
  LIMIT 10;";
        return $this->obtenerRanking($sql);
    }

    public function obtenerRankingMensual()
    {
        $sql = "SELECT 
                  u.Id, 
                  u.Usuario AS usuario, u.Foto_url AS foto_url,
                  SUM(g.total_points) AS puntos, 
                  COUNT(*) AS partidas 
              FROM usuarios u 
              JOIN game g ON g.user_id = u.Id 
              WHERE g.status = 'FINALIZADA' 
                AND g.finished_at >= DATE_SUB(
                    UTC_DATE(),
                    INTERVAL (DAYOFMONTH(UTC_DATE()) - 1) DAY
                )
                AND g.finished_at <= UTC_TIMESTAMP()
              GROUP BY u.Id, u.Usuario, u.Foto_url
              ORDER BY puntos DESC, u.Id ASC 
              LIMIT 10";

        return $this->obtenerRanking($sql);
    }

    public function obtenerRankingHistorico()
    {
        $sql = " 
              SELECT 
                  u.Id, 
                  u.Usuario AS usuario, u.Foto_url AS foto_url,
                  SUM(g.total_points) AS puntos, 
                  COUNT(*) AS partidas 
              FROM usuarios u 
              JOIN game g ON g.user_id = u.Id 
              WHERE g.status = 'FINALIZADA' 
              GROUP BY u.Id, u.Usuario, u.Foto_url
              ORDER BY puntos DESC, u.Id ASC 
              LIMIT 10";

        return $this->obtenerRanking($sql);
    }
}

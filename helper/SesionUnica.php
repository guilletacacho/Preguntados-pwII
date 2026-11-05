<?php

/**
 * Bloqueo de una sola sesión por cuenta.
 *
 * Las pestañas del mismo navegador comparten la cookie de sesión PHP, pero
 * una ventana de incógnito u otro dispositivo abre otra sesión. Esta tabla
 * guarda qué sesión está jugando y cuándo se vio por última vez.
 */
class SesionUnica
{
    /**
     * Intenta quedarse con la cuenta. Devuelve true si esta sesión manda.
     * Si otra sesión sigue activa, devuelve false y no toca nada.
     */
    public static function reclamar($database, $userId, $sessionKey, $segundosVigencia)
    {
        $userId = (int) $userId;
        $sessionKey = (string) $sessionKey;
        $segundosVigencia = max(5, (int) $segundosVigencia);

        // La comparación se hace con la hora de la base para no depender de
        // la zona horaria de PHP ni de la del servidor MySQL.
        $sql = "INSERT INTO user_session_lock (user_id, session_key, last_seen)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    session_key = IF(TIMESTAMPDIFF(SECOND, last_seen, NOW()) > ?
                                     OR session_key = VALUES(session_key),
                                     VALUES(session_key), session_key),
                    last_seen = IF(TIMESTAMPDIFF(SECOND, last_seen, NOW()) > ?
                                   OR session_key = VALUES(session_key),
                                   NOW(), last_seen)";

        try {
            $database->execute($sql, [$userId, $sessionKey, $segundosVigencia, $segundosVigencia]);
        } catch (\PDOException $exception) {
            // Si todavía no se importó migracion_sesion_unica.sql, el juego
            // sigue funcionando sin el bloqueo entre sesiones.
            if ((int) ($exception->errorInfo[1] ?? 0) === 1146) {
                \Log::warning('Falta la tabla user_session_lock: importar migracion_sesion_unica.sql');
                return true;
            }

            throw $exception;
        }

        $fila = $database->queryOne(
            "SELECT session_key
             FROM user_session_lock
             WHERE user_id = ?
             LIMIT 1",
            [$userId]
        );

        return $fila !== null
            && hash_equals((string) $fila['session_key'], $sessionKey);
    }

    /**
     * Libera la cuenta solo si la sesión que sale es la que la tenía tomada.
     */
    public static function liberar($database, $userId, $sessionKey)
    {
        $userId = (int) $userId;
        $sessionKey = (string) $sessionKey;

        if ($userId <= 0 || $sessionKey === '') {
            return;
        }

        try {
            $database->execute(
                "DELETE FROM user_session_lock
                 WHERE user_id = ? AND session_key = ?",
                [$userId, $sessionKey]
            );
        } catch (\PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1146) {
                throw $exception;
            }
        }
    }
}

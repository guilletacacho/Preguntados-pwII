<?php

namespace app\model;

class PresenceModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function touch($userId, $sessionId)
    {
        $this->database->execute(
            'INSERT INTO user_presence (session_key, user_id, last_seen) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), last_seen = NOW()',
            [hash('sha256', $sessionId), (int) $userId]
        );
    }

    public function remove($sessionId)
    {
        $this->database->execute('DELETE FROM user_presence WHERE session_key = ?', [hash('sha256', $sessionId)]);
    }

    public function countOnline($excludeUserId = 0)
    {
        $this->database->execute('DELETE FROM user_presence WHERE last_seen < NOW() - INTERVAL 90 SECOND');
        $sql = 'SELECT COUNT(DISTINCT user_id) AS total FROM user_presence WHERE last_seen >= NOW() - INTERVAL 90 SECOND';
        $params = [];
        $excludeUserId = (int) $excludeUserId;
        if ($excludeUserId > 0) {
            $sql .= ' AND user_id <> ?';
            $params[] = $excludeUserId;
        }
        $row = $this->database->queryOne($sql, $params);
        return (int) $row['total'];
    }
}

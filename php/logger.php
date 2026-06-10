<?php
class Logger {
    private $pdo;
    private static $instance = null;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public static function getInstance($pdo) {
        if (self::$instance === null) {
            self::$instance = new self($pdo);
        }
        return self::$instance;
    }
    
    public function log($userId, $action, $description) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO action_logs (user_id, action, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            return true;
        } catch(PDOException $e) {
            error_log("Logger error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getLogs($userId = null, $limit = 50) {
        $query = "SELECT l.*, u.username FROM action_logs l LEFT JOIN users u ON l.user_id = u.id WHERE 1=1";
        $params = [];
        
        if ($userId) {
            $query .= " AND l.user_id = ?";
            $params[] = $userId;
        }
        
        $query .= " ORDER BY l.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>
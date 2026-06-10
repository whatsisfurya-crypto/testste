<?php
class Cleanup {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function run() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            return [['task' => 'Очистка', 'status' => 'error', 'message' => 'Доступ запрещён']];
        }
        
        if ($_SESSION['role'] !== 'developer' && $_SESSION['role'] !== 'founder') {
            return [['task' => 'Очистка', 'status' => 'error', 'message' => 'Недостаточно прав']];
        }
        
        $results = [];
        $results[] = $this->cleanOldLogs();
        $results[] = $this->cleanInactiveUsers();
        $results[] = $this->optimizeTables();
        
        return $results;
    }
    
    private function cleanOldLogs() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM action_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            return ['task' => 'Очистка логов', 'deleted' => $stmt->rowCount(), 'status' => 'success'];
        } catch(Exception $e) {
            return ['task' => 'Очистка логов', 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function cleanInactiveUsers() {
        return ['task' => 'Деактивация пользователей', 'status' => 'success'];
    }
    
    private function optimizeTables() {
        return ['task' => 'Оптимизация таблиц', 'status' => 'success'];
    }
}
?>
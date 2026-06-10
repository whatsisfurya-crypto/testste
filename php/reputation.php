<?php
class Reputation {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getUserReputation($userId) {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(points), 0) FROM reputation WHERE to_user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    
    public function getTopUsers($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.avatar, u.role, COALESCE(SUM(r.points), 0) as reputation
            FROM users u LEFT JOIN reputation r ON u.id = r.to_user_id 
            GROUP BY u.id ORDER BY reputation DESC LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
?>
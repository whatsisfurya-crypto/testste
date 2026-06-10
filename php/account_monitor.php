<?php
class AccountMonitor {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->init();
    }
    
    private function init() {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_activity_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            action VARCHAR(50),
            metadata TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    public function findMultiAccounts($userId) {
        $linkedAccounts = [];
        
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.role, u.last_login
            FROM user_ips ui1
            JOIN user_ips ui2 ON ui1.ip_address = ui2.ip_address AND ui1.user_id != ui2.user_id
            JOIN users u ON ui2.user_id = u.id
            WHERE ui1.user_id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$userId]);
        $ipLinked = $stmt->fetchAll();
        
        foreach ($ipLinked as $linked) {
            $linkedAccounts[] = [
                'user' => $linked,
                'link_type' => 'IP адрес',
                'confidence' => 75
            ];
        }
        
        return $linkedAccounts;
    }
    
    public function getSecurityReport($userId) {
        return [
            'unique_ips' => 0,
            'unique_devices' => 0,
            'linked_accounts' => count($this->findMultiAccounts($userId)),
            'suspicious_activities' => 0,
            'risk_level' => 'low',
            'risk_score' => 0
        ];
    }
}
?>
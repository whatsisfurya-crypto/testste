<?php
class Achievements {
    private $pdo;
    
    private $achievementsList = [
        'first_login' => ['name' => 'Первый вход', 'description' => 'Войти в панель управления', 'icon' => 'key', 'points' => 10],
        'ban_master' => ['name' => 'Мастер банов', 'description' => 'Выдать 100 банов', 'icon' => 'ban', 'points' => 50],
        'veteran' => ['name' => 'Ветеран', 'description' => 'Быть в команде более 6 месяцев', 'icon' => 'star', 'points' => 100]
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function unlockAchievement($userId, $key) {
        if (!isset($this->achievementsList[$key])) return false;
        
        try {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO achievements (user_id, achievement_key) VALUES (?, ?)");
            $stmt->execute([$userId, $key]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'name' => $this->achievementsList[$key]['name'], 'points' => $this->achievementsList[$key]['points']];
            }
        } catch(PDOException $e) {}
        
        return false;
    }
    
    public function getUserAchievements($userId) {
        $stmt = $this->pdo->prepare("SELECT achievement_key, unlocked_at FROM achievements WHERE user_id = ?");
        $stmt->execute([$userId]);
        $unlocked = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $result = [];
        foreach ($this->achievementsList as $key => $achievement) {
            $result[$key] = array_merge($achievement, [
                'unlocked' => isset($unlocked[$key]),
                'unlocked_at' => $unlocked[$key] ?? null
            ]);
        }
        
        return $result;
    }
    
    public function getTotalPoints($userId) {
        $total = 0;
        $achievements = $this->getUserAchievements($userId);
        foreach ($achievements as $ach) {
            if ($ach['unlocked']) $total += $ach['points'];
        }
        return $total;
    }
}
?>
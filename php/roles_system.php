<?php
class RolesSystem {
    private $pdo;
    private static $instance = null;
    
    private $adminLevels = [
        1 => ['key' => 'helper', 'name' => 'Хелпер', 'color' => '#9CA3AF', 'icon' => 'help-circle', 'badge' => 'H'],
        2 => ['key' => 'junior_moderator', 'name' => 'Младший модератор', 'color' => '#60A5FA', 'icon' => 'shield', 'badge' => 'JM'],
        3 => ['key' => 'moderator', 'name' => 'Модератор', 'color' => '#3B82F6', 'icon' => 'shield-check', 'badge' => 'M'],
        4 => ['key' => 'administrator', 'name' => 'Администратор', 'color' => '#10B981', 'icon' => 'shield-star', 'badge' => 'A'],
        5 => ['key' => 'curator', 'name' => 'Куратор', 'color' => '#F59E0B', 'icon' => 'eye', 'badge' => 'К'],
        6 => ['key' => 'deputy_chief', 'name' => 'Заместитель ГА', 'color' => '#F97316', 'icon' => 'star', 'badge' => 'ЗГА'],
        7 => ['key' => 'chief_admin', 'name' => 'Главный Администратор', 'color' => '#EF4444', 'icon' => 'crown', 'badge' => 'ГА'],
        8 => ['key' => 'founder', 'name' => 'Основатель', 'color' => '#8B5CF6', 'icon' => 'star', 'badge' => 'ОС'],
        9 => ['key' => 'developer', 'name' => 'Разработчик', 'color' => '#EC4899', 'icon' => 'code', 'badge' => 'DEV']
    ];
    
    private $positions = [
        'help_department' => [
            'name' => 'Следящие за хелперами',
            'description' => 'Help отдел',
            'color' => '#06B6D4',
            'positions' => [
                'help_overseer' => ['name' => 'Следящий Хелп', 'level_bonus' => 1, 'color' => '#06B6D4', 'badge' => 'СХ'],
                'deputy_head_help' => ['name' => 'ЗГС Хелп', 'level_bonus' => 2, 'color' => '#0891B2', 'badge' => 'ЗГСХ'],
                'head_help' => ['name' => 'ГС Хелп', 'level_bonus' => 3, 'color' => '#0E7490', 'badge' => 'ГСХ']
            ]
        ],
        'technical_department' => [
            'name' => 'Следящие за тех.разделом',
            'description' => 'Техническая поддержка',
            'color' => '#14B8A6',
            'positions' => [
                'tech_overseer' => ['name' => 'Следящий Тех.Раздел', 'level_bonus' => 1, 'color' => '#14B8A6', 'badge' => 'СТ'],
                'deputy_head_tech' => ['name' => 'ЗГС Тех.Раздел', 'level_bonus' => 2, 'color' => '#0D9488', 'badge' => 'ЗГСТ'],
                'head_tech' => ['name' => 'ГС Тех.Раздел', 'level_bonus' => 3, 'color' => '#0F766E', 'badge' => 'ГСТ']
            ]
        ],
        'government_structures' => [
            'name' => 'Следящие за гос.структурами',
            'description' => 'Государственные организации',
            'color' => '#8B5CF6',
            'positions' => [
                'gov_overseer' => ['name' => 'Следящий ГОС', 'level_bonus' => 1, 'color' => '#8B5CF6', 'badge' => 'СГ'],
                'deputy_head_gov' => ['name' => 'ЗГС ГОС', 'level_bonus' => 2, 'color' => '#7C3AED', 'badge' => 'ЗГСГ'],
                'head_gov' => ['name' => 'ГС ГОС', 'level_bonus' => 3, 'color' => '#6D28D9', 'badge' => 'ГСГ']
            ]
        ],
        'illegal_department' => [
            'name' => 'Следящие за нелегал.организациями',
            'description' => 'Нелегальные организации',
            'color' => '#DC2626',
            'positions' => [
                'illegal_overseer' => ['name' => 'Следящий Нелегал', 'level_bonus' => 1, 'color' => '#DC2626', 'badge' => 'СН'],
                'deputy_head_illegal' => ['name' => 'ЗГС НО', 'level_bonus' => 2, 'color' => '#B91C1C', 'badge' => 'ЗГСН'],
                'head_illegal' => ['name' => 'ГС НО', 'level_bonus' => 3, 'color' => '#991B1B', 'badge' => 'ГСН']
            ]
        ],
    ];
    
    public function __construct($pdo) { $this->pdo = $pdo; }
    
    public static function getInstance($pdo) {
        if (self::$instance === null) self::$instance = new self($pdo);
        return self::$instance;
    }
    
    public function getEffectiveLevel($userId) {
        $stmt = $this->pdo->prepare("SELECT admin_level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        return $user ? (int)$user['admin_level'] : 1;
    }
    
    public function hasPermission($userId, $permission) {
        $level = $this->getEffectiveLevel($userId);
        if ($level >= 8) return true;
        $stmt = $this->pdo->prepare("SELECT permissions FROM admin_levels WHERE level = ?");
        $stmt->execute([$level]);
        $perms = $stmt->fetchColumn();
        if (!$perms) return false;
        $permsArray = json_decode($perms, true) ?? [];
        if (in_array('*', $permsArray)) return true;
        $basic = ['dashboard.view','profile.edit_own','admin_list.view','team.view'];
        if (in_array($permission, $basic)) return true;
        return in_array($permission, $permsArray);
    }
    
    public function requirePermission($permission) {
        if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }
        if (!$this->hasPermission($_SESSION['user_id'], $permission)) { die('Доступ запрещён'); }
    }
    
    public function getAdminLevels() { return $this->adminLevels; }
    public function getAllDepartmentsList() { return $this->positions; }
    
    public function assignPosition($userId, $department, $positionKey, $assignedBy) {
        $stmt = $this->pdo->prepare("INSERT INTO admin_positions (user_id, department, position_key, assigned_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE department=VALUES(department), position_key=VALUES(position_key), assigned_by=VALUES(assigned_by), assigned_at=NOW()");
        return $stmt->execute([$userId, $department, $positionKey, $assignedBy]);
    }
    
    public function removePosition($userId) {
        $stmt = $this->pdo->prepare("DELETE FROM admin_positions WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
    
    public function getDepartmentStaff($department) {
        $stmt = $this->pdo->prepare("SELECT ap.*, u.username, u.avatar FROM admin_positions ap JOIN users u ON ap.user_id=u.id WHERE ap.department=?");
        $stmt->execute([$department]);
        $staff = $stmt->fetchAll();
        foreach ($staff as &$m) {
            if (isset($this->positions[$department]['positions'][$m['position_key']])) {
                $d = $this->positions[$department]['positions'][$m['position_key']];
                $m['position_name'] = $d['name']; $m['position_color'] = $d['color']; $m['position_badge'] = $d['badge'];
            }
        }
        return $staff;
    }
    
    public function getUserPosition($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM admin_positions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $pos = $stmt->fetch();
        if ($pos && isset($this->positions[$pos['department']])) {
            $dept = $this->positions[$pos['department']];
            $p = $dept['positions'][$pos['position_key']] ?? null;
            if ($p) {
                $pos['department_name'] = $dept['name'];
                $pos['position_name'] = $p['name'];
                $pos['badge'] = $p['badge']; $pos['color'] = $p['color'];
            }
        }
        return $pos;
    }
}
?>
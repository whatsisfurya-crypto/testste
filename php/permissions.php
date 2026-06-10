<?php
class Permissions {
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
    
    public function requirePermission($permission) {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../index.php');
            exit();
        }
        
        require_once 'roles_system.php';
        $roles = RolesSystem::getInstance($this->pdo);
        
        if (!$roles->hasPermission($_SESSION['user_id'], $permission)) {
            header('HTTP/1.0 403 Forbidden');
            die('Доступ запрещён');
        }
    }
}
?>
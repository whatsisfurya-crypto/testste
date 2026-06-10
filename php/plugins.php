<?php
class PluginManager {
    private $pdo;
    private $pluginsDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->pluginsDir = ROOT_DIR . 'plugins/';
        if (!file_exists($this->pluginsDir)) mkdir($this->pluginsDir, 0755, true);
    }
    
    public function getPluginsList() {
        $stmt = $this->pdo->query("SELECT * FROM plugins ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function togglePlugin($pluginId) {
        $stmt = $this->pdo->prepare("SELECT is_active FROM plugins WHERE id = ?");
        $stmt->execute([$pluginId]);
        $plugin = $stmt->fetch();
        
        if (!$plugin) return ['success' => false, 'message' => 'Плагин не найден'];
        
        $newStatus = $plugin['is_active'] ? 0 : 1;
        $stmt = $this->pdo->prepare("UPDATE plugins SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $pluginId]);
        
        return ['success' => true, 'message' => $newStatus ? 'Плагин активирован' : 'Плагин деактивирован'];
    }
}
?>
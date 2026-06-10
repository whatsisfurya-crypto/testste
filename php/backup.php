<?php
require_once '../config/database.php';

class Backup {
    private $pdo;
    private $backupDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->backupDir = BACKUP_DIR;
        if (!file_exists($this->backupDir)) mkdir($this->backupDir, 0755, true);
    }
    
    public function createBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_{$timestamp}.sql";
        $filepath = $this->backupDir . $filename;
        
        try {
            $tables = [];
            $result = $this->pdo->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) $tables[] = $row[0];
            
            $output = "-- SAMP Admin Backup\n-- Created: " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
                $row = $stmt->fetch(PDO::FETCH_NUM);
                $output .= "DROP TABLE IF EXISTS `{$table}`;\n{$row[1]};\n\n";
                
                $stmt = $this->pdo->query("SELECT * FROM `{$table}`");
                while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $values = array_map(function($v) {
                        if ($v === null) return 'NULL';
                        return "'" . addslashes($v) . "'";
                    }, $row);
                    $output .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                }
                $output .= "\n";
            }
            
            file_put_contents($filepath, $output);
            
            return ['success' => true, 'filename' => $filename];
        } catch(Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>
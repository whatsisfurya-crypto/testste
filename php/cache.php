<?php
class FastCache {
    private static $dir;
    
    public static function init() {
        self::$dir = __DIR__ . '/../cache/';
        if (!file_exists(self::$dir)) mkdir(self::$dir, 0777, true);
    }
    
    public static function get($key) {
        $file = self::$dir . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file)) < CACHE_LIFETIME) {
            return json_decode(file_get_contents($file), true);
        }
        return null;
    }
    
    public static function set($key, $data) {
        $file = self::$dir . md5($key) . '.cache';
        file_put_contents($file, json_encode($data));
    }
    
    public static function delete($key) {
        $file = self::$dir . md5($key) . '.cache';
        if (file_exists($file)) unlink($file);
    }
    
    public static function clear() {
        $files = glob(self::$dir . '*.cache');
        foreach ($files as $file) unlink($file);
    }
}

FastCache::init();
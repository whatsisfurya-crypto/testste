<?php
class SampRcon {
    private $ip;
    private $port;
    private $password;
    private $socket;
    
    public function __construct($ip, $port, $password) {
        $this->ip = $ip;
        $this->port = $port;
        $this->password = $password;
    }
    
    public function connect() {
        $this->socket = @fsockopen($this->ip, $this->port, $errno, $errstr, 5);
        
        if (!$this->socket) {
            return ['success' => false, 'message' => "Не удалось подключиться: $errstr"];
        }
        
        stream_set_timeout($this->socket, 5);
        
        $packet = $this->buildPacket(3, $this->password);
        fwrite($this->socket, $packet);
        
        $response = fread($this->socket, 11);
        
        if (strlen($response) < 11 || ord($response[10]) === -1) {
            fclose($this->socket);
            return ['success' => false, 'message' => 'Ошибка авторизации RCON'];
        }
        
        return ['success' => true];
    }
    
    public function sendCommand($command) {
        $packet = $this->buildPacket(2, $command);
        fwrite($this->socket, $packet);
        
        $response = '';
        while (strlen($response) < 4096) {
            $data = fread($this->socket, 4096);
            $response .= $data;
            if (strlen($data) < 4096) break;
        }
        
        $lines = explode("\n", trim($response));
        return array_filter($lines, function($line) { return !empty(trim($line)); });
    }
    
    private function buildPacket($type, $body) {
        $packet = 'SAMP';
        $packet .= pack('V', rand(1, 9999));
        $packet .= pack('V', $type);
        $packet .= $body;
        $packet .= chr(0);
        $packet .= chr(0);
        return $packet;
    }
    
    public function disconnect() {
        if ($this->socket) fclose($this->socket);
    }
}
?>
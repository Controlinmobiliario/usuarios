<?php

class LoginAttempt {
    private $db;
    private $table = "login_attempts";
    private $ip;
    private $lockoutMinutes;

    public function __construct($db, $ip, $lockoutMinutes = 15) {
        $this->db = $db;
        $this->ip = $ip;
        $this->lockoutMinutes = $lockoutMinutes;

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("IP inválida: $ip");
        }


        if (rand(1, 100) === 1) {
            $this->cleanOldAttempts();
        }
    }

    public function isBlocked($maxAttempts = 5) {
        $query = "SELECT COUNT(*) as attempts 
                  FROM {$this->table} 
                  WHERE ip_address = :ip 
                  AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ip', $this->ip);
        $stmt->bindParam(':minutes', $this->lockoutMinutes, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['attempts'] >= $maxAttempts;
    }

    public function getRemainingWaitTime() {
        $query = "SELECT MIN(attempted_at) as first_attempt 
                  FROM {$this->table} 
                  WHERE ip_address = :ip 
                  AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ip', $this->ip);
        $stmt->bindParam(':minutes', $this->lockoutMinutes, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['first_attempt']) {
            $firstAttemptTime = strtotime($row['first_attempt']);
            $unlockTime = $firstAttemptTime + ($this->lockoutMinutes * 60);
            $remainingSeconds = max(0, $unlockTime - time());
            return ceil($remainingSeconds / 60);
        }

        return 0;
    }

    public function registerFailedAttempt() {
        $query = "INSERT INTO {$this->table} (ip_address) VALUES (:ip)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ip', $this->ip);
        $stmt->execute();
    }

    public function cleanOldAttempts($hours = 24) {
        $query = "DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL :hours HOUR)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':hours', $hours, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function clearAttempts() {
        $query = "DELETE FROM {$this->table} WHERE ip_address = :ip";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ip', $this->ip);
        return $stmt->execute();
    }
}

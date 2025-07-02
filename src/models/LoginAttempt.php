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
    }

    public function isBlocked($maxAttempts = 5) {
        $query = "SELECT COUNT(*) as attempts 
                  FROM {$this->table} 
                  WHERE ip_address = :ip 
                  AND attempt_time > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ip', $this->ip);
        $stmt->bindParam(':minutes', $this->lockoutMinutes, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['attempts'] >= $maxAttempts;
    }

    public function getRemainingWaitTime() {
        $query = "SELECT MIN(attempt_time) as first_attempt 
                  FROM {$this->table} 
                  WHERE ip_address = :ip 
                  AND attempt_time > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
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
}

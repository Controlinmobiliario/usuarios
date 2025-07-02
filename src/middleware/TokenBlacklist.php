<?php

class TokenBlacklist {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function add($token, $exp) {
        $query = "INSERT INTO jwt_blacklist (token, expires_at) VALUES (:token, FROM_UNIXTIME(:exp))";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':exp', $exp);
        return $stmt->execute();
    }

    public function isBlacklisted($token) {
        $query = "SELECT id FROM jwt_blacklist WHERE token = :token AND expires_at > NOW() LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function cleanExpired() {
        $query = "DELETE FROM jwt_blacklist WHERE expires_at < NOW()";
        $stmt = $this->db->prepare($query);
        return $stmt->execute();
    }
}

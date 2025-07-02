<?php

class Auth {
    public static function authenticate($db) {
        $headers = getallheaders();
        $token = null;

        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        } elseif (isset($headers['authorization'])) {
            $token = str_replace('Bearer ', '', $headers['authorization']);
        }

        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Authorization token required']);
            exit();
        }

        try {
            $payload = self::validateJWT($token);

            if (self::isBlacklisted($db, $token)) {
                http_response_code(401);
                echo json_encode(['error' => 'Token is blacklisted']);
                exit();
            }

            $user = new User($db);
            if (!$user->findById($payload['user_id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit();
            }

            if (!$user->is_active) {
                http_response_code(401);
                echo json_encode(['error' => 'Account is deactivated']);
                exit();
            }

            return $user->toArray();

        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }
    }

    private static function validateJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        $header = json_decode(base64_decode(strtr($base64Header, '-_', '+/')), true);
        if (!isset($header['alg']) || $header['alg'] !== 'HS256') {
            throw new Exception('Invalid algorithm');
        }

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::getJWTSecret(), true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if (!hash_equals($expectedSignature, $base64Signature)) {
            throw new Exception('Invalid signature');
        }

        $payload = json_decode(base64_decode(strtr($base64Payload, '-_', '+/')), true);
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            throw new Exception('Token expired');
        }

        return $payload;
    }

    private static function getJWTSecret() {
        return getenv('JWT_SECRET') ?: 'your-super-secret-jwt-key-change-this';
    }

    public static function addToBlacklist($db, $token, $exp) {
        $query = "INSERT INTO jwt_blacklist (token, expires_at) VALUES (:token, FROM_UNIXTIME(:exp))";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':exp', $exp);
        return $stmt->execute();
    }

    private static function isBlacklisted($db, $token) {
        $query = "SELECT id FROM jwt_blacklist WHERE token = :token AND expires_at > NOW() LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }


}

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
            
            // Get user data
            $user = new User($db);
            if (!$user->findById($payload['user_id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'User not found']);
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
            echo json_encode(['error' => 'Invalid or expired token']);
            exit();
        }
    }

    private static function validateJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::getJWTSecret(), true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if ($base64Signature !== $expectedSignature) {
            throw new Exception('Invalid signature');
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);

        if ($payload['exp'] < time()) {
            throw new Exception('Token expired');
        }

        return $payload;
    }

    private static function getJWTSecret() {
        return $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-this';
    }
}

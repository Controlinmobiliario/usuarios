<?php

require_once 'middleware/TokenBlacklist.php';
require_once 'models/LoginAttempt.php';

class UserController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
        $this->user = new User($db);
    }

    public function register() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$this->validateRegistrationData($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            return;
        }

        // Check if email already exists
        if ($this->user->fieldExists('email', $data['email'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already exists']);
            return;
        }

        // Check if username already exists
        if ($this->user->fieldExists('username', $data['username'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Username already exists']);
            return;
        }

        if (strlen($data['password']) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 6 characters']);
            return;
        }

        if (isset($data['phone']) && !empty($data['phone'])) {
            // Validar formato (ej: solo dígitos, con o sin +, 7 a 15 caracteres)
            if (!preg_match('/^\+?[0-9]{7,15}$/', $data['phone'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid phone number format']);
                return;
            }

            if (strlen($data['phone']) < 7 || strlen($data['phone']) > 15) {
                http_response_code(400);
                echo json_encode(['error' => 'Phone number must be between 7 and 15 digits']);
                return;
            }
        
            // Validar si el número ya existe en otro usuario
            if ($this->user->phoneExists($data['phone']) && $this->user->phone !== $data['phone']) {
                http_response_code(409);
                echo json_encode(['error' => 'Phone number already exists']);
                return;
            }
        }


        // Set user properties
        $this->user->username = $data['username'];
        $this->user->email = $data['email'];
        $this->user->password_hash = $data['password'];
        $this->user->first_name = $data['first_name'] ?? '';
        $this->user->last_name = $data['last_name'] ?? '';
        $this->user->phone = $data['phone'] ?? '';
        $this->user->is_verified = false;
        $this->user->is_active = true;

        if ($this->user->create()) {
            http_response_code(201);
            echo json_encode([
                'message' => 'User created successfully',
                'user' => $this->user->toArray()
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create user']);
        }
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password required']);
            return;
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $loginAttempt = new LoginAttempt($this->db, $ip);
    
        if ($loginAttempt->isBlocked()) {
            $waitTime = $loginAttempt->getRemainingWaitTime();
            http_response_code(429); // Too Many Requests
            echo json_encode(['error' => "Too many login attempts. Try again in {$waitTime} minute(s)."]);
            return;
        }

        if ($this->user->findByEmail($data['email']) && $this->user->verifyPassword($data['password'])) {
                if (!$this->user->is_active) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Account is deactivated']);
                    return;
                }

                $loginAttempt->clearAttempts();
                $this->user->updateLastLogin();
                $token = $this->generateJWT($this->user->id);
                
                http_response_code(200);
                echo json_encode([
                    'message' => 'Login successful',
                    'token' => $token,
                    'user' => $this->user->toArray()
                ]);
        } else {
            $loginAttempt->registerFailedAttempt();
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    }

    public function getUsers() {
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        
        $users = $this->user->getAll($limit, $offset);
        
        http_response_code(200);
        echo json_encode([
            'users' => $users,
            'limit' => (int)$limit,
            'offset' => (int)$offset
        ]);
    }

    public function getUser($id) {
        if ($this->user->findById($id)) {
            http_response_code(200);
            echo json_encode(['user' => $this->user->toArray()]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
    }

    public function getCurrentUser($userId) {
        if ($this->user->findById($userId)) {
            http_response_code(200);
            echo json_encode(['user' => $this->user->toArray()]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
    }

    public function updateUser($id, $currentUser) {
        // Only allow users to update their own profile or admin users
        if ($id != $currentUser['id'] && $currentUser['username'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        if (!$this->user->findById($id)) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        // Update only provided fields
        if (isset($data['username'])) {
            // Check if new username already exists (and it's not the current user's username)
            if (
                $data['username'] !== $this->user->username &&
                $this->user->fieldExists('username', $data['username'], $this->user->id)
            ) {
                http_response_code(409);
                echo json_encode(['error' => 'Username already exists']);
                return;
            }
            $this->user->username = $data['username'];
        }
        
        if (isset($data['email'])) {
            // Check if new email already exists (and it's not the current user's email)

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid email format']);
                return;
            }
            
            if (
                $data['email'] !== $this->user->email &&
                $this->user->fieldExists('email', $data['email'], $this->user->id)
            ) {
                http_response_code(409);
                echo json_encode(['error' => 'Email already exists']);
                return;
            }
            $this->user->email = $data['email'];
        }
        
        if (isset($data['first_name'])) $this->user->first_name = $data['first_name'];
        if (isset($data['last_name'])) $this->user->last_name = $data['last_name'];

        if (isset($data['phone']) && !empty($data['phone'])) {
            // Validar formato (ej: solo dígitos, con o sin +, 7 a 15 caracteres)
            if (!preg_match('/^\+?[0-9]{7,15}$/', $data['phone'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid phone number format']);
                return;
            }

            if (strlen($data['phone']) < 7 || strlen($data['phone']) > 15) {
                http_response_code(400);
                echo json_encode(['error' => 'Phone number must be between 7 and 15 digits']);
                return;
            }
        
            // Validar si el número ya existe en otro usuario
            if ($this->user->phoneExists($data['phone']) && $this->user->phone !== $data['phone']) {
                http_response_code(409);
                echo json_encode(['error' => 'Phone number already exists']);
                return;
            }
        }
        
        // Only admin can change these fields
        if ($currentUser['username'] === 'admin') {
            if (isset($data['is_active'])) $this->user->is_active = $data['is_active'];
            if (isset($data['is_verified'])) $this->user->is_verified = $data['is_verified'];
        }

        if ($this->user->update()) {
            http_response_code(200);
            echo json_encode([
                'message' => 'User updated successfully',
                'user' => $this->user->toArray()
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update user']);
        }
    }

    public function deleteUser($id, $currentUser) {
        // Only allow admin to delete users
        if ($currentUser['username'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        if (!$this->user->findById($id)) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        if ($this->user->delete()) {
            http_response_code(200);
            echo json_encode(['message' => 'User deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete user']);
        }
    }

    public function logout() {
        $headers = getallheaders();
        $token = null;
    
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        }
    
        if (!$token) {
            http_response_code(400);
            echo json_encode(['error' => 'Token required']);
            return;
        }
    
        try {
            $payload = $this->validateJWT($token);
    
            $blacklist = new TokenBlacklist($this->db);
            if ($blacklist->add($token, $payload['exp'])) {
                http_response_code(200);
                echo json_encode(['message' => 'Logged out successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to blacklist token']);
            }
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
        }
    }

    public function refreshToken() {
        $headers = getallheaders();
        $token = null;
        
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        }
        
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Token required']);
            return;
        }

        try {
            $payload = $this->validateJWT($token);
            $newToken = $this->generateJWT($payload['user_id']);
            
            http_response_code(200);
            echo json_encode(['token' => $newToken]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
        }
    }

    private function validateRegistrationData($data) {
        return isset($data['username']) && isset($data['email']) && isset($data['password']);
    }

    private function generateJWT($userId) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $userId,
            'exp' => time() + (24 * 60 * 60), // 24 hours
            'iat' => time()
        ]);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->getJWTSecret(), true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    private function validateJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->getJWTSecret(), true);
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

    private function getJWTSecret() {
        return getenv('JWT_SECRET') ?: 'your-super-secret-jwt-key-change-this';
    }
}

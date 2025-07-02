CREATE DATABASE IF NOT EXISTS user_service;
USE user_service;

-- Tabla de usuarios
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    UNIQUE INDEX idx_username (username),
    UNIQUE INDEX idx_email (email),
    UNIQUE INDEX idx_uuid (uuid),
    UNIQUE INDEX idx_phone (phone)
);

-- Tabla de sesiones de usuario
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_token (session_token),
    INDEX idx_user_id (user_id)
);

-- Tabla de restablecimiento de contraseñas
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_token (token),
    UNIQUE INDEX idx_user_id_token_unused (user_id, used)
);

-- Tabla para tokens JWT en lista negra
CREATE TABLE jwt_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(512) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
);

-- Evento automático para limpiar tokens expirados de jwt_blacklist
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_blacklist
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM jwt_blacklist WHERE expires_at < NOW();
END;
//
DELIMITER ;

-- Tabla de intentos de login
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
);

-- Evento automático para limpiar tokens expirados de jwt_blacklist
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_login_attempts
ON SCHEDULE EVERY 24 HOUR
DO
BEGIN
    DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 24 HOUR);
END;
//
DELIMITER ;

-- Insertar usuario admin por defecto
INSERT INTO users (uuid, username, email, password_hash, first_name, last_name, is_active, is_verified) 
VALUES (
    UUID(),
    'admin',
    'admin@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: "password"
    'Admin',
    'User',
    TRUE,
    TRUE
);

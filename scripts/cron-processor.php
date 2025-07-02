<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/TokenBlacklist.php';
require_once __DIR__ . '/../models/LoginAttempt.php';

$database = new Database();
$db = $database->getConnection();

$blacklist = new TokenBlacklist($db);
$loginAttempt = new LoginAttempt($db);

// Limpiar tokens expirados de la blacklist
$blacklistResult = $blacklist->cleanExpired();
echo $blacklistResult
    ? "✅ Expired tokens cleaned from jwt_blacklist.\n"
    : "❌ Failed to clean expired tokens.\n";

// Limpiar intentos de login antiguos (más de 24 horas)
$loginCleanupResult = $loginAttempt->cleanOldAttempts(24); // horas

echo $loginCleanupResult
    ? "✅ Old login attempts cleaned from login_attempts.\n"
    : "❌ Failed to clean old login attempts.\n";

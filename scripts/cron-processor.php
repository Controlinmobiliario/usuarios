<?php
require_once 'config/database.php';
require_once 'middleware/TokenBlacklist.php';

$database = new Database();
$db = $database->getConnection();

$blacklist = new TokenBlacklist($db);
if ($blacklist->cleanExpired()) {
    echo "Expired tokens cleaned successfully.\n";
} else {
    echo "Failed to clean expired tokens.\n";
}

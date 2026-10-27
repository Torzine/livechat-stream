<?php
// api/db.php - Database Configuration & Helpers

$host     = getenv('DB_HOST')     ?: '127.0.0.1';
$dbname   = getenv('DB_NAME')     ?: 'livechat_db';
$username = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASS')     ?: '';
$port     = getenv('DB_PORT')     ?: '3306';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Helper untuk membaca settings global
function get_settings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

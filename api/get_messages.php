<?php
// api/get_messages.php - Fetch approved new messages (Delta Polling)
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/db.php';

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$limit  = isset($_GET['limit']) ? min((int)$_GET['limit'], 30) : 15;

try {
    if ($lastId === 0) {
        $stmt = $pdo->prepare("SELECT id, username, recipient, message, color, created_at FROM messages WHERE is_approved = 1 ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $messages = array_reverse($rows);
    } else {
        $stmt = $pdo->prepare("SELECT id, username, recipient, message, color, created_at FROM messages WHERE id > :last_id AND is_approved = 1 ORDER BY id ASC LIMIT :limit");
        $stmt->bindValue(':last_id', $lastId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll();
    }

    echo json_encode([
        'status'   => 'success',
        'messages' => $messages,
        'count'    => count($messages)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}

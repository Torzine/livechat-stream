<?php
// api/send_message.php - Post a new message with Moderation, SARA/Profanity Filter & Audit Logging
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/db.php';
$settings = get_settings($pdo);

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$username  = '';
$recipient = '';
$message   = '';
$color     = '';

if (is_array($jsonData)) {
    $username  = $jsonData['username'] ?? '';
    $recipient = $jsonData['recipient'] ?? '';
    $message   = $jsonData['message'] ?? '';
    $color     = $jsonData['color'] ?? '';
} else {
    $username  = $_POST['username'] ?? '';
    $recipient = $_POST['recipient'] ?? '';
    $message   = $_POST['message'] ?? '';
    $color     = $_POST['color'] ?? '';
}

// Client IP & User Agent Tracking
$ipAddress = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
if (strpos($ipAddress, ',') !== false) {
    $ipAddress = trim(explode(',', $ipAddress)[0]);
}
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN', 0, 250);

// Clean & sanitize inputs
$username  = trim(strip_tags($username));
$recipient = trim(strip_tags($recipient));
$message   = trim(strip_tags($message));
$color     = trim(strip_tags($color));

if (empty($recipient)) $recipient = 'Semua';

$colorPalette = ['#ff0055', '#00f2fe', '#ffd166', '#06d6a0', '#a8dadc', '#e0aaff', '#ff758c'];
if (empty($color) || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    $hash = crc32($username);
    $color = $colorPalette[abs($hash) % count($colorPalette)];
}

if (empty($username)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nama pengguna tidak boleh kosong']);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Pesan tidak boleh kosong']);
    exit;
}

// Cek apakah user di-banned
$chkUser = $pdo->prepare("SELECT id, is_banned, is_allowed FROM attendees WHERE name = :name LIMIT 1");
$chkUser->execute([':name' => $username]);
$userRow = $chkUser->fetch();
if ($userRow) {
    if ((int)$userRow['is_banned'] === 1 || (int)$userRow['is_allowed'] === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Akun Anda telah dinonaktifkan oleh administrator.']);
        exit;
    }
}

// Cek Mode Registered Only
if (($settings['auth_mode'] ?? '') === 'registered_only' && !$userRow) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Nama Anda belum terdaftar dalam daftar undangan panitia.']);
    exit;
}

if (mb_strlen($username) > 50) $username = mb_substr($username, 0, 50);
if (mb_strlen($recipient) > 50) $recipient = mb_substr($recipient, 0, 50);
if (mb_strlen($message) > 200) $message = mb_substr($message, 0, 200);

// ==========================================
// DETEKSI KATA SARA & KATA KASAR / BANNED WORDS
// ==========================================
$bannedWordsSetting = $settings['banned_words'] ?? '';
$bannedWords = array_map('trim', explode(',', strtolower($bannedWordsSetting)));
$isFlagged = 0;
$flagReason = null;
$isApproved = 1;

$lowerMessage = strtolower($message);
foreach ($bannedWords as $word) {
    if (!empty($word) && preg_match('/\b' . preg_quote($word, '/') . '\b/i', $lowerMessage)) {
        $isFlagged = 1;
        $flagReason = "Mengandung kata dilarang: '$word'";
        $isApproved = 0; // Otomatis dicekal (tidak langsung tayang di layar)
        break;
    }
}

try {
    $stmt = $pdo->prepare("INSERT INTO messages (username, recipient, message, color, ip_address, user_agent, is_approved, is_flagged, flag_reason) 
                           VALUES (:username, :recipient, :message, :color, :ip, :ua, :approved, :flagged, :reason)");
    $stmt->execute([
        ':username' => $username,
        ':recipient'=> $recipient,
        ':message'  => $message,
        ':color'    => $color,
        ':ip'       => $ipAddress,
        ':ua'       => $userAgent,
        ':approved' => $isApproved,
        ':flagged'  => $isFlagged,
        ':reason'   => $flagReason
    ]);

    $newId = (int)$pdo->lastInsertId();

    if ($isFlagged) {
        // Beri tahu user bahwa pesan masuk proses review
        echo json_encode([
            'status'  => 'warning',
            'id'      => $newId,
            'message' => 'Pesan Anda terdeteksi mengandung kata sensitif dan sedang ditinjau oleh panitia.'
        ]);
    } else {
        echo json_encode([
            'status'  => 'success',
            'id'      => $newId,
            'message' => 'Pesan berhasil terkirim'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}

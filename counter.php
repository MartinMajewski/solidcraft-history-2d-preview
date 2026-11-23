<?php
// Visitor counter for the 2D Timeline preview, stored in MySQL.
// GET reads the count; POST adds one visitor. The page posts once per browser (localStorage),
// so the count is browsers, not page loads. No IP address or other personal data is stored.
// shortcut: anyone can POST to inflate the count; add a per-IP limit if that ever matters.

const PAGE = '2d-timeline';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// The database login lives outside the web root, in /files/counter-config.php on the webspace
// (this file is /html/martinmajewski.net/projects/solidcraft/2d-timeline/counter.php), never in git.
$private = dirname(__DIR__, 5) . '/files';
$config = $private . '/counter-config.php';
if (!is_file($config)) {
    http_response_code(503);
    echo '{"error":"not configured"}';
    exit;
}
$db = require $config;

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
        $db['user'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    $pdo->exec('CREATE TABLE IF NOT EXISTS visitor_count (page VARCHAR(64) PRIMARY KEY, visitors BIGINT UNSIGNED NOT NULL DEFAULT 0)');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $pdo->prepare('INSERT INTO visitor_count (page, visitors) VALUES (?, 1) ON DUPLICATE KEY UPDATE visitors = visitors + 1')
            ->execute([PAGE]);
    }
    $read = $pdo->prepare('SELECT visitors FROM visitor_count WHERE page = ?');
    $read->execute([PAGE]);
    echo json_encode(['visitors' => (int) $read->fetchColumn()]);
} catch (Throwable $e) {
    // Details go to /files/counter-error.log only: they can name the database server or user.
    // PDO's messages never contain the password.
    error_log(date('c') . ' ' . get_class($e) . ': ' . $e->getMessage() . "\n", 3, $private . '/counter-error.log');
    http_response_code(500);
    echo '{"error":"database unavailable"}';
}

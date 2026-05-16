<?php
// ============================================
// SPA comment create API
// VULNERABILITY: Stored XSS in content/author_name and SQLi in raw fields.
// ============================================
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    spa_json([
        'ok'    => false,
        'error' => 'POST required',
    ], 405);
}

$conn = db_connect();

$news_id = $_POST['news_id'] ?? '1';
$author_name = $_POST['author_name'] ?? 'spa_guest';
$content = $_POST['content'] ?? '';
$ip = spa_client_ip();

if (trim($content) === '') {
    spa_json([
        'ok'    => false,
        'error' => 'Comment content is required',
    ], 400);
}

if (is_logged_in()) {
    $user = current_user();
    $user_id = $user['id'];
    $author_name = $user['username'];
} else {
    $user_id = 'NULL';
}

// [VULN] Stored XSS and SQLi: user-controlled fields are inserted raw.
$sql = "INSERT INTO comments (news_id, user_id, author_name, content, ip_address, status)
        VALUES ($news_id, $user_id, '$author_name', '$content', '$ip', 'approved')";

if (!$conn->query($sql)) {
    spa_json([
        'ok'       => false,
        'endpoint' => 'spa/comment_add',
        'db_error' => $conn->error,
        'sql'      => $sql,
    ], 500);
}

spa_json([
    'ok'         => true,
    'endpoint'   => 'spa/comment_add',
    'comment_id' => $conn->insert_id,
    'news_id'    => $news_id,
    'sql'        => $sql,
]);

<?php
// ============================================
// SPA comments API
// VULNERABILITY: SQLi in ?news_id= and stored XSS when SPA renders comments.
// ============================================
require_once __DIR__ . '/_bootstrap.php';

$conn = db_connect();
$news_id = $_GET['news_id'] ?? '1';

// [VULN] SQLi: news_id is interpolated directly into SQL.
$sql = "SELECT c.id, c.news_id, c.user_id, c.author_name, c.content,
               c.ip_address, c.status, c.created_at, u.username
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.news_id = $news_id AND c.status = 'approved'
        ORDER BY c.created_at ASC";

$result = $conn->query($sql);

if (!$result) {
    spa_json([
        'ok'       => false,
        'endpoint' => 'spa/comments',
        'news_id'  => $news_id,
        'db_error' => $conn->error,
        'sql'      => $sql,
    ], 500);
}

spa_json([
    'ok'       => true,
    'endpoint' => 'spa/comments',
    'news_id'  => $news_id,
    'count'    => $result->num_rows,
    'comments' => $result->fetch_all(MYSQLI_ASSOC),
    'sql'      => $sql,
]);

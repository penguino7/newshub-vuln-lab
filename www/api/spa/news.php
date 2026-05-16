<?php
// ============================================
// SPA article API
// VULNERABILITY: SQLi UNION/error-based in ?id=.
// ============================================
require_once __DIR__ . '/_bootstrap.php';

$conn = db_connect();
$id = $_GET['id'] ?? '1';

// [VULN] SQLi: id is interpolated directly into SQL.
$sql = "SELECT n.id, n.title, n.content, n.summary, n.tags, n.views,
               n.created_at, u.username, u.email AS author_email,
               c.name AS cat_name, n.category_id
        FROM news n
        JOIN users u ON n.author_id = u.id
        JOIN categories c ON n.category_id = c.id
        WHERE n.id = $id AND n.status = 'published'
        LIMIT 1";

$result = $conn->query($sql);

if (!$result) {
    spa_json([
        'ok'       => false,
        'endpoint' => 'spa/news',
        'id'       => $id,
        'db_error' => $conn->error,
        'sql'      => $sql,
    ], 500);
}

$article = $result->fetch_assoc();

if (!$article) {
    spa_json([
        'ok'       => false,
        'endpoint' => 'spa/news',
        'id'       => $id,
        'error'    => 'Article not found',
        'sql'      => $sql,
    ], 404);
}

@$conn->query("UPDATE news SET views = views + 1 WHERE id = " . intval($id));

spa_json([
    'ok'       => true,
    'endpoint' => 'spa/news',
    'id'       => $id,
    'article'  => $article,
    'sql'      => $sql,
]);

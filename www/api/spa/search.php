<?php
// ============================================
// SPA search API
// VULNERABILITY: SQLi in ?q= and DOM/reflected XSS when SPA renders JSON.
// ============================================
require_once __DIR__ . '/_bootstrap.php';

$conn = db_connect();

$q = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$order = ($sort === 'views') ? 'n.views DESC' : 'n.created_at DESC';
$results = [];
$sql = '';
$db_error = null;

if ($q !== '') {
    // [VULN] SQLi: q is interpolated directly into SQL.
    $where = "(n.title LIKE '%$q%' OR n.content LIKE '%$q%' OR n.tags LIKE '%$q%')";
    $sql = "SELECT n.id, n.title, n.summary, n.tags, n.views, n.created_at,
                   u.username, c.name AS cat_name
            FROM news n
            JOIN users u ON n.author_id = u.id
            JOIN categories c ON n.category_id = c.id
            WHERE $where AND n.status = 'published'
            ORDER BY $order
            LIMIT 20";
} else {
    $sql = "SELECT n.id, n.title, n.summary, n.tags, n.views, n.created_at,
                   u.username, c.name AS cat_name
            FROM news n
            JOIN users u ON n.author_id = u.id
            JOIN categories c ON n.category_id = c.id
            WHERE n.status = 'published'
            ORDER BY $order
            LIMIT 10";
}

$result = $conn->query($sql);

if (!$result) {
    $db_error = $conn->error;
} else {
    $results = $result->fetch_all(MYSQLI_ASSOC);
}

// [VULN] Stored XSS seed: keyword is stored raw and rendered by the SPA logs view.
$ip = spa_client_ip();
$ua = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '');
@$conn->query("INSERT INTO search_logs (keyword, ip_address, user_agent) VALUES ('$q', '$ip', '$ua')");

if ($db_error) {
    spa_json([
        'ok'       => false,
        'endpoint' => 'spa/search',
        'query'    => $q,
        'sort'     => $sort,
        'db_error' => $db_error,
        'sql'      => $sql,
    ], 500);
}

spa_json([
    'ok'       => true,
    'endpoint' => 'spa/search',
    'query'    => $q,
    'sort'     => $sort,
    'count'    => count($results),
    'results'  => $results,
    'sql'      => $sql,
]);

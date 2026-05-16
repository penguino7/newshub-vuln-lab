<?php
// ============================================
// SPA search log API
// VULNERABILITY: SQLi in ?keyword= and stored XSS when SPA renders logs.
// ============================================
require_once __DIR__ . '/_bootstrap.php';

$conn = db_connect();
$keyword = $_GET['keyword'] ?? '';

$where = 'WHERE 1=1';
if ($keyword !== '') {
    // [VULN] SQLi: keyword is interpolated directly into SQL.
    $where .= " AND keyword LIKE '%$keyword%'";
}

$sql = "SELECT id, keyword, ip_address, user_agent, created_at
        FROM search_logs
        $where
        ORDER BY created_at DESC
        LIMIT 30";

$result = $conn->query($sql);

if (!$result) {
    spa_json([
        'ok'       => false,
        'endpoint' => 'spa/logs',
        'keyword'  => $keyword,
        'db_error' => $conn->error,
        'sql'      => $sql,
    ], 500);
}

spa_json([
    'ok'       => true,
    'endpoint' => 'spa/logs',
    'keyword'  => $keyword,
    'count'    => $result->num_rows,
    'logs'     => $result->fetch_all(MYSQLI_ASSOC),
    'sql'      => $sql,
]);

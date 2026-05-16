<?php
// ============================================
// api/suggest.php - API gợi ý tìm kiếm
// VULNERABILITY: DOM-based XSS
// Endpoint này trả về dữ liệu được JS dùng để cập nhật DOM
// JS trong search.php: document.getElementById(...).innerHTML = html 
// với 's' từ response này - không escape
// TEST: Xem search.php với ?q=<img/src=x onerror=alert(1)>
// ============================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';
$conn = db_connect();

$q = $_GET['q'] ?? '';

// Fetch suggestions based on existing article tags/titles
// [VULN] Query không sanitize nhưng lỗi bị ẩn (JSON response)
$suggestions = [];

if (strlen($q) >= 2) {
    // Get matching tags
    $result = $conn->query("SELECT DISTINCT tags FROM news WHERE tags LIKE '%" . $q . "%' AND status='published' LIMIT 5");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            foreach (explode(',', $row['tags']) as $tag) {
                $tag = trim($tag);
                if ($tag && stripos($tag, $q) !== false) {
                    // [VULN] Tag được trả về raw, JS sẽ đưa vào innerHTML không escape
                    $suggestions[] = $tag;
                }
            }
        }
    }

    // Also suggest from search history
    $log_result = $conn->query("SELECT DISTINCT keyword FROM search_logs 
                                 WHERE keyword LIKE '%" . $q . "%' 
                                 AND keyword != '' 
                                 LIMIT 3");
    if ($log_result) {
        while ($row = $log_result->fetch_assoc()) {
            // [VULN] Keyword từ DB (đã lưu raw từ user input) trả về không escape
            $suggestions[] = $row['keyword'];
        }
    }

    $suggestions = array_unique(array_slice($suggestions, 0, 6));
}

echo json_encode([
    'query'       => $q,
    'suggestions' => array_values($suggestions),
    'count'       => count($suggestions)
]);

$conn->close();

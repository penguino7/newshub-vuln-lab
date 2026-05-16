<?php
// ============================================
// api/track.php - Tracking pixel / analytics
// VULNERABILITY: SQLi Time-based blind
// TEST: /api/track.php?page=news&id=1' AND SLEEP(5)--
// TEST: /api/track.php?ref=http://x.com' AND IF(1=1,SLEEP(5),0)--
// Lỗi bị ẩn hoàn toàn - chỉ dùng được time-based
// ============================================
require_once __DIR__ . '/../config/db.php';

// Return 1x1 transparent GIF
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

// Log page view - async style (errors suppressed)
$page  = $_GET['page'] ?? '';
$id    = $_GET['id'] ?? '';
$ref   = $_GET['ref'] ?? '';
$sess  = session_id();
$ip    = $_SERVER['REMOTE_ADDR'] ?? '';

try {
    $conn = db_connect();

    // [VULN] Time-based SQLi: tất cả tham số đều raw
    $page_url = "/news.php?id=$id";
    $sql = "INSERT INTO page_views (page_url, ref_url, session_id, ip_address) 
            VALUES ('$page_url', '$ref', '$sess', '$ip')";

    // Lỗi bị suppress hoàn toàn - time-based only
    @$conn->query($sql);
    $conn->close();
} catch (Exception $e) {
    // Silently fail
}

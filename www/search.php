<?php
// ============================================
// search.php - Tìm kiếm tin tức
// VULNERABILITY 1: SQLi Error-based (tham số ?q=)
// VULNERABILITY 2: Reflected XSS (tham số ?q= rendered raw)
// TEST SQLi: ?q=' OR 1=1-- 
// TEST SQLi: ?q=' AND extractvalue(1,concat(0x7e,database()))--
// TEST XSS:  ?q=<script>alert(document.cookie)</script>
// TEST XSS:  ?q=<img src=x onerror=alert(1)>
// ============================================
require_once __DIR__ . '/config/db.php';
$conn = db_connect();

$q = $_GET['q'] ?? '';
$author = $_GET['author'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

$page_title = "Tìm kiếm";
require_once __DIR__ . '/includes/header.php';

$results = [];
$total = 0;
$error_msg = '';

if ($q !== '' || $author !== '') {
    // [VULN] SQLi: keyword và author không được sanitize
    $where_parts = [];
    if ($q !== '') {
        $where_parts[] = "(n.title LIKE '%$q%' OR n.content LIKE '%$q%' OR n.tags LIKE '%$q%')";
    }
    if ($author !== '') {
        $where_parts[] = "u.username = '$author'";
    }
    $where = implode(' AND ', $where_parts);

    $order = ($sort === 'views') ? 'n.views DESC' : 'n.created_at DESC';

    $sql = "SELECT n.id, n.title, n.summary, n.created_at, n.views, u.username, c.name as cat_name 
            FROM news n 
            JOIN users u ON n.author_id = u.id 
            JOIN categories c ON n.category_id = c.id 
            WHERE $where AND n.status = 'published' 
            ORDER BY $order 
            LIMIT 20";

    $result = $conn->query($sql);

    if (!$result) {
        // [VULN] Error-based SQLi: lỗi hiển thị ra ngoài
        $error_msg = "Lỗi tìm kiếm: " . $conn->error;
    } else {
        $results = $result->fetch_all(MYSQLI_ASSOC);
        $total = count($results);
    }

    // Log search (stores raw keyword - dùng cho stored XSS ở admin)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '');
    $conn->query("INSERT INTO search_logs (keyword, ip_address, user_agent) VALUES ('$q', '$ip', '$ua')");
}
?>

<div id="wrapper">
    <div id="main">

        <div class="box">
            <div class="box-title">🔍 TÌM KIẾM TIN TỨC <span class="vuln-badge">SQLi Error-based | Reflected XSS</span></div>
            <div class="box-content">

                <form action="/search.php" method="GET" style="margin-bottom:15px;">
                    <div style="display:flex; gap:5px; align-items:center; flex-wrap:wrap;">
                        <input type="text" name="q"
                            value="<?= $q /* [VULN] Reflected XSS - no escaping */ ?>"
                            placeholder="Từ khóa tìm kiếm..."
                            style="flex:1;padding:7px;border:1px solid #ccc;min-width:200px;">
                        <select name="sort" style="padding:7px;border:1px solid #ccc;">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                            <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>Xem nhiều</option>
                        </select>
                        <button type="submit" class="btn">Tìm kiếm</button>
                    </div>
                </form>

                <!-- [VULN] Reflected XSS: từ khóa được echo ra không escape -->
                <?php if ($q !== '' || $author !== ''): ?>
                    <div class="msg-info">
                        Kết quả tìm kiếm cho: <strong><?= $q ?: $author ?></strong>
                        — Tìm thấy <strong><?= $total ?></strong> bài viết
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="msg-error">
                        <strong>⚠ Database Error:</strong><br>
                        <?= $error_msg // [VULN] Error-based SQLi output 
                        ?>
                    </div>
                <?php endif; ?>

                <?php if ($results): ?>
                    <?php foreach ($results as $item): ?>
                        <div class="article-item">
                            <div class="article-title">
                                <a href="/news.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                            </div>
                            <div class="article-meta">
                                📂 <?= htmlspecialchars($item['cat_name']) ?> |
                                ✍ <a href="/search.php?author=<?= urlencode($item['username']) ?>"><?= htmlspecialchars($item['username']) ?></a> |
                                🕐 <?= date('d/m/Y', strtotime($item['created_at'])) ?> |
                                👁 <?= $item['views'] ?>
                            </div>
                            <div class="article-summary"><?= htmlspecialchars($item['summary']) ?></div>
                        </div>
                    <?php endforeach; ?>

                <?php elseif ($q !== '' && !$error_msg): ?>
                    <div style="padding:20px; text-align:center; color:#888;">
                        <p>Không tìm thấy kết quả nào cho từ khóa "<strong><?= $q ?></strong>"</p>
                        <p style="margin-top:10px; font-size:13px;">Thử tìm với từ khóa khác hoặc kiểm tra chính tả.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Autocomplete suggestions (triggers DOM XSS) -->
        <?php if ($q): ?>
            <div class="box">
                <div class="box-title">💡 GỢI Ý TÌM KIẾM <span class="vuln-badge">DOM-based XSS trigger</span></div>
                <div class="box-content">
                    <div id="suggestions-container">Đang tải gợi ý...</div>
                </div>
            </div>
            <script>
                // [VULN] DOM-based XSS: giá trị URL được đưa vào innerHTML
                var searchQuery = decodeURIComponent(location.search.split('q=')[1] || '');
                searchQuery = searchQuery.split('&')[0];

                // Fetch suggestions từ API
                fetch('/api/suggest.php?q=' + encodeURIComponent(searchQuery))
                    .then(r => r.json())
                    .then(data => {
                        if (data.suggestions && data.suggestions.length > 0) {
                            var html = '<p style="font-size:12px;color:#666;margin-bottom:5px;">Có thể bạn muốn tìm:</p><ul style="margin-left:20px;">';
                            data.suggestions.forEach(function(s) {
                                // [VULN] innerHTML với dữ liệu từ API không được escape
                                html += '<li><a href="/search.php?q=' + s + '">' + s + '</a></li>';
                            });
                            html += '</ul>';
                            document.getElementById('suggestions-container').innerHTML = html;
                        } else {
                            document.getElementById('suggestions-container').innerHTML =
                                '<p style="color:#888;font-size:13px;">Không có gợi ý nào.</p>';
                        }
                    })
                    .catch(function(e) {
                        document.getElementById('suggestions-container').innerHTML =
                            '<p style="color:#888;">Không thể tải gợi ý.</p>';
                    });
            </script>
        <?php endif; ?>

    </div><!-- /main -->

    <div id="sidebar">
        <!-- Search tips -->
        <div class="box">
            <div class="box-title">💡 HƯỚNG DẪN TÌM KIẾM</div>
            <div class="box-content" style="font-size:13px;">
                <p>• Nhập từ khóa và nhấn <strong>Tìm kiếm</strong></p>
                <p style="margin-top:5px;">• Tìm theo tác giả: thêm <code>?author=username</code></p>
                <p style="margin-top:5px;">• Sắp xếp theo lượt xem hoặc ngày</p>
            </div>
        </div>
        <!-- Recent searches -->
        <div class="box">
            <div class="box-title">🔥 TÌM KIẾM PHỔ BIẾN</div>
            <div class="box-content">
                <?php
                $popular = $conn->query("SELECT keyword, COUNT(*) as cnt FROM search_logs 
                                     WHERE keyword != '' GROUP BY keyword 
                                     ORDER BY cnt DESC LIMIT 8");
                if ($popular) {
                    while ($row = $popular->fetch_assoc()) {
                        echo '<div style="padding:3px 0;font-size:13px;">';
                        echo '<a href="/search.php?q=' . urlencode($row['keyword']) . '">';
                        echo htmlspecialchars($row['keyword']);
                        echo '</a> <span style="color:#888;font-size:11px;">(' . $row['cnt'] . ')</span>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>

</div><!-- /wrapper -->

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>
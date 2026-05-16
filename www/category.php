<?php
// ============================================
// category.php - Lọc theo chuyên mục
// VULNERABILITY: SQLi Boolean-based blind (tham số ?id=)
// TEST: ?id=1 AND 1=1 (trả về kết quả bình thường)
// TEST: ?id=1 AND 1=2 (trả về rỗng - boolean blind)
// TEST: ?id=1 AND (SELECT SUBSTRING(password,1,1) FROM users WHERE id=1)='0'
// ============================================
require_once __DIR__ . '/config/db.php';
$conn = db_connect();

$cat_id = $_GET['id'] ?? '1';
$page_num = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5;
$offset = ($page_num - 1) * $per_page;

// [VULN] Boolean-based blind SQLi - trả về kết quả hoặc trống
// Không hiển thị lỗi DB nên chỉ dùng được boolean/time-based
$cat_query = "SELECT * FROM categories WHERE id = $cat_id";
$cat_result = $conn->query($cat_query);
$category = $cat_result ? $cat_result->fetch_assoc() : null;

// [VULN] News query cũng vulnerable
$sql = "SELECT n.*, u.username, c.name as cat_name 
        FROM news n 
        JOIN users u ON n.author_id = u.id 
        JOIN categories c ON n.category_id = c.id 
        WHERE n.category_id = $cat_id AND n.status = 'published' 
        ORDER BY n.created_at DESC 
        LIMIT $per_page OFFSET $offset";

$result = $conn->query($sql);
$news_list = ($result && !$conn->errno) ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Count total (cũng vulnerable nhưng ít thấy rõ)
$count_result = $conn->query("SELECT COUNT(*) as total FROM news WHERE category_id = $cat_id AND status='published'");
$total_news = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_news / $per_page);

$page_title = $category ? $category['name'] : "Chuyên mục";
require_once __DIR__ . '/includes/header.php';

// All categories for sidebar
$all_cats = $conn->query("SELECT c.*, COUNT(n.id) as cnt FROM categories c LEFT JOIN news n ON c.id = n.category_id WHERE n.status='published' OR n.id IS NULL GROUP BY c.id");
$all_cats_list = $all_cats ? $all_cats->fetch_all(MYSQLI_ASSOC) : [];
?>

<div id="wrapper">
    <div id="main">

        <div class="box">
            <div class="box-title">
                📂 <?= $category ? htmlspecialchars($category['name']) : 'Chuyên mục' ?>
                <span class="vuln-badge">SQLi Boolean-based blind</span>
            </div>
            <div class="box-content">

                <?php if ($category && $category['description']): ?>
                    <div class="msg-info" style="margin-bottom:10px;"><?= htmlspecialchars($category['description']) ?></div>
                <?php endif; ?>

                <?php if (!$category): ?>
                    <div class="msg-error">Chuyên mục không tồn tại.</div>

                <?php elseif (empty($news_list)): ?>
                    <div style="padding:15px; color:#888; text-align:center;">
                        Chưa có bài viết nào trong chuyên mục này.
                    </div>

                <?php else: ?>
                    <p style="font-size:12px;color:#888;margin-bottom:10px;">
                        Hiển thị <?= count($news_list) ?>/<?= $total_news ?> bài viết | Trang <?= $page_num ?>/<?= $total_pages ?>
                    </p>

                    <?php foreach ($news_list as $item): ?>
                        <div class="article-item">
                            <div class="article-title">
                                <a href="/news.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                            </div>
                            <div class="article-meta">
                                ✍ <?= htmlspecialchars($item['username']) ?> |
                                🕐 <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?> |
                                👁 <?= number_format($item['views']) ?> lượt xem
                            </div>
                            <div class="article-summary"><?= htmlspecialchars($item['summary']) ?></div>
                            <div style="margin-top:5px;">
                                <a href="/news.php?id=<?= $item['id'] ?>" style="font-size:12px;color:#cc6600;">Đọc tiếp »</a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination" style="margin-top:15px;padding-top:10px;border-top:1px solid #eee;">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="/category.php?id=<?= $cat_id ?>&page=<?= $i ?>"
                                    class="<?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>

    </div><!-- /main -->

    <div id="sidebar">
        <div class="box">
            <div class="box-title">📁 TẤT CẢ CHUYÊN MỤC</div>
            <div class="box-content">
                <?php foreach ($all_cats_list as $cat): ?>
                    <div style="padding:5px 0;border-bottom:1px solid #eee;">
                        <a href="/category.php?id=<?= $cat['id'] ?>"
                            style="<?= $cat['id'] == $cat_id ? 'font-weight:bold;color:#cc0000;' : '' ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                        <span style="color:#888;font-size:11px;"> (<?= $cat['cnt'] ?>)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="box">
            <div class="box-title">🔗 LIÊN KẾT</div>
            <div class="box-content" style="font-size:13px;">
                <div style="padding:3px 0;"><a href="/index.php">← Trang chủ</a></div>
                <div style="padding:3px 0;"><a href="/search.php">🔍 Tìm kiếm</a></div>
                <div style="padding:3px 0;"><a href="/rss.php">📡 RSS Feed</a></div>
            </div>
        </div>
    </div>

</div><!-- /wrapper -->

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>
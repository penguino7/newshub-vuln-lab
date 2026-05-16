<?php
// ============================================
// news.php - Xem bài viết chi tiết
// VULNERABILITY: SQLi UNION-based (tham số ?id=)
// TEST: ?id=1 UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12--
// ============================================
require_once __DIR__ . '/config/db.php';
$conn = db_connect();

$id = $_GET['id'] ?? '1';

// [VULN] Raw string interpolation - UNION-based SQLi
$query = "SELECT n.*, u.username, u.email as author_email, c.name as cat_name 
          FROM news n 
          JOIN users u ON n.author_id = u.id 
          JOIN categories c ON n.category_id = c.id 
          WHERE n.id = $id AND n.status = 'published'";

$result = $conn->query($query);

if (!$result) {
    // [VULN] Error shown to user - supports error-based SQLi too
    die("<div style='background:#fdd;padding:10px;margin:10px;border:1px solid #f00;'>
         <b>Database Error:</b> " . $conn->error . "
         <br><small>Query: $query</small></div>");
}

$news = $result->fetch_assoc();

if (!$news) {
    // Still show page structure for crawlers
    $page_title = "Không tìm thấy bài viết";
    require_once __DIR__ . '/includes/header.php';
    echo '<div id="wrapper"><div id="main"><div class="box"><div class="box-content">';
    echo '<div class="msg-error">Bài viết không tồn tại hoặc đã bị xóa.</div>';
    echo '<a href="/index.php">← Về trang chủ</a>';
    echo '</div></div></div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Tăng view count
$conn->query("UPDATE news SET views = views + 1 WHERE id = " . intval($id));

$page_title = $news['title'];
require_once __DIR__ . '/includes/header.php';

// Fetch comments
$comments_result = $conn->query("SELECT c.*, u.username FROM comments c 
                                  LEFT JOIN users u ON c.user_id = u.id 
                                  WHERE c.news_id = " . intval($id) . " 
                                  AND c.status = 'approved' 
                                  ORDER BY c.created_at ASC");
$comments = $comments_result ? $comments_result->fetch_all(MYSQLI_ASSOC) : [];

// Related articles
$related = $conn->query("SELECT id, title FROM news 
                          WHERE category_id = " . intval($news['category_id']) . " 
                          AND id != " . intval($id) . " 
                          AND status = 'published' 
                          LIMIT 5");
$related_news = $related ? $related->fetch_all(MYSQLI_ASSOC) : [];
?>

<div id="wrapper">
    <div id="main">

        <!-- Breadcrumb -->
        <div style="font-size:12px; color:#888; margin-bottom:10px;">
            <a href="/index.php">Trang chủ</a> »
            <a href="/category.php?id=<?= $news['category_id'] ?>"><?= htmlspecialchars($news['cat_name']) ?></a> »
            <?= htmlspecialchars(mb_substr($news['title'], 0, 50)) ?>...
        </div>

        <!-- Article content -->
        <div class="box">
            <div class="box-content">
                <h1 style="font-size:22px; margin-bottom:8px; line-height:1.4;">
                    <?= htmlspecialchars($news['title']) ?>
                </h1>
                <div class="article-meta" style="border-bottom:1px solid #eee; padding-bottom:8px; margin-bottom:12px;">
                    ✍ <strong><?= htmlspecialchars($news['username']) ?></strong> |
                    📂 <a href="/category.php?id=<?= $news['category_id'] ?>"><?= htmlspecialchars($news['cat_name']) ?></a> |
                    🕐 <?= date('d/m/Y H:i', strtotime($news['created_at'])) ?> |
                    👁 <?= number_format($news['views']) ?> lượt xem
                    <?php if ($news['tags']): ?>
                        | 🏷 <?php foreach (explode(',', $news['tags']) as $tag): ?>
                            <a href="/search.php?q=<?= urlencode(trim($tag)) ?>"
                                style="background:#e8e8e8;padding:1px 6px;border-radius:10px;font-size:11px;">
                                <?= htmlspecialchars(trim($tag)) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Article body -->
                <div style="line-height:1.7; font-size:14px;">
                    <?= $news['content'] // [NOTE] Content stored as HTML - no XSS here since admin-entered 
                    ?>
                </div>

                <!-- Share buttons (static) -->
                <div style="margin-top:15px; padding-top:10px; border-top:1px solid #eee;">
                    <span style="font-size:12px; color:#888;">Chia sẻ: </span>
                    <a href="https://facebook.com/sharer?u=<?= urlencode(SITE_URL . '/news.php?id=' . $id) ?>"
                        style="background:#1877f2;color:white;padding:3px 10px;font-size:12px;">Facebook</a>
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode(SITE_URL . '/news.php?id=' . $id) ?>"
                        style="background:#1da1f2;color:white;padding:3px 10px;font-size:12px;">Twitter</a>
                </div>
            </div>
        </div>

        <!-- Comments section -->
        <div class="box">
            <div class="box-title">💬 BÌNH LUẬN (<?= count($comments) ?>)</div>
            <div class="box-content">

                <?php if ($comments): ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment">
                            <div>
                                <span class="comment-author">
                                    <?= $comment['username'] ? htmlspecialchars($comment['username']) : htmlspecialchars($comment['author_name']) ?>
                                </span>
                                <span class="comment-date"> — <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></span>
                            </div>
                            <!-- [VULN] Stored XSS: comment content rendered without escaping -->
                            <div class="comment-body"><?= $comment['content'] ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#888; font-style:italic;">Chưa có bình luận nào. Hãy là người đầu tiên!</p>
                <?php endif; ?>

                <!-- Add comment form -->
                <div style="margin-top:15px; padding-top:10px; border-top:1px solid #eee;">
                    <h3 style="font-size:14px; margin-bottom:10px;">Để lại bình luận</h3>
                    <?php if (isset($_GET['commented'])): ?>
                        <div class="msg-success">Bình luận của bạn đã được gửi!</div>
                    <?php endif; ?>
                    <form action="/comment/add.php" method="POST">
                        <input type="hidden" name="news_id" value="<?= intval($id) ?>">
                        <?php if (!is_logged_in()): ?>
                            <div class="form-group">
                                <label>Tên của bạn *</label>
                                <input type="text" name="author_name" required placeholder="Nhập tên hiển thị">
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label>Nội dung bình luận *</label>
                            <textarea name="content" rows="4" required placeholder="Nhập bình luận..."></textarea>
                        </div>
                        <button type="submit" class="btn">Gửi bình luận</button>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /main -->

    <div id="sidebar">
        <!-- Related articles -->
        <div class="box">
            <div class="box-title">📰 BÀI LIÊN QUAN</div>
            <div class="box-content">
                <?php if ($related_news): ?>
                    <?php foreach ($related_news as $r): ?>
                        <div style="padding:4px 0; border-bottom:1px solid #eee; font-size:13px;">
                            <a href="/news.php?id=<?= $r['id'] ?>"><?= htmlspecialchars($r['title']) ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-size:13px; color:#888;">Không có bài liên quan.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Author info -->
        <div class="box">
            <div class="box-title">✍ TÁC GIẢ</div>
            <div class="box-content" style="font-size:13px;">
                <div style="font-weight:bold; margin-bottom:5px;"><?= htmlspecialchars($news['username']) ?></div>
                <a href="/search.php?author=<?= urlencode($news['username']) ?>">Xem tất cả bài viết</a>
            </div>
        </div>
    </div>

</div><!-- /wrapper -->

<!-- Tracking pixel (calls vulnerable API) -->
<img src="/api/track.php?page=news&id=<?= $id ?>&ref=<?= urlencode($_SERVER['HTTP_REFERER'] ?? '') ?>"
    width="1" height="1" style="display:none">

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>
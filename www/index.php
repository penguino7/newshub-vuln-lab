<?php
$page_title = "Trang chủ";
require_once __DIR__ . '/includes/header.php';
$conn = db_connect();

// Latest news - no vulnerability here (homepage is clean)
$result = $conn->query("SELECT n.*, u.username, c.name as cat_name 
                        FROM news n 
                        JOIN users u ON n.author_id = u.id 
                        JOIN categories c ON n.category_id = c.id 
                        WHERE n.status = 'published' 
                        ORDER BY n.created_at DESC LIMIT 10");
$news_list = $result->fetch_all(MYSQLI_ASSOC);

// Top viewed
$top_result = $conn->query("SELECT id, title, views FROM news WHERE status='published' ORDER BY views DESC LIMIT 5");
$top_news = $top_result->fetch_all(MYSQLI_ASSOC);

// Categories
$cat_result = $conn->query("SELECT c.*, COUNT(n.id) as cnt FROM categories c LEFT JOIN news n ON c.id = n.category_id GROUP BY c.id");
$categories = $cat_result->fetch_all(MYSQLI_ASSOC);
?>

<div id="wrapper">
    <div id="main">

        <!-- Search bar -->
        <div class="box">
            <div class="box-content">
                <form id="search-bar" action="/search.php" method="GET">
                    <input type="text" name="q" placeholder="Tìm kiếm tin tức..."
                        value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
                    <button type="submit">🔍 Tìm</button>
                </form>
            </div>
        </div>

        <!-- Breaking news -->
        <div class="box">
            <div class="box-title">🔥 TIN NỔI BẬT</div>
            <div class="box-content">
                <?php if ($news_list): ?>
                    <?php $featured = array_shift($news_list); ?>
                    <div style="padding-bottom:10px; border-bottom:2px solid #003366; margin-bottom:10px;">
                        <div style="font-size:18px; font-weight:bold; margin-bottom:5px;">
                            <a href="/news.php?id=<?= $featured['id'] ?>"><?= htmlspecialchars($featured['title']) ?></a>
                        </div>
                        <div class="article-meta">
                            📂 <?= htmlspecialchars($featured['cat_name']) ?> |
                            ✍ <?= htmlspecialchars($featured['username']) ?> |
                            🕐 <?= date('d/m/Y H:i', strtotime($featured['created_at'])) ?> |
                            👁 <?= number_format($featured['views']) ?> lượt xem
                        </div>
                        <div class="article-summary" style="margin-top:8px;"><?= htmlspecialchars($featured['summary']) ?></div>
                        <div style="margin-top:6px;">
                            <a href="/news.php?id=<?= $featured['id'] ?>" style="font-size:12px; color:#cc6600;">Đọc tiếp »</a>
                        </div>
                    </div>

                    <?php foreach ($news_list as $item): ?>
                        <div class="article-item">
                            <div class="article-title">
                                <a href="/news.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                            </div>
                            <div class="article-meta">
                                📂 <?= htmlspecialchars($item['cat_name']) ?> |
                                ✍ <?= htmlspecialchars($item['username']) ?> |
                                🕐 <?= date('d/m/Y', strtotime($item['created_at'])) ?> |
                                👁 <?= $item['views'] ?> lượt xem
                            </div>
                            <div class="article-summary"><?= htmlspecialchars($item['summary']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Chưa có tin tức nào.</p>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /main -->

    <div id="sidebar">

        <!-- Hot articles -->
        <div class="box">
            <div class="box-title">📈 XEM NHIỀU NHẤT</div>
            <div class="box-content">
                <?php foreach ($top_news as $i => $item): ?>
                    <div style="padding:5px 0; border-bottom:1px solid #eee; font-size:13px;">
                        <span style="color:#cc0000; font-weight:bold; margin-right:5px;"><?= $i + 1 ?>.</span>
                        <a href="/news.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                        <span style="color:#888; font-size:11px;"> (<?= number_format($item['views']) ?>)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Categories -->
        <div class="box">
            <div class="box-title">📁 CHUYÊN MỤC</div>
            <div class="box-content">
                <?php foreach ($categories as $cat): ?>
                    <div style="padding:4px 0; border-bottom:1px solid #eee;">
                        <a href="/category.php?id=<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a>
                        <span style="color:#888; font-size:11px;"> (<?= $cat['cnt'] ?>)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick links (static pages) -->
        <div class="box">
            <div class="box-title">🔗 LIÊN KẾT NHANH</div>
            <div class="box-content" style="font-size:13px;">
                <div style="padding:3px 0;"><a href="/static/about.html">📄 Giới thiệu về chúng tôi</a></div>
                <div style="padding:3px 0;"><a href="/static/contact.html">📧 Liên hệ tòa soạn</a></div>
                <div style="padding:3px 0;"><a href="/static/faq.html">❓ Câu hỏi thường gặp</a></div>
                <div style="padding:3px 0;"><a href="/sitemap.php">🗺 Sitemap</a></div>
                <div style="padding:3px 0;"><a href="/rss.php">📡 RSS Feed</a></div>
            </div>
        </div>

        <!-- Login widget -->
        <?php if (!is_logged_in()): ?>
            <div class="box">
                <div class="box-title">👤 ĐĂNG NHẬP NHANH</div>
                <div class="box-content">
                    <form action="/user/login.php" method="POST">
                        <div class="form-group">
                            <input type="text" name="username" placeholder="Tên đăng nhập">
                        </div>
                        <div class="form-group">
                            <input type="password" name="password" placeholder="Mật khẩu">
                        </div>
                        <button type="submit" class="btn" style="width:100%">Đăng nhập</button>
                        <div style="margin-top:6px; font-size:12px; text-align:center;">
                            <a href="/user/register.php">Đăng ký tài khoản mới</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- /sidebar -->
</div><!-- /wrapper -->

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>
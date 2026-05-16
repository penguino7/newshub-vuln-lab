<?php
// ============================================
// admin/dashboard.php - Bảng điều khiển Admin
// VULNERABILITY: SQLi UNION-based (filter params)
// TEST: ?filter_cat=1 UNION SELECT 1,config_key,config_value,4,5,6,7 FROM secret_configs--
// ============================================
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) redirect('/user/login.php');
// Note: admin check is weak - bypass possible by modifying session

$filter_cat    = $_GET['filter_cat'] ?? '';
$filter_status = $_GET['filter_status'] ?? 'published';
$filter_date   = $_GET['filter_date'] ?? '';

$page_title = "Admin Dashboard";
require_once __DIR__ . '/../includes/header.php';

$conn = db_connect();

// [VULN] SQLi UNION-based - filter_cat raw
$where = "WHERE 1=1";
if ($filter_cat !== '') {
    $where .= " AND n.category_id = $filter_cat";
}
if ($filter_status) {
    $safe_status = $conn->real_escape_string($filter_status);
    $where .= " AND n.status = '$safe_status'";
}

$sql = "SELECT n.id, n.title, n.status, n.views, n.created_at, 
               u.username, c.name as cat_name
        FROM news n
        JOIN users u ON n.author_id = u.id
        JOIN categories c ON n.category_id = c.id
        $where
        ORDER BY n.created_at DESC
        LIMIT 20";

$result = $conn->query($sql);
$news_list = [];
$db_error = '';

if (!$result) {
    // [VULN] Error-based: lỗi SQL hiển thị
    $db_error = $conn->error;
} else {
    $news_list = $result->fetch_all(MYSQLI_ASSOC);
}

// Stats
$stats = [];
$s = $conn->query("SELECT COUNT(*) as total FROM news WHERE status='published'");
$stats['published'] = $s ? $s->fetch_assoc()['total'] : 0;
$s = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['users'] = $s ? $s->fetch_assoc()['total'] : 0;
$s = $conn->query("SELECT COUNT(*) as total FROM comments WHERE status='approved'");
$stats['comments'] = $s ? $s->fetch_assoc()['total'] : 0;
$s = $conn->query("SELECT SUM(views) as total FROM news");
$stats['views'] = $s ? $s->fetch_assoc()['total'] : 0;

// Categories for filter
$cats = $conn->query("SELECT id, name FROM categories");
$cat_list = $cats ? $cats->fetch_all(MYSQLI_ASSOC) : [];

// Recent search logs (shows stored content - Stored XSS trigger for admin)
$search_logs = $conn->query("SELECT keyword, ip_address, created_at FROM search_logs ORDER BY created_at DESC LIMIT 10");
$logs = $search_logs ? $search_logs->fetch_all(MYSQLI_ASSOC) : [];
?>

<div id="wrapper">
    <div id="main">

        <!-- Stats bar -->
        <div style="display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;">
            <?php
            $stat_items = [
                ['📰', 'Bài đăng', $stats['published'], '#003366'],
                ['👥', 'Người dùng', $stats['users'], '#006600'],
                ['💬', 'Bình luận', $stats['comments'], '#660066'],
                ['👁', 'Lượt xem', number_format($stats['views']), '#660000'],
            ];
            foreach ($stat_items as [$icon, $label, $val, $color]): ?>
                <div style="background:<?= $color ?>;color:white;padding:12px 18px;flex:1;min-width:150px;text-align:center;">
                    <div style="font-size:24px;"><?= $icon ?></div>
                    <div style="font-size:20px;font-weight:bold;"><?= $val ?></div>
                    <div style="font-size:12px;opacity:0.8;"><?= $label ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- News management -->
        <div class="box">
            <div class="box-title">
                📰 QUẢN LÝ BÀI VIẾT
                <span class="vuln-badge">SQLi UNION-based (filter_cat)</span>
            </div>
            <div class="box-content">

                <!-- Filters -->
                <form action="/admin/dashboard.php" method="GET" style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                    <select name="filter_cat" style="padding:5px;">
                        <option value="">-- Tất cả chuyên mục --</option>
                        <?php foreach ($cat_list as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filter_cat == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="filter_status" style="padding:5px;">
                        <option value="published" <?= $filter_status === 'published' ? 'selected' : '' ?>>Đã đăng</option>
                        <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Bản nháp</option>
                        <option value="hidden" <?= $filter_status === 'hidden' ? 'selected' : '' ?>>Ẩn</option>
                    </select>
                    <button type="submit" class="btn btn-sm">Lọc</button>
                    <a href="/admin/dashboard.php" class="btn btn-sm" style="background:#888;">Reset</a>
                </form>

                <?php if ($db_error): ?>
                    <div class="msg-error">
                        <strong>⚠ DB Error:</strong> <?= htmlspecialchars($db_error) ?>
                    </div>
                <?php endif; ?>

                <table>
                    <tr>
                        <th>ID</th>
                        <th>Tiêu đề</th>
                        <th>Chuyên mục</th>
                        <th>Tác giả</th>
                        <th>Trạng thái</th>
                        <th>Lượt xem</th>
                        <th>Ngày</th>
                    </tr>
                    <?php if ($news_list): ?>
                        <?php foreach ($news_list as $item): ?>
                            <tr>
                                <td><?= $item['id'] ?></td>
                                <td><a href="/news.php?id=<?= $item['id'] ?>"><?= htmlspecialchars(mb_substr($item['title'], 0, 50)) ?>...</a></td>
                                <td><?= htmlspecialchars($item['cat_name']) ?></td>
                                <td><?= htmlspecialchars($item['username']) ?></td>
                                <td>
                                    <?php
                                    $status_colors = ['published' => '#006600', 'draft' => '#666', 'hidden' => '#cc0000'];
                                    $color = $status_colors[$item['status']] ?? '#333';
                                    ?>
                                    <span style="color:<?= $color ?>;font-weight:bold;"><?= $item['status'] ?></span>
                                </td>
                                <td><?= number_format($item['views']) ?></td>
                                <td><?= date('d/m/Y', strtotime($item['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;color:#888;padding:20px;">Không có dữ liệu</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Search logs (Stored XSS for admin panel) -->
        <div class="box">
            <div class="box-title">
                🔍 LỊCH SỬ TÌM KIẾM GẦN ĐÂY
                <span class="vuln-badge">Stored XSS (keyword from users)</span>
            </div>
            <div class="box-content">
                <table>
                    <tr>
                        <th>Từ khóa</th>
                        <th>IP</th>
                        <th>Thời gian</th>
                    </tr>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <!-- [VULN] Stored XSS: keyword từ user render không escape trong admin panel -->
                                <?= $log['keyword'] ?>
                            </td>
                            <td><?= htmlspecialchars($log['ip_address']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <div style="margin-top:8px;font-size:12px;">
                    <a href="/admin/users.php">→ Quản lý người dùng</a> |
                    <a href="/admin/news_manage.php">→ Thêm/Sửa bài viết</a>
                </div>
            </div>
        </div>

    </div><!-- /main -->

    <div id="sidebar">
        <div class="box">
            <div class="box-title">⚙ ADMIN MENU</div>
            <div class="box-content" style="font-size:13px;">
                <div style="padding:4px 0; border-bottom:1px solid #eee;"><a href="/admin/dashboard.php">📊 Dashboard</a></div>
                <div style="padding:4px 0; border-bottom:1px solid #eee;"><a href="/admin/users.php">👥 Quản lý users</a></div>
                <div style="padding:4px 0; border-bottom:1px solid #eee;"><a href="/admin/news_manage.php">📰 Quản lý bài viết</a></div>
                <div style="padding:4px 0;"><a href="/index.php">← Về trang chủ</a></div>
            </div>
        </div>
    </div>

</div><!-- /wrapper -->

<?php
require_once __DIR__ . '/../includes/footer.php';
$conn->close();
?>
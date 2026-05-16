<?php
// ============================================
// admin/news_manage.php - Thêm/Sửa bài viết
// VULNERABILITY: SQLi (edit ?edit_id=), Stored XSS (tags field)
// TEST: ?edit_id=1 UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12--
// ============================================
require_once __DIR__ . '/../config/db.php';
if (!is_logged_in()) redirect('/user/login.php');

$conn = db_connect();
$edit_id = $_GET['edit_id'] ?? null;
$msg = '';
$edit_news = null;

// Load article for editing
if ($edit_id) {
    // [VULN] SQLi - edit_id raw
    $result = $conn->query("SELECT * FROM news WHERE id = $edit_id");
    if ($result) $edit_news = $result->fetch_assoc();
}

// Handle form submit (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = $_POST['title'] ?? '';
    $content  = $_POST['content'] ?? '';
    $summary  = $_POST['summary'] ?? '';
    $cat_id   = intval($_POST['category_id'] ?? 1);
    $tags     = $_POST['tags'] ?? ''; // [VULN] tags not sanitized
    $status   = $_POST['status'] ?? 'published';
    $post_id  = intval($_POST['post_id'] ?? 0);
    $author_id = current_user()['id'];

    $safe_title   = $conn->real_escape_string($title);
    $safe_content = $conn->real_escape_string($content);
    $safe_summary = $conn->real_escape_string($summary);
    $safe_status  = $conn->real_escape_string($status);

    if ($post_id > 0) {
        // Update: [VULN] tags raw
        $conn->query("UPDATE news SET 
            title='$safe_title', content='$safe_content', summary='$safe_summary',
            category_id=$cat_id, tags='$tags', status='$safe_status'
            WHERE id=$post_id");
        $msg = "Cập nhật bài viết thành công!";
    } else {
        // Insert
        $conn->query("INSERT INTO news (title,content,summary,author_id,category_id,tags,status)
            VALUES ('$safe_title','$safe_content','$safe_summary',$author_id,$cat_id,'$tags','$safe_status')");
        $msg = "Thêm bài viết thành công!";
    }
}

$cats = $conn->query("SELECT id, name FROM categories");
$cat_list = $cats ? $cats->fetch_all(MYSQLI_ASSOC) : [];

$page_title = $edit_news ? "Sửa bài viết" : "Thêm bài viết";
require_once __DIR__ . '/../includes/header.php';
?>

<div id="wrapper">
    <div id="main">
        <div class="box">
            <div class="box-title">
                <?= $edit_news ? '✏ SỬA BÀI VIẾT' : '➕ THÊM BÀI VIẾT' ?>
                <span class="vuln-badge">SQLi (edit_id) | Stored XSS (tags)</span>
            </div>
            <div class="box-content">
                <?php if ($msg): ?>
                    <div class="msg-success"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form action="/admin/news_manage.php" method="POST">
                    <input type="hidden" name="post_id" value="<?= $edit_news ? $edit_news['id'] : 0 ?>">

                    <div class="form-group">
                        <label>Tiêu đề *</label>
                        <input type="text" name="title" required
                            value="<?= htmlspecialchars($edit_news['title'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Tóm tắt</label>
                        <textarea name="summary" rows="2"><?= htmlspecialchars($edit_news['summary'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Nội dung *</label>
                        <textarea name="content" rows="10" required><?= htmlspecialchars($edit_news['content'] ?? '') ?></textarea>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <div class="form-group" style="flex:1;">
                            <label>Chuyên mục</label>
                            <select name="category_id">
                                <?php foreach ($cat_list as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"
                                        <?= isset($edit_news['category_id']) && $edit_news['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Trạng thái</label>
                            <select name="status">
                                <?php foreach (['published', 'draft', 'hidden'] as $s): ?>
                                    <option value="<?= $s ?>" <?= isset($edit_news['status']) && $edit_news['status'] == $s ? 'selected' : '' ?>>
                                        <?= $s ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex:2;">
                            <label>Tags (cách nhau bởi dấu phẩy)</label>
                            <!-- [VULN] Tags stored raw - Stored XSS khi tags render -->
                            <input type="text" name="tags"
                                value="<?= htmlspecialchars($edit_news['tags'] ?? '') ?>"
                                placeholder="AI,công nghệ,tin tức">
                        </div>
                    </div>
                    <button type="submit" class="btn">
                        <?= $edit_news ? 'Cập nhật bài viết' : 'Đăng bài viết' ?>
                    </button>
                    <?php if ($edit_news): ?>
                        <a href="/admin/news_manage.php" class="btn" style="background:#888;margin-left:8px;">Thêm mới</a>
                        <a href="/news.php?id=<?= $edit_news['id'] ?>" class="btn" style="background:#336600;margin-left:8px;">Xem bài</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- News list with edit links -->
        <div class="box">
            <div class="box-title">📋 DANH SÁCH BÀI VIẾT</div>
            <div class="box-content">
                <?php
                $all_news = $conn->query("SELECT n.id, n.title, n.status, c.name as cat_name FROM news n JOIN categories c ON n.category_id=c.id ORDER BY n.id DESC LIMIT 20");
                $all_news_list = $all_news ? $all_news->fetch_all(MYSQLI_ASSOC) : [];
                ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Tiêu đề</th>
                        <th>Chuyên mục</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                    <?php foreach ($all_news_list as $n): ?>
                        <tr>
                            <td><?= $n['id'] ?></td>
                            <td><?= htmlspecialchars(mb_substr($n['title'], 0, 50)) ?>...</td>
                            <td><?= htmlspecialchars($n['cat_name']) ?></td>
                            <td><?= $n['status'] ?></td>
                            <td>
                                <a href="/admin/news_manage.php?edit_id=<?= $n['id'] ?>" class="btn btn-sm">Sửa</a>
                                <a href="/news.php?id=<?= $n['id'] ?>" class="btn btn-sm" style="background:#336600;">Xem</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div id="sidebar">
        <div class="box">
            <div class="box-title">⚙ ADMIN MENU</div>
            <div class="box-content" style="font-size:13px;">
                <div style="padding:4px 0;border-bottom:1px solid #eee;"><a href="/admin/dashboard.php">📊 Dashboard</a></div>
                <div style="padding:4px 0;border-bottom:1px solid #eee;"><a href="/admin/users.php">👥 Quản lý users</a></div>
                <div style="padding:4px 0;border-bottom:1px solid #eee;"><a href="/admin/news_manage.php" style="font-weight:bold;">📰 Quản lý bài viết</a></div>
                <div style="padding:4px 0;"><a href="/index.php">← Về trang chủ</a></div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
$conn->close();
?>
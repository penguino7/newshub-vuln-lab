<?php
// ============================================
// user/profile.php - Hồ sơ người dùng
// VULNERABILITY 1: Stored XSS (bio field rendered raw)
// VULNERABILITY 2: SQLi Time-based (tham số ?user=)
// TEST Stored XSS: đăng ký với bio = <script>alert(document.cookie)</script>
// TEST Time-based: ?user=admin' AND SLEEP(5)--
// TEST Time-based: ?user=admin' AND IF(1=1,SLEEP(5),0)--
// ============================================
require_once __DIR__ . '/../config/db.php';
$conn = db_connect();

// View someone's profile by username (URL param)
$view_user = $_GET['user'] ?? (is_logged_in() ? current_user()['username'] : null);

if (!$view_user) {
    redirect('/user/login.php');
}

// [VULN] Time-based SQLi: username trong query không được escape
$sql = "SELECT u.*, 
               (SELECT COUNT(*) FROM comments WHERE user_id = u.id) as comment_count,
               (SELECT COUNT(*) FROM news WHERE author_id = u.id AND status='published') as article_count
        FROM users u
        WHERE u.username = '$view_user'
        LIMIT 1";

$result = $conn->query($sql);

if (!$result) {
    // Lỗi im lặng để khuyến khích time-based thay vì error-based
    $profile_user = null;
} else {
    $profile_user = $result->fetch_assoc();
}

$is_own_profile = is_logged_in() && $profile_user && current_user()['id'] == $profile_user['id'];

// Handle profile update
$update_msg = '';
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $new_bio    = $_POST['bio'] ?? '';
    $new_email  = $_POST['email'] ?? '';

    // [VULN] Stored XSS: bio được lưu và hiển thị lại không escape
    $uid = $profile_user['id'];
    $new_email_safe = $conn->real_escape_string($new_email);
    $conn->query("UPDATE users SET bio = '$new_bio', email = '$new_email_safe' WHERE id = $uid");
    $update_msg = "Cập nhật hồ sơ thành công!";

    // Reload
    $result2 = $conn->query($sql);
    $profile_user = $result2 ? $result2->fetch_assoc() : $profile_user;
}

// Articles by this user
$articles = [];
if ($profile_user) {
    $art_result = $conn->query("SELECT id, title, created_at, views FROM news 
                                 WHERE author_id = " . intval($profile_user['id']) . " 
                                 AND status = 'published' 
                                 ORDER BY created_at DESC LIMIT 5");
    $articles = $art_result ? $art_result->fetch_all(MYSQLI_ASSOC) : [];
}

$page_title = $profile_user ? "Hồ sơ: " . $profile_user['username'] : "Hồ sơ người dùng";
require_once __DIR__ . '/../includes/header.php';
?>

<div id="wrapper">
    <div id="main">

        <?php if (!$profile_user): ?>
            <div class="box">
                <div class="box-content">
                    <div class="msg-error">Không tìm thấy người dùng này.</div>
                    <a href="/index.php">← Về trang chủ</a>
                </div>
            </div>

        <?php else: ?>

            <!-- Profile header -->
            <div class="box">
                <div class="box-title">
                    👤 HỒ SƠ: <?= htmlspecialchars($profile_user['username']) ?>
                    <?php if ($profile_user['role'] === 'admin'): ?>
                        <span style="background:#cc0000;color:white;padding:1px 6px;font-size:11px;margin-left:5px;">ADMIN</span>
                    <?php elseif ($profile_user['role'] === 'editor'): ?>
                        <span style="background:#0066cc;color:white;padding:1px 6px;font-size:11px;margin-left:5px;">EDITOR</span>
                    <?php endif; ?>
                    <span class="vuln-badge">Stored XSS (bio) | SQLi Time-based</span>
                </div>
                <div class="box-content">

                    <table style="width:auto;margin-bottom:10px;">
                        <tr>
                            <td style="padding:4px 15px 4px 0;font-weight:bold;color:#555;font-size:13px;">Username:</td>
                            <td style="font-size:13px;"><?= htmlspecialchars($profile_user['username']) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:4px 15px 4px 0;font-weight:bold;color:#555;font-size:13px;">Email:</td>
                            <td style="font-size:13px;"><?= htmlspecialchars($profile_user['email']) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:4px 15px 4px 0;font-weight:bold;color:#555;font-size:13px;">Thành viên từ:</td>
                            <td style="font-size:13px;"><?= date('d/m/Y', strtotime($profile_user['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:4px 15px 4px 0;font-weight:bold;color:#555;font-size:13px;">Đăng nhập lần cuối:</td>
                            <td style="font-size:13px;"><?= $profile_user['last_login'] ? date('d/m/Y H:i', strtotime($profile_user['last_login'])) : 'Chưa có' ?></td>
                        </tr>
                        <tr>
                            <td style="padding:4px 15px 4px 0;font-weight:bold;color:#555;font-size:13px;">Bài viết:</td>
                            <td style="font-size:13px;"><?= $profile_user['article_count'] ?></td>
                        </tr>
                        <tr>
                            <td style="padding:4px 15px 4px 0;font-weight:bold;color:#555;font-size:13px;">Bình luận:</td>
                            <td style="font-size:13px;"><?= $profile_user['comment_count'] ?></td>
                        </tr>
                    </table>

                    <div style="margin-top:10px;padding-top:10px;border-top:1px solid #eee;">
                        <strong style="font-size:13px;">Giới thiệu:</strong>
                        <div style="margin-top:6px;font-size:13px;background:#f9f9f9;padding:8px;border:1px solid #eee;">
                            <!-- [VULN] Stored XSS: bio rendered without escaping -->
                            <?= $profile_user['bio'] ?: '<span style="color:#888;font-style:italic;">Chưa có giới thiệu.</span>' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit profile (own profile only) -->
            <?php if ($is_own_profile): ?>
                <div class="box">
                    <div class="box-title">✏ CẬP NHẬT HỒ SƠ</div>
                    <div class="box-content">
                        <?php if ($update_msg): ?>
                            <div class="msg-success"><?= htmlspecialchars($update_msg) ?></div>
                        <?php endif; ?>
                        <form action="/user/profile.php" method="POST">
                            <input type="hidden" name="action" value="update">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($profile_user['email']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Giới thiệu bản thân</label>
                                <textarea name="bio" rows="4"><?= htmlspecialchars($profile_user['bio']) ?></textarea>
                                <small style="color:#888;">HTML được phép. Hiển thị trên hồ sơ công khai.</small>
                            </div>
                            <button type="submit" class="btn">Lưu thay đổi</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Articles by user -->
            <?php if ($articles): ?>
                <div class="box">
                    <div class="box-title">📰 BÀI VIẾT GẦN ĐÂY</div>
                    <div class="box-content">
                        <?php foreach ($articles as $art): ?>
                            <div class="article-item">
                                <div class="article-title">
                                    <a href="/news.php?id=<?= $art['id'] ?>"><?= htmlspecialchars($art['title']) ?></a>
                                </div>
                                <div class="article-meta">
                                    🕐 <?= date('d/m/Y', strtotime($art['created_at'])) ?> |
                                    👁 <?= number_format($art['views']) ?> lượt xem
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div><!-- /main -->

    <div id="sidebar">
        <div class="box">
            <div class="box-title">🔗 TÀI KHOẢN</div>
            <div class="box-content" style="font-size:13px;">
                <?php if (is_logged_in()): ?>
                    <div style="padding:3px 0;"><a href="/user/profile.php">👤 Hồ sơ của tôi</a></div>
                    <?php if (is_admin()): ?>
                        <div style="padding:3px 0;"><a href="/admin/dashboard.php">⚙ Quản trị</a></div>
                    <?php endif; ?>
                    <div style="padding:3px 0;"><a href="/user/logout.php">🚪 Đăng xuất</a></div>
                <?php else: ?>
                    <div style="padding:3px 0;"><a href="/user/login.php">🔑 Đăng nhập</a></div>
                    <div style="padding:3px 0;"><a href="/user/register.php">📝 Đăng ký</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /wrapper -->

<?php
require_once __DIR__ . '/../includes/footer.php';
$conn->close();
?>
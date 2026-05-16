<?php
// ============================================
// user/register.php - Đăng ký tài khoản
// VULNERABILITY: SQLi (second-order) + Stored XSS in bio
// ============================================
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) redirect('/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email    = trim($_POST['email'] ?? '');
    $bio      = $_POST['bio'] ?? '';

    if (!$username || !$password || !$email) {
        $error = "Vui lòng điền đầy đủ các trường bắt buộc.";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        $conn = db_connect();

        // Check duplicate
        $check = $conn->query("SELECT id FROM users WHERE username = '" . $conn->real_escape_string($username) . "'");
        if ($check->num_rows > 0) {
            $error = "Tên đăng nhập đã tồn tại.";
        } else {
            $hashed = md5($password);
            // [VULN] bio không được sanitize - Stored XSS khi hiển thị profile
            // [VULN] Second-order SQLi: bio được lưu raw, dùng ở chỗ khác
            $sql = "INSERT INTO users (username, password, email, bio, role) 
                    VALUES ('" . $conn->real_escape_string($username) . "', 
                            '$hashed', 
                            '" . $conn->real_escape_string($email) . "', 
                            '$bio', 
                            'user')";
            if ($conn->query($sql)) {
                $conn->close();
                redirect('/user/login.php?msg=registered');
            } else {
                $error = "Lỗi đăng ký: " . $conn->error;
            }
        }
        $conn->close();
    }
}

$page_title = "Đăng ký tài khoản";
require_once __DIR__ . '/../includes/header.php';
?>

<div id="wrapper">
    <div id="main" style="max-width:480px;margin:0 auto;">

        <div class="box">
            <div class="box-title">📝 ĐĂNG KÝ TÀI KHOẢN <span class="vuln-badge">Stored XSS (bio)</span></div>
            <div class="box-content">

                <?php if ($error): ?>
                    <div class="msg-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="/user/register.php" method="POST">
                    <div class="form-group">
                        <label>Tên đăng nhập *</label>
                        <input type="text" name="username" required
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            placeholder="Chỉ dùng chữ cái, số, dấu gạch dưới">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            placeholder="email@example.com">
                    </div>
                    <div class="form-group">
                        <label>Mật khẩu *</label>
                        <input type="password" name="password" required placeholder="Ít nhất 6 ký tự">
                    </div>
                    <div class="form-group">
                        <label>Giới thiệu bản thân</label>
                        <textarea name="bio" rows="3"
                            placeholder="Viết vài dòng giới thiệu về bạn..."><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
                        <small style="color:#888;">Hiển thị trên trang hồ sơ công khai của bạn.</small>
                    </div>
                    <button type="submit" class="btn" style="width:100%;padding:8px;">Đăng ký</button>
                </form>

                <div style="margin-top:12px;text-align:center;font-size:13px;">
                    Đã có tài khoản? <a href="/user/login.php">Đăng nhập</a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
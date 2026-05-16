<?php
// ============================================
// user/login.php - Đăng nhập
// VULNERABILITY: SQLi Authentication Bypass
// TEST: username = admin'-- (bypass password)
// TEST: username = ' OR '1'='1'-- 
// TEST: username = ' OR 1=1 LIMIT 1--
// ============================================
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $conn = db_connect();

        // [VULN] SQLi Auth bypass - cả username và password đều raw
        $sql = "SELECT * FROM users 
                WHERE username = '$username' 
                AND password = '" . md5($password) . "' 
                LIMIT 1";

        $result = $conn->query($sql);

        if (!$result) {
            // [VULN] Error shown - error-based SQLi possible
            $error = "Lỗi hệ thống: " . $conn->error;
        } elseif ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['user'] = [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ];

            // Update last login
            $conn->query("UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);

            $conn->close();
            redirect('/index.php');
        } else {
            $error = "Tên đăng nhập hoặc mật khẩu không đúng.";
        }

        $conn->close();
    } else {
        $error = "Vui lòng nhập đầy đủ thông tin.";
    }
}

$page_title = "Đăng nhập";
require_once __DIR__ . '/../includes/header.php';
?>

<div id="wrapper">
    <div id="main" style="max-width:450px; margin:0 auto;">

        <div class="box">
            <div class="box-title">👤 ĐĂNG NHẬP <span class="vuln-badge">SQLi Auth Bypass</span></div>
            <div class="box-content">

                <?php if ($error): ?>
                    <div class="msg-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="msg-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'registered'): ?>
                    <div class="msg-success">Đăng ký thành công! Vui lòng đăng nhập.</div>
                <?php endif; ?>

                <form action="/user/login.php" method="POST">
                    <div class="form-group">
                        <label>Tên đăng nhập</label>
                        <input type="text" name="username" required
                            value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                            placeholder="Nhập tên đăng nhập" autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label>Mật khẩu</label>
                        <input type="password" name="password" required
                            placeholder="Nhập mật khẩu" autocomplete="current-password">
                    </div>
                    <div class="form-group" style="display:flex; justify-content:space-between; align-items:center;">
                        <label style="display:flex;align-items:center;gap:5px;font-weight:normal;">
                            <input type="checkbox" name="remember"> Nhớ đăng nhập
                        </label>
                        <a href="#" style="font-size:12px;">Quên mật khẩu?</a>
                    </div>
                    <button type="submit" class="btn" style="width:100%; padding:8px;">Đăng nhập</button>
                </form>

                <div style="margin-top:15px; padding-top:10px; border-top:1px solid #eee; font-size:13px; text-align:center;">
                    Chưa có tài khoản? <a href="/user/register.php">Đăng ký ngay</a>
                </div>

                <!-- Demo accounts hint (for lab) -->
                <div style="margin-top:10px; background:#fffbe6; border:1px solid #ffe58f; padding:8px; font-size:12px;">
                    <strong>🔬 Lab accounts:</strong><br>
                    admin / admin123 | editor1 / editor123 | thinhnv / password123
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
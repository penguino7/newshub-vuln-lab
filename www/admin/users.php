<?php
// ============================================
// admin/users.php - Quản lý người dùng
// VULNERABILITY: SQLi Boolean-based blind (search_user param)
// TEST: ?search_user=admin' AND 1=1-- (trả về kết quả)
// TEST: ?search_user=admin' AND 1=2-- (trả về rỗng)
// TEST: ?search_user=admin' AND (SELECT COUNT(*) FROM secret_configs)>0--
// ============================================
require_once __DIR__ . '/../config/db.php';
if (!is_logged_in()) redirect('/user/login.php');

$conn = db_connect();
$search_user = $_GET['search_user'] ?? '';
$filter_role = $_GET['role'] ?? '';

$page_title = "Quản lý người dùng";
require_once __DIR__ . '/../includes/header.php';

// [VULN] Boolean-based blind SQLi: search_user raw
$where = "WHERE 1=1";
if ($search_user !== '') {
    $where .= " AND (username LIKE '%$search_user%' OR email LIKE '%$search_user%')";
}
if ($filter_role) {
    $safe_role = $conn->real_escape_string($filter_role);
    $where .= " AND role = '$safe_role'";
}

// Suppress errors intentionally - makes only boolean/time based work
$result = @$conn->query("SELECT id, username, email, role, bio, created_at, last_login FROM users $where ORDER BY id ASC");
$users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$count = count($users);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $del_id = intval($_POST['delete_user']);
    if ($del_id > 1) { // Protect admin id=1
        $conn->query("DELETE FROM users WHERE id = $del_id");
        redirect('/admin/users.php?msg=deleted');
    }
}
?>

<div id="wrapper">
    <div id="main">

        <div class="box">
            <div class="box-title">
                👥 QUẢN LÝ NGƯỜI DÙNG (<?= $count ?>)
                <span class="vuln-badge">SQLi Boolean-based blind</span>
            </div>
            <div class="box-content">

                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                    <div class="msg-success">Xóa người dùng thành công.</div>
                <?php endif; ?>

                <!-- Search/Filter form -->
                <form action="/admin/users.php" method="GET" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                    <input type="text" name="search_user"
                        value="<?= htmlspecialchars($search_user) ?>"
                        placeholder="Tìm theo username / email..."
                        style="padding:5px;border:1px solid #ccc;flex:1;min-width:180px;">
                    <select name="role" style="padding:5px;">
                        <option value="">-- Tất cả role --</option>
                        <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="editor" <?= $filter_role === 'editor' ? 'selected' : '' ?>>Editor</option>
                        <option value="user" <?= $filter_role === 'user' ? 'selected' : '' ?>>User</option>
                    </select>
                    <button type="submit" class="btn btn-sm">Tìm kiếm</button>
                    <a href="/admin/users.php" class="btn btn-sm" style="background:#888;">Reset</a>
                </form>

                <table>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Bio (preview)</th>
                        <th>Ngày tạo</th>
                        <th>Đăng nhập cuối</th>
                        <th></th>
                    </tr>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td>
                                <a href="/user/profile.php?user=<?= urlencode($user['username']) ?>">
                                    <?= htmlspecialchars($user['username']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <?php
                                $role_colors = ['admin' => '#cc0000', 'editor' => '#0066cc', 'user' => '#333'];
                                $rc = $role_colors[$user['role']] ?? '#333';
                                ?>
                                <strong style="color:<?= $rc ?>"><?= $user['role'] ?></strong>
                            </td>
                            <td style="max-width:200px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                                <!-- [VULN] Stored XSS: bio rendered raw in admin table -->
                                <?= $user['bio'] ?: '<span style="color:#ccc;">—</span>' ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                            <td><?= $user['last_login'] ? date('d/m/Y', strtotime($user['last_login'])) : '—' ?></td>
                            <td>
                                <?php if ($user['id'] > 1): ?>
                                    <form action="/admin/users.php" method="POST" style="display:inline;"
                                        onsubmit="return confirm('Xóa user này?')">
                                        <input type="hidden" name="delete_user" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;color:#888;padding:15px;">Không tìm thấy người dùng nào</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

    </div><!-- /main -->

    <div id="sidebar">
        <div class="box">
            <div class="box-title">⚙ ADMIN MENU</div>
            <div class="box-content" style="font-size:13px;">
                <div style="padding:4px 0;border-bottom:1px solid #eee;"><a href="/admin/dashboard.php">📊 Dashboard</a></div>
                <div style="padding:4px 0;border-bottom:1px solid #eee;"><a href="/admin/users.php" style="font-weight:bold;">👥 Quản lý users</a></div>
                <div style="padding:4px 0;border-bottom:1px solid #eee;"><a href="/admin/news_manage.php">📰 Quản lý bài viết</a></div>
                <div style="padding:4px 0;"><a href="/index.php">← Về trang chủ</a></div>
            </div>
        </div>
        <div class="box">
            <div class="box-title">📊 THỐNG KÊ NHANH</div>
            <div class="box-content" style="font-size:13px;">
                <?php
                $total_u = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
                $total_admin = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch_assoc()['c'];
                $total_editor = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='editor'")->fetch_assoc()['c'];
                ?>
                <div>Tổng: <strong><?= $total_u ?></strong> users</div>
                <div>Admin: <strong><?= $total_admin ?></strong></div>
                <div>Editor: <strong><?= $total_editor ?></strong></div>
                <div>User: <strong><?= $total_u - $total_admin - $total_editor ?></strong></div>
            </div>
        </div>
    </div>

</div><!-- /wrapper -->

<?php
require_once __DIR__ . '/../includes/footer.php';
$conn->close();
?>
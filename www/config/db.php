<?php
// ============================================
// NewsHub Lab - Database Configuration
// ============================================

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'newshub');
define('DB_USER', getenv('DB_USER') ?: 'webuser');
define('DB_PASS', getenv('DB_PASS') ?: 'webpass123');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'NewsHub');
define('SITE_URL', 'http://localhost:12001');

// ============================================
// INTENTIONALLY VULNERABLE - NO REAL SECURITY
// ============================================
// Kết nối MySQLi (dùng cho các query SQLi raw)
function db_connect()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        // Hiển thị lỗi - intentionally verbose for error-based SQLi
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset(DB_CHARSET);
    return $conn;
}

// Kết nối PDO (dùng cho một số endpoint)
function db_pdo()
{
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Intentionally show error details
        die("PDO Error: " . $e->getMessage());
    }
}

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper: get current logged-in user
function current_user()
{
    return $_SESSION['user'] ?? null;
}

// Helper: is logged in
function is_logged_in()
{
    return isset($_SESSION['user']);
}

// Helper: is admin
function is_admin()
{
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

// Helper: redirect
function redirect($url)
{
    header("Location: $url");
    exit;
}

<?php
require_once __DIR__ . '/../config/db.php';
$current_user = current_user();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?><?= SITE_NAME ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            background: #f0f0f0;
            color: #333;
        }

        a {
            color: #0066cc;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* Header */
        #header {
            background: #003366;
            color: white;
            padding: 10px 20px;
        }

        #header h1 {
            display: inline-block;
            font-size: 22px;
        }

        #header h1 a {
            color: white;
        }

        #nav {
            background: #004080;
            padding: 5px 20px;
        }

        #nav a {
            color: #cce0ff;
            margin-right: 15px;
            font-size: 13px;
        }

        #nav a:hover {
            color: white;
        }

        #nav .user-info {
            float: right;
            color: #aad0ff;
        }

        /* Layout */
        #wrapper {
            max-width: 1100px;
            margin: 15px auto;
            display: flex;
            gap: 15px;
            padding: 0 10px;
        }

        #main {
            flex: 1;
            min-width: 0;
        }

        #sidebar {
            width: 240px;
            flex-shrink: 0;
        }

        /* Boxes */
        .box {
            background: white;
            border: 1px solid #ccc;
            margin-bottom: 15px;
        }

        .box-title {
            background: #003366;
            color: white;
            padding: 6px 10px;
            font-size: 13px;
            font-weight: bold;
        }

        .box-content {
            padding: 10px;
        }

        /* Articles */
        .article-item {
            border-bottom: 1px solid #eee;
            padding: 8px 0;
        }

        .article-item:last-child {
            border-bottom: none;
        }

        .article-title {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .article-meta {
            font-size: 11px;
            color: #888;
        }

        .article-summary {
            font-size: 13px;
            color: #555;
            margin-top: 4px;
        }

        /* Forms */
        .form-group {
            margin-bottom: 10px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #ccc;
            font-size: 13px;
        }

        .btn {
            padding: 6px 16px;
            background: #003366;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 13px;
        }

        .btn:hover {
            background: #004488;
        }

        .btn-danger {
            background: #cc0000;
        }

        .btn-sm {
            padding: 3px 10px;
            font-size: 12px;
        }

        /* Messages */
        .msg-success {
            background: #dff0d8;
            border: 1px solid #a0c878;
            padding: 8px;
            margin-bottom: 10px;
            color: #2d6a2d;
        }

        .msg-error {
            background: #fde;
            border: 1px solid #f88;
            padding: 8px;
            margin-bottom: 10px;
            color: #a00;
        }

        .msg-info {
            background: #d9edf7;
            border: 1px solid #7bb8d4;
            padding: 8px;
            margin-bottom: 10px;
            color: #1a5276;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #003366;
            color: white;
            padding: 6px 8px;
            text-align: left;
            font-size: 12px;
        }

        table td {
            padding: 6px 8px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        table tr:hover td {
            background: #f9f9f9;
        }

        /* Comments */
        .comment {
            border-bottom: 1px solid #eee;
            padding: 8px 0;
        }

        .comment-author {
            font-weight: bold;
            font-size: 12px;
            color: #003366;
        }

        .comment-date {
            font-size: 11px;
            color: #888;
        }

        .comment-body {
            margin-top: 4px;
            font-size: 13px;
        }

        /* Search bar */
        #search-bar {
            display: flex;
            gap: 5px;
        }

        #search-bar input {
            flex: 1;
            padding: 5px 8px;
            border: 1px solid #ccc;
        }

        #search-bar button {
            padding: 5px 12px;
            background: #cc6600;
            color: white;
            border: none;
            cursor: pointer;
        }

        /* Pagination */
        .pagination {
            margin: 10px 0;
        }

        .pagination a {
            display: inline-block;
            padding: 4px 9px;
            border: 1px solid #ccc;
            margin-right: 3px;
        }

        .pagination a.active {
            background: #003366;
            color: white;
        }

        /* Footer */
        #footer {
            text-align: center;
            padding: 20px;
            color: #888;
            font-size: 12px;
            border-top: 1px solid #ccc;
            margin-top: 20px;
        }

        /* Vuln label (visible to tester) */
        .vuln-badge {
            float: right;
            background: #c00;
            color: white;
            font-size: 10px;
            padding: 1px 5px;
            border-radius: 2px;
        }
    </style>
</head>

<body>

    <div id="header">
        <h1><a href="/index.php"><?= SITE_NAME ?></a></h1>
        <span style="font-size:12px; opacity:0.7; margin-left:10px;">Trang tin tức cộng đồng</span>
    </div>

    <div id="nav">
        <a href="/index.php">Trang chủ</a>
        <a href="/category.php?id=1">Công nghệ</a>
        <a href="/category.php?id=2">Thể thao</a>
        <a href="/category.php?id=3">Kinh tế</a>
        <a href="/category.php?id=4">Giải trí</a>
        <a href="/search.php">Tìm kiếm</a>
        <a href="/spa/" style="color:#ffaa00;">SPA Lab</a>
        <a href="/static/about.html">Giới thiệu</a>
        <a href="/static/contact.html">Liên hệ</a>
        <?php if (is_admin()): ?>
            <a href="/admin/dashboard.php" style="color: #ffaa00;">⚙ Admin</a>
        <?php endif; ?>
        <span class="user-info">
            <?php if (is_logged_in()): ?>
                Xin chào, <a href="/user/profile.php" style="color:#aad0ff"><?= htmlspecialchars($current_user['username']) ?></a>
                | <a href="/user/logout.php" style="color:#aad0ff">Đăng xuất</a>
            <?php else: ?>
                <a href="/user/login.php" style="color:#aad0ff">Đăng nhập</a>
                | <a href="/user/register.php" style="color:#aad0ff">Đăng ký</a>
            <?php endif; ?>
        </span>
    </div>

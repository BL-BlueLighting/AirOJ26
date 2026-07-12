<?php
$title = '注册';
require_once 'inc/config.php';
require_once 'inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (isset($_SESSION['airoj_user'])) { header('Location: /index.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = trim($_POST['user_id'] ?? '');
    $pw  = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $nick = trim($_POST['nick'] ?? '');
    if ($uid && $pw && $email) {
        if (Database::fetchOne("SELECT user_id FROM users WHERE user_id=?", [$uid]))
            $err = '用户名已存在';
        else {
            Database::insert("INSERT INTO users (user_id,password,email,nick) VALUES (?,?,?,?)",
                [$uid, password_hash($pw, PASSWORD_DEFAULT), $email, $nick ?: $uid]);
            $count = Database::fetchOne("SELECT COUNT(*) AS c FROM users")['c'] ?? 0;
            if ($count <= 1) Database::insert("INSERT OR IGNORE INTO privilege (user_id, right_str) VALUES (?, 'administrator')", [$uid]);
            $_SESSION['airoj_user'] = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
            header('Location: /index.php'); exit;
        }
    } else $err = '请填写所有必填字段';
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>注册 — AirOJ</title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body style="display:flex;justify-content:center;align-items:center;min-height:100vh;background:var(--bg)">
<div style="width:100%;max-width:400px;padding:20px">
<div style="text-align:center;margin-bottom:24px"><a href="/" style="font-size:28px;font-weight:800;color:var(--text-primary);text-decoration:none">✈ <span style="color:var(--accent)">Air</span>OJ</a></div>
<div class="card" style="padding:32px">
    <h1 style="text-align:center;font-size:20px;margin-bottom:24px">注册</h1>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>用户名</label><input type="text" name="user_id" class="form-input" required></div>
        <div class="form-group"><label>密码</label><input type="password" name="password" class="form-input" required></div>
        <div class="form-group"><label>邮箱</label><input type="email" name="email" class="form-input" required></div>
        <div class="form-group"><label>昵称</label><input type="text" name="nick" class="form-input" placeholder="可选"></div>
        <button type="submit" class="btn btn-primary btn-block">注册</button>
    </form>
    <div style="text-align:center;margin-top:16px;font-size:13px"><a href="/login.php">已有账号？登录</a></div>
</div>
</div>
</body>
</html>

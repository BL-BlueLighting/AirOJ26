<?php
if (session_status() === PHP_SESSION_NONE) @session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$page = basename($_SERVER['SCRIPT_NAME'], '.php');

// 未安装时跳转安装向导
if ($page !== 'install') {
    $lockFile = __DIR__ . '/../data/installed.lock';
    if (!file_exists($lockFile)) {
        header('Location: install.php');
        exit;
    }
}

$user = $_SESSION['airoj_user'] ?? null;
$site_name = 'AirOJ';

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Check admin status
$is_admin = false;
$site_logo = '';
$site_favicon = '';
$background_style = '';
try {
    if ($user) {
        $priv = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$user['user_id']]);
        $is_admin = (bool)$priv;
    }
    // Logo & Favicon & 站点背景 from system_config
    $site_logo = Database::getConfig('site_logo');
    $site_favicon = Database::getConfig('site_favicon');
    // 背景：用户自定义优先，没有则回落到管理员配置的站点背景
    $background_style = background_css(resolve_background($user));
} catch (Exception $e) {}

if ($background_style !== '') {
    $body_class = trim(($body_class ?? '') . ' has-bg');
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ($title ?? '首页') . ' — ' . $site_name ?></title>
<link rel="stylesheet" href="/css/style.css?v=5">
<?php if ($site_favicon): ?><link rel="icon" href="/<?=$site_favicon?>"><?php else: ?><link rel="icon" href="data:,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='28' font-size='28'>✈</text></svg>"><?php endif; ?>
<?php if ($background_style): ?><style>body{<?= $background_style ?>}</style><?php endif; ?></head>
<body<?= !empty($body_class) ? ' class="' . htmlspecialchars($body_class) . '"' : '' ?>>

<header class="site-header">
<div class="header-inner">
<a href="/index.php" class="site-logo"><?php if ($site_logo): ?><img src="/<?=$site_logo?>" style="max-height:28px" alt="logo"><?php else: ?>✈ <span>Air</span>OJ<?php endif; ?></a>
<nav class="site-nav">
<a href="/index.php" class="nav-link <?= $page === 'index' ? 'active' : '' ?>">首页</a>
<a href="/problems.php" class="nav-link <?= $page === 'problems' ? 'active' : '' ?>">题目</a>
<a href="/status.php" class="nav-link <?= $page === 'status' ? 'active' : '' ?>">状态</a>
<a href="/ranklist.php" class="nav-link <?= $page === 'ranklist' ? 'active' : '' ?>">排名</a>
<a href="/contest.php" class="nav-link <?= $page === 'contest' ? 'active' : '' ?>">比赛</a>
<a href="/bbs.php" class="nav-link <?= $page === 'bbs' ? 'active' : '' ?>">讨论</a>
<a href="/team.php" class="nav-link <?= $page === 'team' || $page === 'team_manage' ? 'active' : '' ?>">团队</a>
</nav>
<div class="header-actions">
<?php if ($is_admin): ?><a href="/admin/index.php" class="btn btn-sm" style="background:var(--accent-gold);color:#000">管理</a><?php endif; ?>
<?php if ($user): ?>
<a href="/settings.php" class="btn btn-sm" style="background:var(--bg-hover);color:var(--text-secondary)" title="设置">⚙️</a>
<a href="/profile.php" class="btn btn-outline btn-sm"><?= htmlspecialchars($user['nick'] ?: $user['user_id']) ?></a>
<a href="/logout.php" class="btn btn-sm" style="background:var(--bg-hover);color:var(--text-secondary)">退出</a>
<?php else: ?>
<a href="/login.php" class="btn btn-outline btn-sm">登录</a>
<a href="/register.php" class="btn btn-primary btn-sm">注册</a>
<?php endif; ?>
</div>
</div>
</header>

<main class="container">

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

// 切换主题：?theme=xxx → 写入 cookie / 数据库，然后重定向去掉该参数
if (isset($_GET['theme']) && valid_theme($_GET['theme'])) {
    set_theme($_GET['theme'], $user);
    $q = $_GET; unset($q['theme']);
    $qs  = http_build_query($q);
    $url = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/index.php';
    header('Location: ' . $url . ($qs ? '?' . $qs : ''));
    exit;
}

$theme = current_theme($user);

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
    // 背景：用户自定义优先，没有则回落到管理员配置的站点背景；遮罩颜色随主题变化
    $background_style = background_css(resolve_background($user), $theme);
} catch (Exception $e) {}

if ($background_style !== '') {
    $body_class = trim(($body_class ?? '') . ' has-bg');
}
$body_class = trim(($body_class ?? '') . ' theme-' . $theme);
if ($theme === 'mdui3') {
    $body_class .= ' mdui-theme-dark';   // 使用 MDUI 官方的暗色设计令牌
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ($title ?? '首页') . ' — ' . $site_name ?></title>
<link rel="stylesheet" href="/css/fontawesome/css/all.min.css?v=6">
<?php if ($theme === 'mdui3'): ?><link rel="stylesheet" href="/css/mdui/mdui.css?v=1"><?php endif; ?>
<link rel="stylesheet" href="/css/style.css?v=13">
<link rel="stylesheet" href="/css/themes.css?v=2">
<?php if ($site_favicon): ?><link rel="icon" href="/<?=$site_favicon?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%234da6ff'/><text x='16' y='24' font-size='21' font-family='sans-serif' font-weight='bold' text-anchor='middle' fill='white'>A</text></svg>"><?php endif; ?>
<?php if ($background_style): ?><style>body{<?= $background_style ?>}</style><?php endif; ?></head>
<body<?= !empty($body_class) ? ' class="' . htmlspecialchars($body_class) . '"' : '' ?>>

<header class="site-header">
<div class="header-inner">
<a href="/index.php" class="site-logo"><?php if ($site_logo): ?><img src="/<?=$site_logo?>" style="max-height:28px" alt="logo"><?php else: ?><i class="fa-solid fa-plane"></i> <span>Air</span>OJ<?php endif; ?></a>
<nav class="site-nav">
<a href="/index.php" class="nav-link <?= $page === 'index' ? 'active' : '' ?>">首页</a>
<a href="/problems.php" class="nav-link <?= $page === 'problems' ? 'active' : '' ?>">题目</a>
<a href="/status.php" class="nav-link <?= $page === 'status' ? 'active' : '' ?>">状态</a>
<a href="/ranklist.php" class="nav-link <?= $page === 'ranklist' ? 'active' : '' ?>">排名</a>
<a href="/contest.php" class="nav-link <?= $page === 'contest' ? 'active' : '' ?>">比赛</a>
<a href="/bbs.php" class="nav-link <?= $page === 'bbs' ? 'active' : '' ?>">讨论</a>
<a href="/team.php" class="nav-link <?= $page === 'team' || $page === 'team_manage' ? 'active' : '' ?>">团队</a>
<a href="/help.php" class="nav-link <?= $page === 'help' ? 'active' : '' ?>">帮助</a>
</nav>
<div class="header-actions">
<select class="theme-select" title="切换主题"
        onchange="var u=new window.URL(location.href);u.searchParams.set('theme',this.value);location.href=u.toString()">
    <?php foreach (AIR_OJ_THEMES as $tk => $tl): ?>
    <option value="<?=$tk?>" <?=$theme === $tk ? 'selected' : ''?>><?=$tl?></option>
    <?php endforeach; ?>
</select>
<?php if ($is_admin): ?><a href="/admin/index.php" class="btn btn-sm" style="background:var(--accent-gold);color:#000">管理</a><?php endif; ?>
<?php if ($user): ?>
<a href="/settings.php" class="btn btn-sm" style="background:var(--bg-hover);color:var(--text-secondary)" title="设置"><i class="fa-solid fa-gear"></i></a>
<a href="/profile.php" class="btn btn-outline btn-sm"><?= user_role_html($user['user_id'], $user['nick'] ?: $user['user_id']) ?></a>
<a href="/logout.php" class="btn btn-sm" style="background:var(--bg-hover);color:var(--text-secondary)">退出</a>
<?php else: ?>
<a href="/login.php" class="btn btn-outline btn-sm">登录</a>
<a href="/register.php" class="btn btn-primary btn-sm">注册</a>
<?php endif; ?>
</div>
</div>
</header>

<main class="container">

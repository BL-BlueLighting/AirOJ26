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

// 每次请求从数据库刷新登录用户资料，避免 session 中保留旧的头像 / 背景 / 签名等
if ($user) {
    try {
        $freshUser = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$user['user_id']]);
        if ($freshUser) {
            unset($freshUser['password'], $freshUser['hydro_salt'], $freshUser['hydro_hash']);
            $user = array_merge($user, $freshUser);
            $_SESSION['airoj_user'] = $user;
        }
    } catch (Exception $e) {}
}

// 从 HydroOJ 迁移的账号必须先修改密码，才能访问其他页面
// 老 session 里可能没有该字段，首次访问时从数据库补一次
if ($user && !array_key_exists('must_change_password', $user)) {
    try {
        $mcp = Database::fetchOne("SELECT must_change_password FROM users WHERE user_id=?", [$user['user_id']]);
        $user['must_change_password'] = (int)($mcp['must_change_password'] ?? 0);
        $_SESSION['airoj_user']['must_change_password'] = $user['must_change_password'];
    } catch (Exception $e) {}
}
$current_script = $_SERVER['SCRIPT_NAME'] ?? '';
if ($user && !empty($user['must_change_password'])
    && !in_array($current_script, ['/settings.php', '/logout.php', '/login.php', '/install.php'], true)) {
    header('Location: /settings.php?tab=password&force=1');
    exit;
}

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
    if ($user) {
        $unread_messages = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS c FROM messages WHERE to_user=? AND is_read=0 AND to_deleted=0",
            [$user['user_id']])['c'] ?? 0);
        // 夜猫子等时间类成就：在线即判定
        check_time_achievements($user['user_id']);
    }
    // 背景：页面级覆盖（如章节背景）> 用户自定义 > 管理员配置的站点背景；遮罩颜色随主题变化
    $page_bg = isset($page_background_override) && trim((string)$page_background_override) !== ''
        ? $page_background_override
        : resolve_background($user);
    $background_style = background_css($page_bg, $theme);
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
<link rel="stylesheet" href="/css/style.css?v=22">
<link rel="stylesheet" href="/css/themes.css?v=2">
<link rel="stylesheet" href="/vendor/katex/css/katex.min.css">
<script defer src="/vendor/katex/js/katex.min.js"></script>
<script defer src="/vendor/katex/js/auto-render.min.js"></script>
<script defer src="/js/math.js?v=1"></script>
<?php if ($site_favicon): ?><link rel="icon" href="/<?=$site_favicon?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%234da6ff'/><text x='16' y='24' font-size='21' font-family='sans-serif' font-weight='bold' text-anchor='middle' fill='white'>A</text></svg>"><?php endif; ?>
<?php if ($background_style): ?><style>body{<?= $background_style ?>}</style><?php endif; ?></head>
<body<?= !empty($body_class) ? ' class="' . htmlspecialchars($body_class) . '"' : '' ?>>

<header class="site-header">
<div class="header-inner">
<a href="/index.php" class="site-logo"><?php if ($site_logo): ?><img src="/<?=$site_logo?>" style="max-height:28px" alt="logo"><?php else: ?><i class="fa-solid fa-plane"></i> <span>Air</span>OJ<?php endif; ?></a>
<nav class="site-nav">
<a href="/index.php" class="nav-link <?= $page === 'index' ? 'active' : '' ?>">首页</a>
<a href="/problems.php" class="nav-link <?= $page === 'problems' ? 'active' : '' ?>">题目</a>
<a href="/chapters.php" class="nav-link <?= $page === 'chapters' || $page === 'chapter' ? 'active' : '' ?>">章节</a>
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
<a href="/messages.php" class="btn btn-sm" style="background:var(--bg-hover);color:var(--text-secondary);position:relative" title="站内信">
    <i class="fa-solid fa-envelope"></i><?php if (($unread_messages ?? 0) > 0): ?><span class="msg-badge"><?= ($unread_messages > 99 ? '99+' : (int)$unread_messages) ?></span><?php endif; ?>
</a>
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

<?php
// 背景与 body class 在页面逻辑之后计算，允许页面设置 $page_background_override / $body_class
$page_bg = isset($page_background_override) && trim((string)$page_background_override) !== ''
    ? $page_background_override
    : resolve_background($user ?? null);
$background_style = background_css($page_bg, $theme ?? 'original');
if ($background_style !== '') {
    $body_class = trim(($body_class ?? '') . ' has-bg');
}
$theme_key = $theme ?? 'original';
$is_mdui_theme = in_array($theme_key, ['mdui3', 'mdui_fake'], true);
$body_class = trim(($body_class ?? '') . ' theme-' . $theme_key);
if ($is_mdui_theme) {
    $body_class .= ' mdui-theme-dark';
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ($title ?? '首页') . ' — ' . $site_name ?></title>
<link rel="stylesheet" href="/css/fontawesome/css/all.min.css?v=6">
<?php if ($is_mdui_theme): ?><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'mdui/mdui.css') ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= theme_asset('stylesheets', 'style.css') ?>">
<?php if (($theme ?? 'original') !== 'original'): ?><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'theme.css') ?>"><?php endif; ?>
<link rel="stylesheet" href="/vendor/katex/css/katex.min.css">
<script defer src="/vendor/katex/js/katex.min.js"></script>
<script defer src="/vendor/katex/js/auto-render.min.js"></script>
<script defer src="<?= theme_asset('scripts', 'math.js') ?>"></script>
<?php if ($site_favicon): ?><link rel="icon" href="/<?=$site_favicon?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%234da6ff'/><text x='16' y='24' font-size='21' font-family='sans-serif' font-weight='bold' text-anchor='middle' fill='white'>A</text></svg>"><?php endif; ?>
<?php if ($background_style): ?><style>body{<?= $background_style ?>}</style><?php endif; ?></head>
<body<?= !empty($body_class) ? ' class="' . htmlspecialchars($body_class) . '"' : '' ?>>

<header class="site-header">
<div class="header-inner">
<a href="/index.php" class="site-logo"><?php if ($site_logo): ?><img src="/<?=$site_logo?>" style="max-height:28px" alt="logo"><?php else: ?><i class="fa-solid fa-plane"></i> <span>Air</span>OJ<?php endif; ?></a>
<nav class="site-nav">
<a href="/index.php" class="nav-link <?= $nav_page === 'index' ? 'active' : '' ?>">首页</a>
<a href="/problems.php" class="nav-link <?= $nav_page === 'problems' ? 'active' : '' ?>">题目</a>
<a href="/chapters.php" class="nav-link <?= $nav_page === 'chapters' || $nav_page === 'chapter' ? 'active' : '' ?>">章节</a>
<a href="/status.php" class="nav-link <?= $nav_page === 'status' ? 'active' : '' ?>">状态</a>
<a href="/ranklist.php" class="nav-link <?= $nav_page === 'ranklist' ? 'active' : '' ?>">排名</a>
<a href="/contest.php" class="nav-link <?= $nav_page === 'contest' ? 'active' : '' ?>">比赛</a>
<a href="/bbs.php" class="nav-link <?= $nav_page === 'bbs' ? 'active' : '' ?>">讨论</a>
<a href="/team.php" class="nav-link <?= $nav_page === 'team' || $nav_page === 'team_manage' ? 'active' : '' ?>">团队</a>
<a href="/help.php" class="nav-link <?= $nav_page === 'help' ? 'active' : '' ?>">帮助</a>
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

<?php
// 背景与 body class 在页面逻辑之后计算，允许页面设置 $page_background_override / $body_class
$page_bg = isset($page_background_override) && trim((string)$page_background_override) !== ''
    ? $page_background_override
    : resolve_background($user ?? null);
$background_style = background_css($page_bg, 'bshark');
if ($background_style !== '') {
    $body_class = trim(($body_class ?? '') . ' has-bg');
}
$body_class = trim(($body_class ?? '') . ' theme-bshark');
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ($title ?? '首页') . ' — ' . $site_name ?></title>
<link rel="stylesheet" href="/css/fontawesome/css/all.min.css?v=6">
<link rel="stylesheet" href="<?= theme_asset('stylesheets', 'semantic.min.css') ?>">
<link rel="stylesheet" href="<?= theme_asset('stylesheets', 'style.css') ?>">
<link rel="stylesheet" href="<?= theme_asset('stylesheets', 'iconfont.css') ?>">
<link rel="stylesheet" href="<?= theme_asset('stylesheets', 'theme.css') ?>">
<link rel="stylesheet" href="/vendor/katex/css/katex.min.css">
<script defer src="/vendor/katex/js/katex.min.js"></script>
<script defer src="/vendor/katex/js/auto-render.min.js"></script>
<script defer src="<?= theme_asset('scripts', 'math.js') ?>"></script>
<?php if ($site_favicon): ?><link rel="icon" href="/<?=$site_favicon?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%234da6ff'/><text x='16' y='24' font-size='21' font-family='sans-serif' font-weight='bold' text-anchor='middle' fill='white'>A</text></svg>"><?php endif; ?>
<?php if ($background_style): ?><style>body{<?= $background_style ?>}</style><?php endif; ?></head>
<body class="<?= htmlspecialchars($body_class) ?>">

<div class="ui top fixed borderless menu bsharkMenu topmenu" id="navbar">
    <div class="ui container">
        <a class="item" id="logolink" href="/index.php">
            <?php if ($site_logo): ?><img src="/<?=$site_logo?>" style="max-height:26px" alt="logo"><?php else: ?><b><i class="fa-solid fa-plane"></i> AirOJ</b><?php endif; ?>
        </a>
        <a class="item <?= $nav_page === 'index' ? 'active' : '' ?>" href="/index.php">首页</a>
        <a class="item <?= $nav_page === 'problems' || $nav_page === 'problem' ? 'active' : '' ?>" href="/problems.php">题目</a>
        <a class="item <?= $nav_page === 'chapters' || $nav_page === 'chapter' ? 'active' : '' ?>" href="/chapters.php">章节</a>
        <a class="item <?= $nav_page === 'status' ? 'active' : '' ?>" href="/status.php">状态</a>
        <a class="item <?= $nav_page === 'ranklist' ? 'active' : '' ?>" href="/ranklist.php">排名</a>
        <a class="item <?= $nav_page === 'contest' || $nav_page === 'contest_detail' || $nav_page === 'contest_rank' ? 'active' : '' ?>" href="/contest.php">比赛</a>
        <a class="item <?= $nav_page === 'bbs' || $nav_page === 'topic' ? 'active' : '' ?>" href="/bbs.php">讨论</a>
        <a class="item <?= $nav_page === 'team' || $nav_page === 'team_manage' ? 'active' : '' ?>" href="/team.php">团队</a>
        <a class="item <?= $nav_page === 'help' ? 'active' : '' ?>" href="/help.php">帮助</a>
        <div class="right menu">
            <div class="item">
                <select class="theme-select" title="切换主题"
                        onchange="var u=new window.URL(location.href);u.searchParams.set('theme',this.value);location.href=u.toString()">
                    <?php foreach (AIR_OJ_THEMES as $tk => $tl): ?>
                    <option value="<?=$tk?>" <?=$theme === $tk ? 'selected' : ''?>><?=$tl?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($user): ?>
            <a class="item" href="/messages.php" title="站内信">
                <i class="fa-solid fa-envelope"></i>
                <?php if (($unread_messages ?? 0) > 0): ?><span class="ui red circular label" style="margin-left:4px"><?= ($unread_messages > 99 ? '99+' : (int)$unread_messages) ?></span><?php endif; ?>
            </a>
            <a class="item" href="/profile.php"><?= user_role_html($user['user_id'], $user['nick'] ?: $user['user_id']) ?></a>
            <a class="item" href="/settings.php" title="设置"><i class="fa-solid fa-gear"></i></a>
            <?php if ($is_admin): ?><a class="item" href="/admin/index.php">管理</a><?php endif; ?>
            <a class="item" href="/logout.php">退出</a>
            <?php else: ?>
            <a class="item" href="/login.php">登录</a>
            <a class="item" href="/register.php">注册</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="ui container bsharkMain">
    <div class="ui stackable grid">
        <div class="sixteen wide column">

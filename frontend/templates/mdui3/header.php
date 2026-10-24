<?php
// 背景与 body class 在页面逻辑之后计算，允许页面设置 $page_background_override / $body_class
$page_bg = isset($page_background_override) && trim((string)$page_background_override) !== ''
    ? $page_background_override
    : resolve_background($user ?? null);
$background_style = background_css($page_bg, 'mdui3');
if ($background_style !== '') {
    $body_class = trim(($body_class ?? '') . ' has-bg');
}
$body_class = trim(($body_class ?? '') . ' theme-mdui3 mdui-theme-dark');
?><!DOCTYPE html>
<html lang="zh-CN" class="mdui-theme-dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ($title ?? '首页') . ' — ' . $site_name ?></title>
<link rel="stylesheet" href="/css/fontawesome/css/all.min.css?v=6">
<link rel="stylesheet" href="<?= theme_asset('stylesheets', 'mdui/mdui.css') ?>">
<?php /* 旧组件样式放进低优先级层，避免它的全局 reset 覆盖 MDUI 组件宿主样式 */ ?>
<style>@import url("<?= theme_asset('stylesheets', 'style.css') ?>") layer(legacy);</style>
<link rel="stylesheet" href="<?= theme_asset('stylesheets', 'theme.css') ?>">
<link rel="stylesheet" href="/vendor/katex/css/katex.min.css">
<script defer src="/vendor/katex/js/katex.min.js"></script>
<script defer src="/vendor/katex/js/auto-render.min.js"></script>
<script defer src="<?= theme_asset('scripts', 'math.js') ?>"></script>
<script src="<?= theme_asset('scripts', 'mdui.global.js') ?>"></script>
<?php if ($site_favicon): ?><link rel="icon" href="/<?=$site_favicon?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%234da6ff'/><text x='16' y='24' font-size='21' font-family='sans-serif' font-weight='bold' text-anchor='middle' fill='white'>A</text></svg>"><?php endif; ?>
<?php if ($background_style): ?><style>body{<?= $background_style ?>}</style><?php endif; ?></head>
<body class="<?= htmlspecialchars($body_class) ?>">

<mdui-layout>
    <mdui-top-app-bar>
        <mdui-button-icon icon="menu" aria-label="菜单"
            onclick="var d=document.getElementById('main-drawer');d.open=!d.open"></mdui-button-icon>
        <mdui-top-app-bar-title>
            <a class="mdui-brand" href="/index.php"
               style="display:inline-flex;align-items:center;gap:8px;height:40px;vertical-align:middle;color:inherit;text-decoration:none;border-radius:12px">
                <?php if ($site_logo): ?>
                <img src="/<?=$site_logo?>" style="height:28px;width:auto;max-width:100%;border-radius:12px" alt="logo">
                <?php else: ?>
                <span><b>Air</b>OJ</span>
                <?php endif; ?>
            </a>
        </mdui-top-app-bar-title>
        <div class="mdui-bar-spacer"></div>
        <select class="theme-select theme-select-top" title="切换主题"
                onchange="var u=new window.URL(location.href);u.searchParams.set('theme',this.value);location.href=u.toString()">
            <?php foreach (AIR_OJ_THEMES as $tk => $tl): ?>
            <option value="<?=$tk?>" <?=$theme === $tk ? 'selected' : ''?>><?=$tl?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($user): ?>
        <span class="mdui-msg-wrap">
            <mdui-button-icon icon="mail" href="/messages.php" aria-label="站内信"></mdui-button-icon>
            <?php if (($unread_messages ?? 0) > 0): ?><span class="msg-badge"><?= ($unread_messages > 99 ? '99+' : (int)$unread_messages) ?></span><?php endif; ?>
        </span>
        <mdui-dropdown>
            <mdui-button slot="trigger" variant="text" icon="account_circle"><?= htmlspecialchars($user['nick'] ?: $user['user_id']) ?></mdui-button>
            <mdui-menu>
                <mdui-menu-item icon="person" href="/profile.php">我的主页</mdui-menu-item>
                <mdui-menu-item icon="settings" href="/settings.php">账号设置</mdui-menu-item>
                <?php if ($is_admin): ?><mdui-menu-item icon="dashboard" href="/admin/index.php">后台管理</mdui-menu-item><?php endif; ?>
                <mdui-divider></mdui-divider>
                <mdui-menu-item icon="logout" href="/logout.php">退出登录</mdui-menu-item>
            </mdui-menu>
        </mdui-dropdown>
        <?php else: ?>
        <mdui-button variant="text" href="/login.php">登录</mdui-button>
        <mdui-button variant="filled" href="/register.php">注册</mdui-button>
        <?php endif; ?>
    </mdui-top-app-bar>

    <mdui-navigation-drawer id="main-drawer" open order="2" close-on-esc close-on-overlay-click>
        <mdui-list>
            <mdui-list-item icon="home" href="/index.php" <?= $nav_page === 'index' ? 'active' : '' ?>>首页</mdui-list-item>
            <mdui-list-item icon="book" href="/problems.php" <?= $nav_page === 'problems' || $nav_page === 'problem' ? 'active' : '' ?>>题目</mdui-list-item>
            <mdui-list-item icon="library_books" href="/chapters.php" <?= $nav_page === 'chapters' || $nav_page === 'chapter' ? 'active' : '' ?>>章节</mdui-list-item>
            <mdui-list-item icon="play_circle_outline" href="/status.php" <?= $nav_page === 'status' ? 'active' : '' ?>>状态</mdui-list-item>
            <mdui-list-item icon="equalizer" href="/ranklist.php" <?= $nav_page === 'ranklist' ? 'active' : '' ?>>排名</mdui-list-item>
            <mdui-list-item icon="layers" href="/contest.php" <?= $nav_page === 'contest' || $nav_page === 'contest_detail' || $nav_page === 'contest_rank' ? 'active' : '' ?>>比赛</mdui-list-item>
            <mdui-list-item icon="forum" href="/bbs.php" <?= $nav_page === 'bbs' || $nav_page === 'topic' ? 'active' : '' ?>>讨论</mdui-list-item>
            <mdui-list-item icon="group" href="/team.php" <?= $nav_page === 'team' || $nav_page === 'team_manage' ? 'active' : '' ?>>团队</mdui-list-item>
            <mdui-list-item icon="help_outline" href="/help.php" <?= $nav_page === 'help' ? 'active' : '' ?>>帮助</mdui-list-item>
            <?php if ($user): ?>
            <mdui-divider></mdui-divider>
            <mdui-list-item icon="person" href="/profile.php" <?= $nav_page === 'profile' ? 'active' : '' ?>>我的主页</mdui-list-item>
            <mdui-list-item icon="settings" href="/settings.php" <?= $nav_page === 'settings' ? 'active' : '' ?>>账号设置</mdui-list-item>
            <?php if ($is_admin): ?><mdui-list-item icon="dashboard" href="/admin/index.php">后台管理</mdui-list-item><?php endif; ?>
            <?php endif; ?>
        </mdui-list>
        <div class="drawer-theme">
            <span>主题</span>
            <select class="theme-select theme-select-drawer" title="切换主题"
                    onchange="var u=new window.URL(location.href);u.searchParams.set('theme',this.value);location.href=u.toString()">
                <?php foreach (AIR_OJ_THEMES as $tk => $tl): ?>
                <option value="<?=$tk?>" <?=$theme === $tk ? 'selected' : ''?>><?=$tl?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </mdui-navigation-drawer>

    <mdui-layout-main>
        <div class="mdui-container">

<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>注册 — AirOJ</title>
<link rel="stylesheet" href="/css/fontawesome/css/all.min.css?v=6">
<link rel="stylesheet" href="<?= theme_asset('stylesheets', 'style.css', $auth_theme) ?>">
<?php if (in_array($auth_theme, ['mdui3', 'mdui_fake'], true)): ?><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'mdui/mdui.css', $auth_theme) ?>"><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'theme.css', $auth_theme) ?>"><?php elseif ($auth_theme === 'bshark'): ?><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'semantic.min.css', 'bshark') ?>"><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'iconfont.css', 'bshark') ?>"><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'theme.css', 'bshark') ?>"><?php endif; ?>
</head>
<body class="theme-<?= $auth_theme ?><?= in_array($auth_theme, ['mdui3', 'mdui_fake'], true) ? ' mdui-theme-dark' : '' ?>" style="display:flex;justify-content:center;align-items:center;min-height:100vh;background:var(--bg-primary)">
<div style="width:100%;max-width:400px;padding:20px">
<?php plugin_view_head(); ?>
<div class="auth-logo" style="text-align:center;margin-bottom:24px">
    <a href="/" style="font-size:28px;font-weight:800;color:var(--text-primary);text-decoration:none;display:inline-block">
    <?php if ($site_logo): ?>
    <img src="/<?=htmlspecialchars($site_logo)?>" alt="logo" style="max-height:48px;max-width:100%;display:block">
    <?php else: ?>
    <i class="fa-solid fa-plane"></i> <span style="color:var(--accent)">Air</span>OJ
    <?php endif; ?>
    </a>
</div>
<div class="card" style="padding:32px">
    <h1 style="text-align:center;font-size:20px;margin-bottom:24px">注册</h1>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if (email_verification_enabled()): ?>
    <div class="alert alert-info" style="font-size:12px">注册后需要点击邮件中的验证链接完成邮箱验证，验证通过后才能登录。</div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group"><label>用户名</label><input type="text" name="user_id" class="form-input" required></div>
        <div class="form-group"><label>密码</label><input type="password" name="password" class="form-input" required></div>
        <div class="form-group"><label>邮箱</label><input type="email" name="email" class="form-input" required></div>
        <div class="form-group"><label>昵称</label><input type="text" name="nick" class="form-input" placeholder="可选"></div>
        <?= turnstile_widget() ?>
        <button type="submit" class="btn btn-primary btn-block">注册</button>
    </form>
    <div style="text-align:center;margin-top:16px;font-size:13px"><a href="/login.php">已有账号？登录</a></div>
</div>
<?php plugin_view_tail(); ?>
</div>
</body>
</html>

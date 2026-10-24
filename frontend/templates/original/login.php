<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录 — AirOJ</title>
<link rel="stylesheet" href="/css/fontawesome/css/all.min.css?v=6">
<link rel="stylesheet" href="<?= theme_asset('stylesheets', 'style.css', $auth_theme) ?>">
<?php if (in_array($auth_theme, ['mdui3', 'mdui_fake'], true)): ?><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'mdui/mdui.css', $auth_theme) ?>"><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'theme.css', $auth_theme) ?>"><?php elseif ($auth_theme === 'bshark'): ?><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'semantic.min.css', 'bshark') ?>"><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'iconfont.css', 'bshark') ?>"><link rel="stylesheet" href="<?= theme_asset('stylesheets', 'theme.css', 'bshark') ?>"><?php endif; ?>
<style>
.auth-wrap { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: var(--bg-primary); }
.auth-box { width: 100%; max-width: 400px; padding: 20px; }
.auth-logo { text-align: center; margin-bottom: 24px; }
.auth-logo a { font-size: 28px; font-weight: 800; color: var(--text-primary); text-decoration: none; }
.auth-logo a span { color: var(--accent); }

/* 6 位验证码输入框 */
.twofa-inputs { display: flex; gap: 8px; justify-content: center; margin: 20px 0; }
.twofa-inputs input {
    width: 44px; height: 52px; text-align: center; font-size: 22px; font-weight: 700;
    border: 2px solid var(--border); border-radius: var(--radius);
    background: var(--bg-secondary); color: var(--text-primary);
    outline: none; transition: border-color .2s;
}
.twofa-inputs input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(77,166,255,.2); }
.twofa-inputs input.filled { border-color: var(--green); }
</style>
</head>
<body class="theme-<?= $auth_theme ?><?= in_array($auth_theme, ['mdui3', 'mdui_fake'], true) ? ' mdui-theme-dark' : '' ?>">
<div class="auth-wrap">
<div class="auth-box">
<?php plugin_view_head(); ?>
<div class="auth-logo">
    <a href="/">
    <?php if ($site_logo): ?>
    <img src="/<?=htmlspecialchars($site_logo)?>" alt="logo" style="max-height:48px;max-width:100%">
    <?php else: ?>
    <i class="fa-solid fa-plane"></i> <span>Air</span>OJ
    <?php endif; ?>
    </a>
</div>

<div class="card" style="padding:32px">
    <?php if (!empty($notice)): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($show_2fa): ?>
    <h1 style="text-align:center;font-size:20px;margin-bottom:8px"><i class="fa-solid fa-lock"></i> 二步验证</h1>
    <p style="text-align:center;color:var(--text-secondary);font-size:13px;margin-bottom:8px">
        请在下方输入验证器中的 6 位验证码
    </p>
    <p style="text-align:center;color:var(--text-muted);font-size:12px;margin-bottom:20px"><?= htmlspecialchars($temp_uid) ?></p>

    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <form method="post" id="twofa-form">
        <input type="hidden" name="twofa_step" value="1">
        <div class="twofa-inputs" id="twofa-container">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autofocus="<?= $i===0?'autofocus':'' ?>" data-index="<?= $i ?>" class="twofa-input">
            <?php endfor; ?>
        </div>
        <input type="hidden" name="twofa_code" id="twofa_code">
        <button type="submit" class="btn btn-primary btn-block" id="twofa-submit" disabled>验证</button>
    </form>

    <div style="text-align:center;margin-top:12px"><a href="/login.php" style="font-size:13px"><i class="fa-solid fa-arrow-left"></i> 返回登录</a></div>

    <script>
    const inputs = document.querySelectorAll('.twofa-input');
    const hiddenInput = document.getElementById('twofa_code');
    const submitBtn = document.getElementById('twofa-submit');

    inputs.forEach((input, idx) => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value) {
                this.classList.add('filled');
                if (idx < inputs.length - 1) inputs[idx + 1].focus();
            } else {
                this.classList.remove('filled');
            }
            updateCode();
        });
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && idx > 0) {
                inputs[idx - 1].focus();
            }
        });
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
            for (let i = 0; i < Math.min(paste.length, inputs.length); i++) {
                inputs[i].value = paste[i];
                inputs[i].classList.add('filled');
            }
            const next = Math.min(paste.length, inputs.length - 1);
            if (paste.length >= 6) {
                inputs[5].focus();
            } else {
                inputs[next].focus();
            }
            updateCode();
        });
    });

    function updateCode() {
        let code = '';
        inputs.forEach(i => code += i.value);
        hiddenInput.value = code;
        submitBtn.disabled = code.length !== 6;
    }
    </script>

    <?php else: ?>
    <h1 style="text-align:center;font-size:20px;margin-bottom:24px">登录</h1>
    <?= plugin_render('Hook:Login/Page.Choice', ['page' => 'login']) ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if (!empty($email_unverified)): ?>
    <p style="text-align:center;font-size:13px;margin:-4px 0 14px">
        <a href="/verify_email.php?resend=1">重新发送验证邮件</a>
    </p>
    <?php endif; ?>
    <form method="post">
        <div class="form-group"><label>用户名</label><input type="text" name="user_id" class="form-input" required autocomplete="username"></div>
        <div class="form-group"><label>密码</label><input type="password" name="password" class="form-input" required autocomplete="current-password"></div>
        <?= turnstile_widget() ?>
        <button type="submit" class="btn btn-primary btn-block">登录</button>
    </form>
    <div style="text-align:center;margin-top:16px;font-size:13px">
        <a href="/register.php">注册账号</a>
        <?php if (password_reset_enabled()): ?>
        <span style="margin:0 8px;color:var(--border)">·</span>
        <a href="/lostpassword.php">忘记密码</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php plugin_view_tail(); ?>
</div>
</div>
</body>
</html>

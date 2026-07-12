<?php
$title = '登录';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
require_once 'inc/TOTP.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (isset($_SESSION['airoj_user'])) { header('Location: /index.php'); exit; }

$err = '';
$show_2fa = false;
$temp_uid = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = trim($_POST['user_id'] ?? '');
    $pw  = $_POST['password'] ?? '';
    $twofa_code = $_POST['twofa_code'] ?? '';

    if (isset($_POST['twofa_step'])) {
        // 2FA 验证阶段
        $temp_uid = $_SESSION['twofa_uid'] ?? '';
        if (!$temp_uid) { $err = '请重新登录'; }
        else {
            $row = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$temp_uid]);
            if ($row && $row['twofa_enabled'] && !empty($row['twofa_secret'])) {
                $totp = new TOTP($row['twofa_secret']);
                if ($totp->verify($twofa_code)) {
                    unset($_SESSION['twofa_uid']);
                    $_SESSION['airoj_user'] = $row;
                    header('Location: /index.php'); exit;
                }
                $err = '验证码错误，请重试';
            } else { $err = '2FA 配置异常'; }
        }
    } else {
        // 第一步：用户名密码验证
        if ($uid && $pw) {
            $row = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
            if ($row && password_verify($pw, $row['password'])) {
                if ($row['twofa_enabled']) {
                    $_SESSION['twofa_uid'] = $uid;
                    $show_2fa = true;
                    $temp_uid = $uid;
                } else {
                    $_SESSION['airoj_user'] = $row;
                    header('Location: /index.php'); exit;
                }
            } else { $err = '用户名或密码错误'; }
        } else { $err = '请填写用户名和密码'; }
    }
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录 — AirOJ</title>
<link rel="stylesheet" href="/css/style.css">
<style>
.auth-wrap { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: var(--bg); }
.auth-box { width: 100%; max-width: 400px; padding: 20px; }
.auth-logo { text-align: center; margin-bottom: 24px; }
.auth-logo a { font-size: 28px; font-weight: 800; color: var(--text-primary); text-decoration: none; }
.auth-logo a span { color: var(--accent); }

/* 6 位验证码输入框 */
.twofa-inputs { display: flex; gap: 8px; justify-content: center; margin: 20px 0; }
.twofa-inputs input {
    width: 44px; height: 52px; text-align: center; font-size: 22px; font-weight: 700;
    border: 2px solid var(--border); border-radius: var(--radius);
    background: var(--bg-input); color: var(--text-primary);
    outline: none; transition: border-color .2s;
}
.twofa-inputs input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(77,166,255,.2); }
.twofa-inputs input.filled { border-color: var(--green); }
</style>
</head>
<body>
<div class="auth-wrap">
<div class="auth-box">
<div class="auth-logo"><a href="/">✈ <span>Air</span>OJ</a></div>

<div class="card" style="padding:32px">
    <?php if ($show_2fa): ?>
    <h1 style="text-align:center;font-size:20px;margin-bottom:8px">🔐 二步验证</h1>
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

    <div style="text-align:center;margin-top:12px"><a href="/login.php" style="font-size:13px">← 返回登录</a></div>

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
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>用户名</label><input type="text" name="user_id" class="form-input" required autocomplete="username"></div>
        <div class="form-group"><label>密码</label><input type="password" name="password" class="form-input" required autocomplete="current-password"></div>
        <button type="submit" class="btn btn-primary btn-block">登录</button>
    </form>
    <div style="text-align:center;margin-top:16px;font-size:13px">
        <a href="/register.php">注册账号</a>
        <span style="margin:0 8px;color:var(--border)">·</span>
        <a href="/lostpassword.php">忘记密码</a>
    </div>
    <?php endif; ?>
</div>

</div>
</div>
</body>
</html>

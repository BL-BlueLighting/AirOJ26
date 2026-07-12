<?php
$title = '设置';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
require_once 'inc/TOTP.php';
require_once 'inc/Parsedown.php';

if (session_status() === PHP_SESSION_NONE) @session_start();
if (!isset($_SESSION['airoj_user'])) { header('Location: /login.php'); exit; }

$uid = $_SESSION['airoj_user']['user_id'];
$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
$pd = new Parsedown();

$err = ''; $ok = '';
$tab = $_GET['tab'] ?? 'profile';

// 处理表单
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_bio'])) {
        $bio = $_POST['bio'] ?? '';
        Database::exec("UPDATE users SET bio=? WHERE user_id=?", [$bio, $uid]);
        $_SESSION['airoj_user']['bio'] = $bio;
        $ok = '简介已更新';
    }
    if (isset($_POST['enable_2fa'])) {
        $secret = $_POST['secret'] ?? '';
        $code = $_POST['code'] ?? '';
        if ($secret && $code) {
            $totp = new TOTP($secret);
            if ($totp->verify($code)) {
                Database::exec("UPDATE users SET twofa_secret=?, twofa_enabled=1 WHERE user_id=?", [$secret, $uid]);
                $_SESSION['airoj_user']['twofa_enabled'] = 1;
                $ok = '二步验证已启用';
            } else { $err = '验证码错误，请重试'; }
        } else { $err = '参数错误'; }
    }
    if (isset($_POST['disable_2fa'])) {
        $code = $_POST['code'] ?? '';
        if ($code) {
            $totp = new TOTP($user['twofa_secret']);
            if ($totp->verify($code)) {
                Database::exec("UPDATE users SET twofa_enabled=0, twofa_secret=NULL WHERE user_id=?", [$uid]);
                $_SESSION['airoj_user']['twofa_enabled'] = 0;
                $ok = '二步验证已关闭';
            } else { $err = '验证码错误'; }
        } else { $err = '请输入验证码'; }
    }
}

// 重新读取用户
$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);

// 生成 2FA 密钥（如果还没有且要展示设置界面）
$new_secret = TOTP::generateSecret();
$otpauth = TOTP::getOTPAuthURI($uid, $new_secret);
?>
<style>
.tab-bar { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 24px; }
.tab-bar a { padding: 10px 20px; font-size: 14px; font-weight: 600; color: var(--text-secondary); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all .2s; }
.tab-bar a:hover { color: var(--text-primary); }
.tab-bar a.active { color: var(--accent); border-bottom-color: var(--accent); }
</style>

<div class="page-header"><h1>⚙️ 账号设置</h1></div>

<div class="tab-bar">
    <a href="?tab=profile" class="<?=$tab==='profile'?'active':''?>">📝 个人简介</a>
    <a href="?tab=2fa" class="<?=$tab==='2fa'?'active':''?>">🔐 二步验证</a>
    <a href="?tab=password" class="<?=$tab==='password'?'active':''?>">🔑 修改密码</a>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<?php if ($tab === 'profile'): ?>
<div class="card" style="max-width:600px">
    <h2>📝 个人简介</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">支持 Markdown 格式，将在你的个人主页显示。</p>
    <form method="post">
        <div class="form-group">
            <textarea name="bio" class="form-input" style="min-height:150px" placeholder="介绍一下自己..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
        </div>
        <button type="submit" name="update_bio" class="btn btn-primary">保存</button>
    </form>
    <?php if (!empty($user['bio'])): ?>
    <div style="margin-top:16px;padding:16px;background:var(--bg-secondary);border-radius:var(--radius)">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">预览：</div>
        <div style="font-size:14px;color:var(--text-secondary);line-height:1.7"><?= $pd->text($user['bio']) ?></div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === '2fa'): ?>
<div class="card" style="max-width:600px">
    <h2>🔐 二步验证 (2FA)</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px">
        使用 Google Authenticator、Authy 等 TOTP 兼容应用扫码绑定，登录时需额外输入 6 位验证码。
    </p>

    <?php if ($user['twofa_enabled']): ?>
    <div class="alert alert-success" style="margin-bottom:16px">✅ 二步验证已启用</div>
    <form method="post" style="max-width:300px">
        <div class="form-group"><label>输入验证码以关闭</label>
            <input type="text" name="code" class="form-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6 位验证码" required>
        </div>
        <button type="submit" name="disable_2fa" class="btn btn-danger">关闭 2FA</button>
    </form>

    <?php else: ?>
    <div style="text-align:center;padding:20px;background:var(--bg-secondary);border-radius:var(--radius);margin-bottom:16px">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($otpauth) ?>"
             alt="2FA QR Code" style="border-radius:var(--radius);max-width:180px">
        <p style="margin-top:8px;font-size:12px;color:var(--text-muted)">使用验证器 App 扫描二维码</p>
    </div>
    <div style="margin-bottom:16px;font-size:12px;color:var(--text-muted);word-break:break-all">
        或手动输入密钥: <code style="background:var(--bg-primary);padding:2px 6px;border-radius:4px"><?= $new_secret ?></code>
    </div>
    <form method="post" style="max-width:300px">
        <input type="hidden" name="secret" value="<?= $new_secret ?>">
        <div class="form-group"><label>输入验证器中的 6 位验证码</label>
            <input type="text" name="code" class="form-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required>
        </div>
        <button type="submit" name="enable_2fa" class="btn btn-primary">启用 2FA</button>
    </form>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'password'): ?>
<div class="card" style="max-width:400px">
    <h2>🔑 修改密码</h2>
    <form method="post" action="/modify.php">
        <div class="form-group"><label>原密码</label><input type="password" name="password_old" class="form-input" required></div>
        <div class="form-group"><label>新密码</label><input type="password" name="password_new" class="form-input" required></div>
        <button type="submit" class="btn btn-primary">修改密码</button>
    </form>
</div>
<?php endif; ?>

<?php require 'inc/footer.php'; ?>

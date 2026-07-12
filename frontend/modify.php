<?php
$title = '修改资料';
require_once 'inc/config.php';
require_once 'inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (!isset($_SESSION['airoj_user'])) { header('Location: login.php'); exit; }

$err = ''; $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nick = trim($_POST['nick'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pw_old = $_POST['password_old'] ?? '';
    $pw_new = $_POST['password_new'] ?? '';
    $uid = $_SESSION['airoj_user']['user_id'];

    if ($nick) {
        Database::exec("UPDATE users SET nick=? WHERE user_id=?", [$nick, $uid]);
        $_SESSION['airoj_user']['nick'] = $nick;
        $ok = '昵称已更新';
    }
    if ($email) {
        Database::exec("UPDATE users SET email=? WHERE user_id=?", [$email, $uid]);
        $ok = '邮箱已更新';
    }
    if ($pw_old && $pw_new) {
        $row = Database::fetchOne("SELECT password FROM users WHERE user_id=?", [$uid]);
        if (password_verify($pw_old, $row['password'])) {
            Database::exec("UPDATE users SET password=? WHERE user_id=?", [password_hash($pw_new, PASSWORD_DEFAULT), $uid]);
            $ok = '密码已更新';
        } else $err = '原密码错误';
    }
    if ($ok) $_SESSION['airoj_user'] = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
}

require 'inc/header.php';
$user = $_SESSION['airoj_user'];
?>
<div class="page-header"><h1>⚙️ 修改资料</h1></div>
<div class="auth-card" style="max-width:500px">
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>用户名</label><input type="text" class="form-input" value="<?= htmlspecialchars($user['user_id']) ?>" disabled></div>
        <div class="form-group"><label>昵称</label><input type="text" name="nick" class="form-input" value="<?= htmlspecialchars($user['nick'] ?? '') ?>"></div>
        <div class="form-group"><label>邮箱</label><input type="email" name="email" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>"></div>
        <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">
        <div class="form-group"><label>原密码（修改密码时填写）</label><input type="password" name="password_old" class="form-input" autocomplete="current-password"></div>
        <div class="form-group"><label>新密码</label><input type="password" name="password_new" class="form-input" autocomplete="new-password"></div>
        <button type="submit" class="btn btn-primary btn-block">保存修改</button>
    </form>
</div>
<?php require 'inc/footer.php'; ?>

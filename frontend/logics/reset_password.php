<?php
$title = '重置密码';
$error = '';
$notice = '';
$enabled = password_reset_enabled();
$token = trim((string)($_POST['token'] ?? ($_GET['token'] ?? '')));
$target = null;

if (!$enabled) {
    $error = '找回密码功能当前未开启，请联系管理员处理。';
} elseif (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = '重置链接无效或已过期。';
} else {
    $row = Database::fetchOne(
        "SELECT * FROM users WHERE password_reset_token=? LIMIT 1",
        [mail_token_hash($token)]
    );
    if (!$row || (int)($row['password_reset_expires'] ?? 0) < time()) {
        $error = '重置链接无效或已过期，请重新申请找回密码。';
    } else {
        $target = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $target) {
    $pw  = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password_confirm'] ?? '');
    if (strlen($pw) < 6) {
        $error = '新密码至少需要 6 位。';
    } elseif ($pw !== $pw2) {
        $error = '两次输入的密码不一致。';
    } else {
        Database::exec(
            "UPDATE users SET password=?, hydro_salt=NULL, hydro_hash=NULL, must_change_password=0,
                password_reset_token='', password_reset_expires=0 WHERE user_id=?",
            [password_hash($pw, PASSWORD_DEFAULT), $target['user_id']]
        );
        // 重置后让当前浏览器退出登录，使用新密码重新登录
        unset($_SESSION['airoj_user'], $_SESSION['twofa_uid']);
        if (session_status() === PHP_SESSION_ACTIVE) @session_regenerate_id(true);
        header('Location: /login.php?reset=1');
        exit;
    }
}

$valid = (bool)$target;

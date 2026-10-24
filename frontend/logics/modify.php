<?php
$title = '修改资料';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/TOTP.php';
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
        $row = Database::fetchOne("SELECT password, twofa_enabled, twofa_secret, hydro_salt, hydro_hash FROM users WHERE user_id=?", [$uid]);
        if (!verify_login_password($row ?: [], $pw_old)) {
            $err = '原密码错误';
        } elseif (strlen($pw_new) < 6) {
            $err = '新密码至少 6 位';
        } elseif (!empty($row['twofa_enabled'])) {
            $code = trim((string)($_POST['twofa_code'] ?? ''));
            $totp = new TOTP((string)$row['twofa_secret']);
            if ($code === '' || !$totp->verify($code)) $err = '二步验证码错误';
        } else {
            Database::exec(
                "UPDATE users SET password=?, hydro_salt=NULL, hydro_hash=NULL, must_change_password=0 WHERE user_id=?",
                [password_hash($pw_new, PASSWORD_DEFAULT), $uid]
            );
            $ok = '密码已更新';
        }
    }
    if ($ok) $_SESSION['airoj_user'] = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
}

$user = $_SESSION['airoj_user'];

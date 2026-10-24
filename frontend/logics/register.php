<?php
$title = '注册';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (isset($_SESSION['airoj_user'])) { header('Location: /index.php'); exit; }

$auth_theme = in_array($_COOKIE['airoj_theme'] ?? '', ['original', 'mdui3', 'mdui_fake', 'bshark'], true)
    ? $_COOKIE['airoj_theme'] : 'original';

$err = '';
$site_logo = '';
try { $site_logo = Database::getConfig('site_logo'); } catch (Exception $e) {}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = trim($_POST['user_id'] ?? '');
    $pw  = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $nick = trim($_POST['nick'] ?? '');
    if ($uid && $pw && $email) {
        if (!turnstile_verify($_POST['cf-turnstile-response'] ?? '')) {
            $err = '人机验证失败，请重试';
        } elseif (Database::fetchOne("SELECT user_id FROM users WHERE user_id=?", [$uid]))
            $err = '用户名已存在';
        else {
            Database::insert("INSERT INTO users (user_id,password,email,nick) VALUES (?,?,?,?)",
                [$uid, password_hash($pw, PASSWORD_DEFAULT), $email, $nick ?: $uid]);
            $count = Database::fetchOne("SELECT COUNT(*) AS c FROM users")['c'] ?? 0;
            if ($count <= 1) Database::insert("INSERT OR IGNORE INTO privilege (user_id, right_str) VALUES (?, 'administrator')", [$uid]);
            $_SESSION['airoj_user'] = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
            header('Location: /index.php'); exit;
        }
    } else $err = '请填写所有必填字段';
}

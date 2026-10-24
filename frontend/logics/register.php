<?php
$title = '注册';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/mailer.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (isset($_SESSION['airoj_user'])) { header('Location: /index.php'); exit; }

$auth_theme = in_array($_COOKIE['airoj_theme'] ?? '', ['original', 'mdui3', 'mdui_fake', 'bshark'], true)
    ? $_COOKIE['airoj_theme'] : 'original';

$err = '';
$site_logo = '';
try { $site_logo = Database::getConfig('site_logo'); } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = trim((string)($_POST['user_id'] ?? ''));
    $pw  = (string)($_POST['password'] ?? '');
    $email = trim((string)($_POST['email'] ?? ''));
    $nick = trim((string)($_POST['nick'] ?? ''));

    if ($uid === '' || $pw === '' || $email === '') {
        $err = '请填写所有必填字段';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = '请输入有效的邮箱地址';
    } elseif (mb_strlen($email) > 200) {
        $err = '邮箱地址过长';
    } elseif (!turnstile_verify($_POST['cf-turnstile-response'] ?? '')) {
        $err = '人机验证失败，请重试';
    } elseif (Database::fetchOne("SELECT user_id FROM users WHERE user_id=?", [$uid])) {
        $err = '用户名已存在';
    } elseif (Database::fetchOne("SELECT user_id FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1", [$email])) {
        $err = '该邮箱已被注册';
    } else {
        $verify_required = email_verification_enabled();
        try {
            if ($verify_required) {
                $rawToken = mail_token();
                Database::insert(
                    "INSERT INTO users
                        (user_id,password,email,nick,email_verified,email_verify_token,email_verify_expires,email_verify_sent_at)
                     VALUES (?,?,?,?,0,?,?,?)",
                    [
                        $uid,
                        password_hash($pw, PASSWORD_DEFAULT),
                        $email,
                        $nick ?: $uid,
                        mail_token_hash($rawToken),
                        time() + 86400,
                        time(),
                    ]
                );
            } else {
                Database::insert(
                    "INSERT INTO users (user_id,password,email,nick,email_verified) VALUES (?,?,?,?,1)",
                    [$uid, password_hash($pw, PASSWORD_DEFAULT), $email, $nick ?: $uid]
                );
            }

            // 站点第一个注册用户自动成为管理员
            $count = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM users")['c'] ?? 0);
            if ($count <= 1) {
                $hasAdmin = Database::fetchOne(
                    "SELECT user_id FROM privilege WHERE user_id=? AND right_str='administrator'",
                    [$uid]
                );
                if (!$hasAdmin) {
                    Database::insert("INSERT INTO privilege (user_id, right_str) VALUES (?, 'administrator')", [$uid]);
                }
            }

            $pluginRegCtx = [
                'user_id' => $uid,
                'email' => $email,
                'nick' => $nick ?: $uid,
                'verify_required' => $verify_required,
            ];
            plugin_do('Hook:Register/User.Submit', $pluginRegCtx);

            if ($verify_required) {
                $row = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
                [$sent, $mailErr] = $row ? send_verification_email($row, $rawToken) : [false, '用户记录读取失败'];
                if (!$sent) {
                    error_log('[AirOJ mail] verification email failed for ' . $uid . ': ' . $mailErr);
                    header('Location: /login.php?registered=1&verify=1&mailfail=1');
                } else {
                    header('Location: /login.php?registered=1&verify=1');
                }
                exit;
            }

            // 未开启邮箱验证：保持原有的注册后自动登录行为
            $_SESSION['airoj_user'] = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
            header('Location: /index.php');
            exit;
        } catch (Throwable $e) {
            $err = '注册失败，请稍后重试';
            error_log('[AirOJ register] ' . $e->getMessage());
        }
    }
}

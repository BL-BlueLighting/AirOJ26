<?php
$title = '登录';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/TOTP.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (isset($_SESSION['airoj_user'])) { header('Location: /index.php'); exit; }

// 登录页使用 cookie 中的主题（未登录时没有账号设置）
$auth_theme = in_array($_COOKIE['airoj_theme'] ?? '', ['original', 'mdui3', 'mdui_fake', 'bshark'], true)
    ? $_COOKIE['airoj_theme'] : 'original';

$err = '';
$show_2fa = false;
$temp_uid = '';

// 站点 Logo（后台配置，未设置时回落到文字 Logo）
$site_logo = '';
try { $site_logo = Database::getConfig('site_logo'); } catch (Exception $e) {}

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
                    $sessionRow = $row;
                    unset($sessionRow['hydro_salt'], $sessionRow['hydro_hash']);
                    $_SESSION['airoj_user'] = $sessionRow;
                    header('Location: ' . (!empty($row['must_change_password'])
                        ? '/settings.php?tab=password&force=1' : '/index.php'));
                    exit;
                }
                $err = '验证码错误，请重试';
            } else { $err = '2FA 配置异常'; }
        }
    } else {
        // 第一步：用户名密码验证（开启 Turnstile 时先做人机校验）
        if ($uid && $pw) {
            if (!turnstile_verify($_POST['cf-turnstile-response'] ?? '')) {
                $err = '人机验证失败，请重试';
            } else {
                $row = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
                if ($row && verify_login_password($row, $pw)) {
                    // Hydro 迁移账号：无论用哪个密码登录，都要先改密码
                    if (verify_hydro_password($row, $pw) || !empty($row['must_change_password'])) {
                        if (empty($row['must_change_password'])) {
                            Database::exec("UPDATE users SET must_change_password=1 WHERE user_id=?", [$uid]);
                        }
                        $row['must_change_password'] = 1;
                    }
                    if ($row['twofa_enabled']) {
                        $_SESSION['twofa_uid'] = $uid;
                        $show_2fa = true;
                        $temp_uid = $uid;
                    } else {
                        // 不要把 Hydro 迁移哈希带进 session
                        $sessionRow = $row;
                        unset($sessionRow['hydro_salt'], $sessionRow['hydro_hash']);
                        $_SESSION['airoj_user'] = $sessionRow;
                        header('Location: ' . (!empty($row['must_change_password'])
                            ? '/settings.php?tab=password&force=1' : '/index.php'));
                        exit;
                    }
                } else { $err = '用户名或密码错误'; }
            }
        } else { $err = '请填写用户名和密码'; }
    }
}

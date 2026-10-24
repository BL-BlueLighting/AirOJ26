<?php
$title = '设置';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/mailer.php';
require_once 'include/TOTP.php';
require_once 'include/Parsedown.php';

if (session_status() === PHP_SESSION_NONE) @session_start();
if (!isset($_SESSION['airoj_user'])) { header('Location: /login.php'); exit; }

$uid = $_SESSION['airoj_user']['user_id'];
$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
$pd = new Parsedown();

$err = ''; $ok = '';
$tab = $_GET['tab'] ?? 'profile';
$is_manager = (bool)Database::fetchOne("SELECT 1 AS x FROM privilege WHERE user_id=? AND right_str IN ('administrator','moderator')", [$uid]);

// Hydro 迁移账号：修改密码前不允许切换其他设置页
if (!empty($user['must_change_password']) && $tab !== 'password') {
    header('Location: /settings.php?tab=password&force=1');
    exit;
}

/** 保存成功后重定向（必须在输出任何 HTML 之前，保证新主题/背景立即生效） */
function settings_redirect(string $tab, string $msg): void
{
    $_SESSION['settings_flash'] = $msg;
    header('Location: /settings.php?tab=' . $tab);
    exit;
}

// 处理表单
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_bio'])) {
        $bio = $_POST['bio'] ?? '';
        Database::exec("UPDATE users SET bio=? WHERE user_id=?", [$bio, $uid]);
        $_SESSION['airoj_user']['bio'] = $bio;
        settings_redirect('profile', '简介已更新');
    }
    if (isset($_POST['enable_2fa'])) {
        $secret = $_POST['secret'] ?? '';
        $code = $_POST['code'] ?? '';
        if ($secret && $code) {
            $totp = new TOTP($secret);
            if ($totp->verify($code)) {
                Database::exec("UPDATE users SET twofa_secret=?, twofa_enabled=1 WHERE user_id=?", [$secret, $uid]);
                $_SESSION['airoj_user']['twofa_enabled'] = 1;
                settings_redirect('2fa', '二步验证已启用');
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
                settings_redirect('2fa', '二步验证已关闭');
            } else { $err = '验证码错误'; }
        } else { $err = '请输入验证码'; }
    }
    // 自定义页面背景
    if (isset($_POST['save_background'])) {
        $bg = trim($_POST['background_value'] ?? '');
        $safe_uid = preg_replace('/[^A-Za-z0-9_.-]/', '_', $uid);
        $up = save_background_upload($_FILES['background_file'] ?? [], 'bg-' . $safe_uid);

        if (is_array($up) && $up[0] === null) {
            $err = $up[1];
        } else {
            if (is_array($up)) $bg = $up[0];   // 上传的图片优先
            if (!is_valid_background($bg)) {
                $err = '背景值无效（支持 #颜色、渐变、图片地址或站内路径）';
            } else {
                Database::exec("UPDATE users SET background=? WHERE user_id=?", [$bg, $uid]);
                $_SESSION['airoj_user']['background'] = $bg;
                settings_redirect('background', $bg === '' ? '已恢复默认背景' : '背景已更新');
            }
        }
    }
    if (isset($_POST['clear_background'])) {
        Database::exec("UPDATE users SET background='' WHERE user_id=?", [$uid]);
        $_SESSION['airoj_user']['background'] = '';
        settings_redirect('background', '已恢复默认背景');
    }
    // 头像：QQ / 上传 / Gravatar
    if (isset($_POST['save_qq_avatar'])) {
        $qq = trim((string)($_POST['qq'] ?? ''));
        if (!avatars_enabled()) {
            $err = '管理员已关闭自定义头像';
        } elseif (!preg_match('/^\d{5,12}$/', $qq)) {
            $err = 'QQ 号格式不正确（5~12 位数字）';
        } else {
            $avatar = "https://q1.qlogo.cn/g?b=qq&nk={$qq}&s=640";
            Database::exec("UPDATE users SET avatar=? WHERE user_id=?", [$avatar, $uid]);
            $safe_uid = preg_replace('/[^A-Za-z0-9_.-]/', '_', $uid);
            foreach (glob(__DIR__ . '/../data/uploads/avatar-' . $safe_uid . '.*') ?: [] as $f) @unlink($f);
            $_SESSION['airoj_user']['avatar'] = $avatar;
            settings_redirect('avatar', 'QQ 头像已更新');
        }
    }
    if (isset($_POST['upload_avatar'])) {
        $file = $_FILES['avatar_file'] ?? [];
        $safe_uid = preg_replace('/[^A-Za-z0-9_.-]/', '_', $uid);
        if (!avatar_uploads_enabled()) {
            $err = '管理员已关闭头像上传';
        } elseif (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $err = '请选择头像图片';
        } elseif (($file['size'] ?? 0) > 4 * 1024 * 1024) {
            $err = '头像图片不能超过 4MB';
        } else {
            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
                $err = '仅支持 PNG / JPG / GIF / WEBP';
            } else {
                $dir = __DIR__ . '/../data/uploads/';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                foreach (glob($dir . 'avatar-' . $safe_uid . '.*') ?: [] as $f) @unlink($f);
                $name = 'avatar-' . $safe_uid . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
                    $err = '头像保存失败';
                } else {
                    $avatar = 'data/uploads/' . $name;
                    Database::exec("UPDATE users SET avatar=? WHERE user_id=?", [$avatar, $uid]);
                    $_SESSION['airoj_user']['avatar'] = $avatar;
                    settings_redirect('avatar', '头像已更新');
                }
            }
        }
    }
    if (isset($_POST['use_gravatar'])) {
        Database::exec("UPDATE users SET avatar='' WHERE user_id=?", [$uid]);
        $safe_uid = preg_replace('/[^A-Za-z0-9_.-]/', '_', $uid);
        foreach (glob(__DIR__ . '/../data/uploads/avatar-' . $safe_uid . '.*') ?: [] as $f) @unlink($f);
        $_SESSION['airoj_user']['avatar'] = '';
        settings_redirect('avatar', '已恢复 Gravatar 头像');
    }
    // 收藏品装备
    if (isset($_POST['save_collectibles'])) {
        $map = [
            'frame'     => 'equipped_frame',
            'badge'     => 'equipped_badge',
            'bbs_style' => 'equipped_bbs_style',
            'bubble'    => 'equipped_bubble',
        ];
        foreach ($map as $type => $col) {
            $cid = (int)($_POST['equip_' . $type] ?? 0);
            if ($cid > 0) {
                $owned = Database::fetchOne(
                    "SELECT 1 AS x FROM user_collectibles uc
                     JOIN collectibles c ON c.collectible_id=uc.collectible_id
                     WHERE uc.user_id=? AND uc.collectible_id=? AND c.type=?",
                    [$uid, $cid, $type]
                );
                if (!$owned) $cid = 0;
            }
            Database::exec("UPDATE users SET `$col`=? WHERE user_id=?", [$cid, $uid]);
        }
        settings_redirect('collectibles', '收藏品装备已更新');
    }
    // 称号申请
    if (isset($_POST['apply_title'])) {
        $t = trim((string)($_POST['title'] ?? ''));
        $reason = trim((string)($_POST['title_reason'] ?? ''));
        $pending = Database::fetchOne("SELECT 1 AS x FROM title_applications WHERE user_id=? AND status='pending'", [$uid]);
        if ($t === '') {
            $err = '请填写称号';
        } elseif (mb_strlen($t) > 32) {
            $err = '称号不能超过 32 个字符';
        } elseif ($pending) {
            $err = '你已有一个待审核的称号申请';
        } else {
            Database::insert("INSERT INTO title_applications (user_id,title,reason) VALUES (?,?,?)", [$uid, $t, $reason]);
            settings_redirect('title', '称号申请已提交，等待管理员审核');
        }
    }
    // 个人资料（昵称 / 邮箱 / 学校 / 名字颜色）
    if (isset($_POST['update_profile'])) {
        $nick   = trim($_POST['nick'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $school = trim($_POST['school'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = '邮箱格式不正确';
        } else {
            [$emailOk, $emailMsg] = update_user_email($uid, $email);
            if (!$emailOk) {
                $err = $emailMsg;
            } else {
                Database::exec("UPDATE users SET nick=?, school=? WHERE user_id=?", [$nick, $school, $uid]);
                $_SESSION['airoj_user']['nick']   = $nick;
                $_SESSION['airoj_user']['email']  = $email;
                $_SESSION['airoj_user']['school'] = $school;

                if (array_key_exists('name_color', $_POST)) {
                    $c = (string)$_POST['name_color'];
                    if (!valid_name_color($c)) $c = '';
                    if (!$is_manager && in_array($c, ['role', 'split'], true)) $c = '';
                    Database::exec("UPDATE users SET name_color=? WHERE user_id=?", [$c, $uid]);
                    $_SESSION['airoj_user']['name_color'] = $c;
                }
                settings_redirect('profile', $emailMsg === '邮箱未变更' ? '个人资料已更新' : $emailMsg);
            }
        }
    }
    // 修改密码：先验证原密码；若开启 2FA，再验证动态验证码
    if (isset($_POST['change_password'])) {
        $old  = (string)($_POST['password_old'] ?? '');
        $new  = (string)($_POST['password_new'] ?? '');
        $new2 = (string)($_POST['password_new2'] ?? '');

        if (!verify_login_password($user, $old)) {
            $err = '原密码错误';
        } elseif (strlen($new) < 6) {
            $err = '新密码至少 6 位';
        } elseif ($new !== $new2) {
            $err = '两次输入的新密码不一致';
        } elseif (!empty($user['twofa_enabled'])) {
            $code = trim((string)($_POST['twofa_code'] ?? ''));
            $totp = new TOTP((string)$user['twofa_secret']);
            if ($code === '' || !$totp->verify($code)) $err = '二步验证码错误';
        }

        if ($err === '') {
            // 修改成功即清除 Hydro 迁移密码，之后只保留 AirOJ 新密码
            Database::exec(
                "UPDATE users SET password=?, hydro_salt=NULL, hydro_hash=NULL, must_change_password=0 WHERE user_id=?",
                [password_hash($new, PASSWORD_DEFAULT), $uid]
            );
            $_SESSION['airoj_user']['must_change_password'] = 0;
            unset($_SESSION['airoj_user']['hydro_salt'], $_SESSION['airoj_user']['hydro_hash']);
            session_regenerate_id(true);
            settings_redirect('password', '密码已修改');
        }
    }
    // 主题切换
    if (isset($_POST['save_theme'])) {
        $t = $_POST['theme'] ?? '';
        if (valid_theme($t)) {
            set_theme($t, $user);
            settings_redirect('theme', '主题已切换为 ' . AIR_OJ_THEMES[$t]);
        } else {
            $err = '无效的主题';
        }
    }
}

// 读取一次性提示
$ok = $_SESSION['settings_flash'] ?? '';
unset($_SESSION['settings_flash']);

// 重新读取用户
$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);

// 生成 2FA 密钥（如果还没有且要展示设置界面）
$new_secret = TOTP::generateSecret();
$otpauth = TOTP::getOTPAuthURI($uid, $new_secret);

// header.php 会用 session 覆盖 $user，这里重新读一次数据库，保证设置页显示最新数据
$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);

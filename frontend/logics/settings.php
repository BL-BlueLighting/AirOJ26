<?php
$title = '设置';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/mailer.php';
require_once 'include/TOTP.php';
require_once 'include/Parsedown.php';
require_once 'include/frv.php';
require_once 'include/oierdb.php';

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
    // 个人认证申请
    if (isset($_POST['apply_verify'])) {
        $vContent = trim((string)($_POST['verify_content'] ?? ''));
        $vReason  = trim((string)($_POST['verify_reason'] ?? ''));
        $vStatus  = (string)($user['personal_verify_status'] ?? 'none');
        if ($vContent === '') {
            $err = '请填写认证内容';
        } elseif (mb_strlen($vContent) > 100) {
            $err = '认证内容不能超过 100 个字符';
        } elseif (mb_strlen($vReason) > 500) {
            $err = '申请理由不能超过 500 个字符';
        } elseif ($vStatus === 'pending') {
            $err = '你的个人认证申请正在审核中';
        } elseif ($vStatus === 'approved') {
            $err = '你已通过个人认证，如需修改请联系管理员';
        } else {
            Database::exec(
                "UPDATE users SET personal_verify_status='pending', personal_verify_content=?, personal_verify_reason=?, personal_verify_applied_at=? WHERE user_id=?",
                [$vContent, $vReason, date('Y-m-d H:i:s'), $uid]
            );
            settings_redirect('verify', '个人认证申请已提交，等待管理员审核');
        }
    }
    // 奖项认证：自动匹配 OIerDb
    if (isset($_POST['award_auto'])) {
        $aid = (int)($_POST['award_id'] ?? 0);
        $fresh = Database::fetchOne("SELECT frv_status, frv_real_name, award_verify_status FROM users WHERE user_id=?", [$uid]);
        if (($fresh['frv_status'] ?? 'none') !== 'verified') {
            $err = '请先完成实人认证';
        } elseif (($fresh['award_verify_status'] ?? 'none') === 'pending') {
            $err = '有人工认证申请正在审核中，请等待管理员处理';
        } else {
            $rec = Database::fetchOne("SELECT * FROM oier_awards WHERE id=?", [$aid]);
            if (!$rec) {
                $err = '奖项记录不存在';
            } elseif (oierdb_normalize_name((string)$rec['real_name']) !== oierdb_normalize_name((string)($fresh['frv_real_name'] ?? ''))) {
                $err = '奖项记录与实人认证姓名不一致';
            } else {
                $awardText = oierdb_record_label($rec);
                $now = date('Y-m-d H:i:s');
                Database::exec(
                    "UPDATE users SET award_verify_status='approved', award_verify_source='auto',
                        award_verify_award=?, award_verify_oier_id=?, award_verify_reason='',
                        award_verify_payload=?, award_verify_applied_at=?, award_verify_approved_at=?
                     WHERE user_id=?",
                    [$awardText, (int)$rec['id'], json_encode($rec, JSON_UNESCAPED_UNICODE), $now, $now, $uid]
                );
                settings_redirect('award', '奖项认证已通过');
            }
        }
    }
    // 奖项认证：人工申请
    if (isset($_POST['award_manual'])) {
        $fresh = Database::fetchOne("SELECT frv_status, award_verify_status FROM users WHERE user_id=?", [$uid]);
        $cfg = frv_config();
        $awardText = trim((string)($_POST['award_text'] ?? ''));
        $awardReason = trim((string)($_POST['award_reason'] ?? ''));
        if (($fresh['frv_status'] ?? 'none') !== 'verified') {
            $err = '请先完成实人认证';
        } elseif (!$cfg['manual_award']) {
            $err = '管理员未开启人工认证';
        } elseif (($fresh['award_verify_status'] ?? 'none') === 'pending') {
            $err = '已有待审核的奖项认证申请';
        } elseif (($fresh['award_verify_status'] ?? 'none') === 'approved') {
            $err = '你已通过奖项认证，如需修改请联系管理员';
        } elseif ($awardText === '') {
            $err = '请填写奖项名称';
        } elseif (mb_strlen($awardText) > 100) {
            $err = '奖项名称不能超过 100 个字符';
        } elseif ($awardReason === '') {
            $err = '请填写申请理由或证明材料';
        } elseif (mb_strlen($awardReason) > 1000) {
            $err = '申请理由不能超过 1000 个字符';
        } else {
            Database::exec(
                "UPDATE users SET award_verify_status='pending', award_verify_source='manual',
                    award_verify_award=?, award_verify_oier_id=0, award_verify_reason=?,
                    award_verify_payload='', award_verify_applied_at=?, award_verify_approved_at=''
                 WHERE user_id=?",
                [$awardText, $awardReason, date('Y-m-d H:i:s'), $uid]
            );
            settings_redirect('award', '人工认证申请已提交，等待管理员审核');
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
                $profileCtx = [
                    'user_id' => $uid,
                    'email' => $email,
                    'nick' => $nick,
                    'school' => $school,
                ];
                plugin_do('Hook:Profile/User.Update', $profileCtx);
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
            if (set_theme($t, $user)) {
                settings_redirect('theme', '主题已切换为 ' . AIR_OJ_THEMES[$t]);
            }
            $err = '主题切换已被锁定，请联系管理员';
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

// 实人认证 / 奖项认证页面数据
$frvCfg = frv_config();
$frvUi = [
    'enabled'         => $frvCfg['enabled'],
    'meta_script_url' => $frvCfg['meta_script_url'],
    'daily_limit'     => $frvCfg['daily_limit'],
    'manual_award'    => $frvCfg['manual_award'],
];
$frvRemaining = frv_remaining_attempts($user);
$awardRecords = [];
$awardTotal = 0;
$awardPages = 1;
$awardPage = 1;
$awardQuery = '';
if ($tab === 'award') {
    $awardQuery = trim((string)($_GET['aq'] ?? ''));
    $awardPage = max(1, (int)($_GET['page'] ?? 1));
    if (($user['frv_status'] ?? 'none') === 'verified') {
        try {
            $res = oierdb_search_by_name((string)($user['frv_real_name'] ?? ''), $awardQuery, $awardPage, 20);
            $awardRecords = $res['rows'];
            $awardTotal = $res['total'];
            $awardPages = $res['pages'];
            $awardPage = $res['page'];
        } catch (Throwable $e) {
            $err = $err ?: ('读取 OIerDb 数据失败：' . $e->getMessage());
        }
    }
}
$oierdbCount = oierdb_count();

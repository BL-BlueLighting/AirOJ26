<?php
$title = '设置';
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
$is_manager = (bool)Database::fetchOne("SELECT 1 AS x FROM privilege WHERE user_id=? AND right_str IN ('administrator','moderator')", [$uid]);

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
    // 个人资料（昵称 / 邮箱 / 学校 / 名字颜色）
    if (isset($_POST['update_profile'])) {
        $nick   = trim($_POST['nick'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $school = trim($_POST['school'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = '邮箱格式不正确';
        } else {
            Database::exec("UPDATE users SET nick=?, email=?, school=? WHERE user_id=?", [$nick, $email, $school, $uid]);
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
            settings_redirect('profile', '个人资料已更新');
        }
    }
    // 修改密码：先验证原密码；若开启 2FA，再验证动态验证码
    if (isset($_POST['change_password'])) {
        $old  = (string)($_POST['password_old'] ?? '');
        $new  = (string)($_POST['password_new'] ?? '');
        $new2 = (string)($_POST['password_new2'] ?? '');

        if (!password_verify($old, (string)$user['password'])) {
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
            Database::exec("UPDATE users SET password=? WHERE user_id=?", [password_hash($new, PASSWORD_DEFAULT), $uid]);
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

require 'inc/header.php';
?>
<style>
.tab-bar { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 24px; }
.tab-bar a { padding: 10px 20px; font-size: 14px; font-weight: 600; color: var(--text-secondary); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all .2s; }
.tab-bar a:hover { color: var(--text-primary); }
.tab-bar a.active { color: var(--accent); border-bottom-color: var(--accent); }
</style>

<div class="page-header"><h1><i class="fa-solid fa-gear"></i> 账号设置</h1></div>

<div class="tab-bar">
    <a href="?tab=profile" class="<?=$tab==='profile'?'active':''?>"><i class="fa-solid fa-pen-to-square"></i> 个人简介</a>
    <a href="?tab=theme" class="<?=$tab==='theme'?'active':''?>"><i class="fa-solid fa-swatchbook"></i> 主题</a>
    <a href="?tab=background" class="<?=$tab==='background'?'active':''?>"><i class="fa-solid fa-palette"></i> 页面背景</a>
    <a href="?tab=2fa" class="<?=$tab==='2fa'?'active':''?>"><i class="fa-solid fa-lock"></i> 二步验证</a>
    <a href="?tab=password" class="<?=$tab==='password'?'active':''?>"><i class="fa-solid fa-key"></i> 修改密码</a>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<?php if ($tab === 'profile'): ?>
<?php
$rating     = round($user['rating'] ?? 0);
$total_sub  = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM submissions WHERE user_id=?", [$uid])['c'] ?? 0);
$ac_sub     = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM submissions WHERE user_id=? AND score>=100", [$uid])['c'] ?? 0);
$ac_count   = (int)(Database::fetchOne("SELECT COUNT(DISTINCT problem_id) AS c FROM submissions WHERE user_id=? AND score>=100 AND status='done'", [$uid])['c'] ?? 0);
$email_md5  = md5(strtolower(trim($user['email'] ?? '')));
$my_name_color = (string)($user['name_color'] ?? '');
?>
<div class="profile-wrap">
    <div class="profile-side">
        <div class="profile-avatar">
            <img src="https://www.gravatar.com/avatar/<?= $email_md5 ?>?s=200&d=identicon" alt="avatar">
            <div class="pname"><?= user_role_html($uid, $user['nick'] ?: $uid) ?></div>
            <div class="pid"><?= htmlspecialchars($uid) ?></div>
        </div>

        <div class="profile-stats">
            <div class="row"><span class="lbl">Rating</span><span class="val" style="color:var(--accent-gold)"><?= $rating ?></span></div>
            <div class="row"><span class="lbl">已通过</span><span class="val" style="color:var(--green)"><?= $ac_count ?></span></div>
            <div class="row"><span class="lbl">总提交</span><span class="val" style="color:var(--accent)"><?= $total_sub ?></span></div>
            <div class="row"><span class="lbl">通过率</span><span class="val" style="color:var(--yellow)"><?= $total_sub > 0 ? round($ac_sub / $total_sub * 100, 1) : 0 ?>%</span></div>
            <div class="row"><span class="lbl">AC 提交</span><span class="val" style="color:var(--green)"><?= $ac_sub ?></span></div>
        </div>

        <form method="post" class="profile-info">
            <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">个人资料</div>
            <div class="form-group" style="margin-bottom:10px">
                <label>昵称</label>
                <input type="text" name="nick" class="form-input" value="<?= htmlspecialchars($user['nick'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom:10px">
                <label>邮箱</label>
                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom:10px">
                <label>学校</label>
                <input type="text" name="school" class="form-input" value="<?= htmlspecialchars($user['school'] ?? '') ?>">
            </div>
            <?php if ($is_manager): ?>
            <div class="form-group" style="margin-bottom:10px">
                <label>名字颜色</label>
                <select name="name_color" class="form-input">
                    <option value="" <?= $my_name_color === '' ? 'selected' : '' ?>>跟随 Rating</option>
                    <option value="role" <?= $my_name_color === 'role' ? 'selected' : '' ?>>管理颜色</option>
                    <option value="split" <?= $my_name_color === 'split' ? 'selected' : '' ?>>平分（左 Rating / 右管理）</option>
                    <option value="special" <?= $my_name_color === 'special' ? 'selected' : '' ?>>特殊四色渐变</option>
                    <?php foreach (AIR_OJ_NAME_COLORS as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $my_name_color === $k ? 'selected' : '' ?>><?= htmlspecialchars($v['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <p style="font-size:11px;color:var(--text-muted);margin-bottom:10px">名字颜色跟随 Rating 分级，管理员也可为你指定专属颜色。</p>
            <?php endif; ?>
            <button type="submit" name="update_profile" class="btn btn-primary btn-sm btn-block"><i class="fa-solid fa-floppy-disk"></i> 保存资料</button>
        </form>
    </div>

    <div class="profile-main">
        <div class="card">
            <h2><i class="fa-solid fa-pen-to-square"></i> 个人简介</h2>
            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">支持 Markdown 格式，将在你的个人主页显示。</p>
            <form method="post">
                <div class="form-group">
                    <textarea name="bio" class="form-input md-editor" style="min-height:180px" placeholder="介绍一下自己..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
                <button type="submit" name="update_bio" class="btn btn-primary">保存简介</button>
            </form>
            <?php if (!empty($user['bio'])): ?>
            <div style="margin-top:16px;padding:16px;background:var(--bg-secondary);border-radius:var(--radius)">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">预览：</div>
                <div class="mdui-prose" style="font-size:14px;line-height:1.7"><?= $pd->text($user['bio']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="/js/md-toolbar.js?v=1"></script>

<?php elseif ($tab === 'theme'): ?>
<?php $cur_theme = current_theme($user); ?>
<div class="card" style="max-width:820px">
    <h2><i class="fa-solid fa-swatchbook"></i> 主题</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:14px">
        选择后立即生效。登录用户的选择会保存到账号，换设备也保持一致。
    </p>
    <form method="post">
        <div class="theme-grid">
            <?php foreach (AIR_OJ_THEMES as $tk => $tl): ?>
            <label class="theme-card <?= $cur_theme === $tk ? 'active' : '' ?>">
                <input type="radio" name="theme" value="<?=$tk?>" <?= $cur_theme === $tk ? 'checked' : '' ?>>
                <span class="theme-swatch theme-swatch-<?=$tk?>"></span>
                <strong><?=htmlspecialchars($tl)?></strong>
                <span><?= $tk === 'original' ? '暗色科幻（默认）' : ($tk === 'mdui3' ? 'Material Design 3 · 黑绿主色' : '白色简洁（HUSTOJ bshark 风格）') ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" name="save_theme" class="btn btn-primary" style="margin-top:16px">
            <i class="fa-solid fa-floppy-disk"></i> 保存主题
        </button>
    </form>
</div>
<script>
document.querySelectorAll('.theme-card input').forEach(function (r) {
    r.addEventListener('change', function () {
        document.querySelectorAll('.theme-card').forEach(function (c) { c.classList.remove('active'); });
        r.closest('.theme-card').classList.add('active');
    });
});
</script>

<?php elseif ($tab === 'background'): ?>
<?php
$my_bg   = (string)($user['background'] ?? '');
$site_bg = Database::getConfig('site_background');
$bg_css  = background_css(resolve_background($user), current_theme($user));
?>
<div class="card" style="max-width:640px">
    <h2><i class="fa-solid fa-palette"></i> 页面背景</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">
        可以上传图片，也可以填写图片地址、纯色（如 <code>#1a1a2a</code>）或渐变。
        <?php if ($site_bg): ?>管理员已配置站点背景，<strong>未自定义时会自动使用站点背景</strong>。<?php endif; ?>
    </p>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <span style="font-size:13px;color:var(--text-muted)">当前生效：</span>
        <?php if ($my_bg): ?><span class="tag tag-blue">自定义背景</span>
        <?php elseif ($site_bg): ?><span class="tag tag-gold">站点默认背景</span>
        <?php else: ?><span class="tag">默认主题</span><?php endif; ?>
        <?php if ($my_bg && $site_bg): ?>
        <span style="font-size:12px;color:var(--text-muted)">（清除自定义后将回落到站点背景）</span>
        <?php endif; ?>
    </div>

    <div style="height:110px;border:1px solid var(--border);border-radius:var(--radius);margin-bottom:16px;overflow:hidden;position:relative;background:var(--bg-primary)">
        <div style="position:absolute;inset:0;<?= htmlspecialchars($bg_css, ENT_COMPAT) ?>"></div>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;text-shadow:0 1px 3px rgba(0,0,0,.8)">背景预览</div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <div class="form-group"><label>上传图片（≤4MB，PNG / JPG / GIF / WEBP）</label>
            <input type="file" name="background_file" class="form-input" accept="image/png,image/jpeg,image/gif,image/webp"></div>
        <div class="form-group"><label>或填写图片地址 / 颜色</label>
            <input type="text" name="background_value" class="form-input" value="<?=htmlspecialchars($my_bg)?>"
                   placeholder="https://example.com/bg.jpg 或 data/uploads/bg-xxx.png 或 #1a1a2a"></div>
        <button type="submit" name="save_background" class="btn btn-primary">保存背景</button>
    </form>

    <?php if ($my_bg): ?>
    <form method="post" style="margin-top:12px">
        <button type="submit" name="clear_background" class="btn btn-outline">恢复默认<?= $site_bg ? '（跟随站点背景）' : '' ?></button>
    </form>
    <?php endif; ?>
</div>

<?php elseif ($tab === '2fa'): ?>
<div class="card" style="max-width:600px">
    <h2><i class="fa-solid fa-lock"></i> 二步验证 (2FA)</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px">
        使用 Google Authenticator、Authy 等 TOTP 兼容应用扫码绑定，登录时需额外输入 6 位验证码。
    </p>

    <?php if ($user['twofa_enabled']): ?>
    <div class="alert alert-success" style="margin-bottom:16px"><i class="fa-solid fa-circle-check"></i> 二步验证已启用</div>
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
<div class="card" style="max-width:420px">
    <h2><i class="fa-solid fa-key"></i> 修改密码</h2>
    <?php if (!empty($user['twofa_enabled'])): ?>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
        你的账号已开启二步验证：需要先验证原密码，再输入验证器中的 6 位验证码。
    </p>
    <?php endif; ?>
    <form method="post">
        <div class="form-group"><label>原密码</label>
            <input type="password" name="password_old" class="form-input" required autocomplete="current-password"></div>
        <div class="form-group"><label>新密码（至少 6 位）</label>
            <input type="password" name="password_new" class="form-input" required autocomplete="new-password"></div>
        <div class="form-group"><label>确认新密码</label>
            <input type="password" name="password_new2" class="form-input" required autocomplete="new-password"></div>
        <?php if (!empty($user['twofa_enabled'])): ?>
        <div class="form-group"><label>二步验证码</label>
            <input type="text" name="twofa_code" class="form-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6 位验证码" required></div>
        <?php endif; ?>
        <button type="submit" name="change_password" class="btn btn-primary btn-block">修改密码</button>
    </form>
</div>
<?php endif; ?>

<?php require 'inc/footer.php'; ?>

<style>
.tab-bar { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 24px; }
.tab-bar a { padding: 10px 20px; font-size: 14px; font-weight: 600; color: var(--text-secondary); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all .2s; }
.tab-bar a:hover { color: var(--text-primary); }
.tab-bar a.active { color: var(--accent); border-bottom-color: var(--accent); }
</style>

<div class="page-header"><h1><i class="fa-solid fa-gear"></i> 账号设置</h1></div>

<div class="tab-bar">
    <a href="?tab=profile" class="<?=$tab==='profile'?'active':''?>"><i class="fa-solid fa-pen-to-square"></i> 个人简介</a>
    <a href="?tab=avatar" class="<?=$tab==='avatar'?'active':''?>"><i class="fa-solid fa-image"></i> 头像</a>
    <a href="?tab=theme" class="<?=$tab==='theme'?'active':''?>"><i class="fa-solid fa-swatchbook"></i> 主题</a>
    <a href="?tab=background" class="<?=$tab==='background'?'active':''?>"><i class="fa-solid fa-palette"></i> 页面背景</a>
    <a href="?tab=collectibles" class="<?=$tab==='collectibles'?'active':''?>"><i class="fa-solid fa-gem"></i> 收藏品</a>
    <a href="?tab=title" class="<?=$tab==='title'?'active':''?>"><i class="fa-solid fa-crown"></i> 称号</a>
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
            <img src="<?= htmlspecialchars(user_avatar_url($user, 200)) ?>" alt="avatar">
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
<script src="<?= theme_asset('scripts', 'md-toolbar.js') ?>"></script>

<?php elseif ($tab === 'avatar'): ?>
<?php
$avatar_now = trim((string)($user['avatar'] ?? ''));
$avatar_preview = user_avatar_url($user, 200);
$qq_now = '';
if (preg_match('~q1\.qlogo\.cn.*[?&]nk=(\d+)~', $avatar_now, $m)) $qq_now = $m[1];
if ($avatar_now === '') $avatar_source = 'Gravatar（按邮箱生成）';
elseif ($qq_now !== '') $avatar_source = 'QQ 头像';
elseif (str_starts_with($avatar_now, 'data/uploads/')) $avatar_source = '本地上传';
else $avatar_source = '自定义图片地址';
?>
<div class="card" style="max-width:640px">
    <h2><i class="fa-solid fa-image"></i> 头像设置</h2>
    <?php if (!avatars_enabled()): ?>
    <div class="alert alert-info"><i class="fa-solid fa-lock"></i> 管理员已关闭自定义头像，当前统一使用 Gravatar。</div>
    <div style="display:flex;align-items:center;gap:18px">
        <img src="<?= htmlspecialchars($avatar_preview) ?>" alt="avatar" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
        <span style="font-size:13px;color:var(--text-secondary)">Gravatar（按邮箱生成）</span>
    </div>
    <?php else: ?>
    <div style="display:flex;align-items:center;gap:18px;margin-bottom:20px">
        <img src="<?= htmlspecialchars($avatar_preview) ?>" alt="avatar" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
        <span style="font-size:13px;color:var(--text-secondary)">当前头像来源：<?= htmlspecialchars($avatar_source) ?></span>
    </div>

    <div class="form-group">
        <label>使用 QQ 头像</label>
        <form method="post" class="form-row" style="align-items:flex-end">
            <div class="form-group" style="flex:1;margin:0">
                <input type="text" name="qq" class="form-input" value="<?= htmlspecialchars($qq_now) ?>"
                       placeholder="输入 QQ 号，例如 123456" pattern="\d{5,12}" required>
            </div>
            <button type="submit" name="save_qq_avatar" class="btn btn-primary">保存 QQ 头像</button>
        </form>
        <p style="margin-top:6px;font-size:12px;color:var(--text-muted)">
            使用高清 QQ 头像：<code>https://q1.qlogo.cn/g?b=qq&amp;nk=QQ号&amp;s=640</code>
        </p>
    </div>

    <?php if (avatar_uploads_enabled()): ?>
    <div class="form-group">
        <label>上传头像</label>
        <form method="post" enctype="multipart/form-data" class="form-row" style="align-items:flex-end">
            <div class="form-group" style="flex:1;margin:0">
                <input type="file" name="avatar_file" class="form-input" accept="image/png,image/jpeg,image/gif,image/webp" required>
            </div>
            <button type="submit" name="upload_avatar" class="btn btn-primary">上传头像</button>
        </form>
        <p style="margin-top:6px;font-size:12px;color:var(--text-muted)">支持 PNG / JPG / GIF / WEBP，不超过 4MB。</p>
    </div>
    <?php else: ?>
    <p style="font-size:13px;color:var(--text-muted)">管理员已关闭头像上传。</p>
    <?php endif; ?>

    <form method="post" style="margin-top:12px">
        <button type="submit" name="use_gravatar" class="btn btn-outline">
            <i class="fa-solid fa-rotate-left"></i> 恢复 Gravatar 头像
        </button>
    </form>
    <?php endif; ?>
</div>

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
                <span><?= $tk === 'original' ? '暗色科幻（默认）' : ($tk === 'mdui3' ? 'MDUI 3 官方组件 · 黑绿主色' : ($tk === 'mdui_fake' ? 'MVC 重构前的 MDUI 仿制外观' : '白色简洁（HUSTOJ bshark 风格）')) ?></span>
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

<?php elseif ($tab === 'collectibles'): ?>
<?php
$owned = Database::fetchAll(
    "SELECT c.* FROM collectibles c
     JOIN user_collectibles uc ON uc.collectible_id=c.collectible_id
     WHERE uc.user_id=? ORDER BY c.type, c.collectible_id", [$uid]);
$byType = [];
foreach ($owned as $c) $byType[$c['type']][] = $c;
$equippedIds = [
    'frame'     => (int)($user['equipped_frame'] ?? 0),
    'badge'     => (int)($user['equipped_badge'] ?? 0),
    'bbs_style' => (int)($user['equipped_bbs_style'] ?? 0),
    'bubble'    => (int)($user['equipped_bubble'] ?? 0),
];
$displayName = $user['nick'] ?: $uid;
?>
<div class="card">
    <h2><i class="fa-solid fa-gem"></i> 收藏品</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px">收藏品由管理员授予，每种类型只能装备一件。</p>
    <form method="post">
    <?php foreach (AIR_OJ_COLLECTIBLE_TYPES as $type => $meta): ?>
        <div class="collect-block">
            <h3><i class="fa-solid <?= $meta['icon'] ?>"></i> <?= htmlspecialchars($meta['label']) ?></h3>
            <?php if (empty($byType[$type])): ?>
            <p style="color:var(--text-muted);font-size:13px">暂无该类型收藏品。</p>
            <?php else: ?>
            <label class="collect-item">
                <input type="radio" name="equip_<?= $type ?>" value="0" <?= $equippedIds[$type] === 0 ? 'checked' : '' ?>>
                <span class="collect-preview" style="color:var(--text-muted)">不使用</span>
            </label>
            <?php foreach ($byType[$type] as $c): ?>
            <label class="collect-item">
                <input type="radio" name="equip_<?= $type ?>" value="<?= (int)$c['collectible_id'] ?>" <?= $equippedIds[$type] === (int)$c['collectible_id'] ? 'checked' : '' ?>>
                <?php $css = collectible_css_value((string)$c['value']); ?>
                <?php if ($type === 'frame'): ?>
                <span class="u-frame" style="<?= htmlspecialchars($css, ENT_COMPAT) ?>"><?= htmlspecialchars($displayName) ?></span>
                <?php elseif ($type === 'badge'): ?>
                <?php
                    [$bIcon, $bColor] = array_pad(explode('|', (string)$c['value'], 2), 2, '');
                    $bIcon = strtolower(preg_replace('/[^a-z0-9-]/', '', $bIcon));
                    if ($bIcon !== '' && !str_starts_with($bIcon, 'fa-')) $bIcon = 'fa-' . $bIcon;
                    $bColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', $bColor) ? $bColor : '#f5b94a';
                ?>
                <span style="color:<?= $bColor ?>"><i class="fa-solid <?= htmlspecialchars($bIcon) ?>"></i></span>
                <?php else: ?>
                <span class="collect-preview-box" style="<?= htmlspecialchars($css, ENT_COMPAT) ?>"><?= $type === 'bubble' ? '站内信气泡预览' : '帖子样式预览' ?></span>
                <?php endif; ?>
                <span class="collect-info"><strong><?= htmlspecialchars($c['name']) ?></strong>
                    <?php if (!empty($c['description'])): ?><small><?= htmlspecialchars($c['description']) ?></small><?php endif; ?>
                </span>
            </label>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
        <button type="submit" name="save_collectibles" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> 保存装备</button>
    </form>
</div>

<?php elseif ($tab === 'title'): ?>
<?php
$myTitle = trim((string)($user['title'] ?? ''));
$titleApps = Database::fetchAll("SELECT * FROM title_applications WHERE user_id=? ORDER BY application_id DESC LIMIT 20", [$uid]);
$hasPending = false;
foreach ($titleApps as $ta) { if ($ta['status'] === 'pending') { $hasPending = true; break; } }
$statusText = ['pending' => '审核中', 'approved' => '已通过', 'rejected' => '已驳回'];
$statusCls  = ['pending' => 'tag-gold', 'approved' => 'tag-green', 'rejected' => 'tag-red'];
?>
<div class="card" style="max-width:820px">
    <h2><i class="fa-solid fa-crown"></i> 当前称号</h2>
    <p style="font-size:14px">
        <?php if ($myTitle !== ''): ?><span class="u-title"><?= htmlspecialchars($myTitle) ?></span>
        <?php else: ?><span style="color:var(--text-muted)">暂无称号</span><?php endif; ?>
    </p>
</div>

<div class="card" style="max-width:820px">
    <h2><i class="fa-solid fa-paper-plane"></i> 申请称号</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:14px">填写希望获得的称号并说明理由，管理员审核通过后会在名字旁显示。</p>
    <?php if ($hasPending): ?>
    <div class="alert alert-info"><i class="fa-solid fa-hourglass-half"></i> 你已有一个待审核的称号申请，请等待管理员处理。</div>
    <?php else: ?>
    <form method="post">
        <div class="form-group"><label>称号 *</label>
            <input type="text" name="title" class="form-input" maxlength="32" required placeholder="例如：夜行生物"></div>
        <div class="form-group"><label>申请理由</label>
            <textarea name="title_reason" class="form-input" style="min-height:100px" placeholder="简单说明为什么希望获得这个称号"></textarea></div>
        <button type="submit" name="apply_title" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> 提交申请</button>
    </form>
    <?php endif; ?>
</div>

<div class="card" style="max-width:820px">
    <h2><i class="fa-solid fa-clock-rotate-left"></i> 申请记录</h2>
    <?php if (!$titleApps): ?>
    <p style="color:var(--text-muted)">暂无申请记录。</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>称号</th><th>理由</th><th>状态</th><th>申请时间</th></tr></thead>
        <tbody>
        <?php foreach ($titleApps as $ta): ?>
        <tr>
            <td><strong><?= htmlspecialchars($ta['title']) ?></strong></td>
            <td style="color:var(--text-secondary);font-size:12px"><?= htmlspecialchars($ta['reason'] ?? '') ?></td>
            <td><span class="tag <?= $statusCls[$ta['status']] ?? '' ?>"><?= $statusText[$ta['status']] ?? htmlspecialchars($ta['status']) ?></span></td>
            <td style="color:var(--text-muted);font-size:12px"><?= htmlspecialchars($ta['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
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
    <?php if (!empty($user['must_change_password'])): ?>
    <div class="alert alert-error" style="margin-bottom:14px">
        <strong><i class="fa-solid fa-triangle-exclamation"></i> 请先修改密码</strong><br>
        你的账号从 HydroOJ 迁移而来。修改成功前无法访问其他页面。
    </div>
    <?php endif; ?>
    <?php if (!empty($user['twofa_enabled'])): ?>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
        你的账号已开启二步验证：需要先验证原密码，再输入验证器中的 6 位验证码。
    </p>
    <?php endif; ?>
    <?php if (!empty($user['hydro_hash'])): ?>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
        Hydro 迁移账号：原密码可填写 HydroOJ 密码或 AirOJ 原密码。
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


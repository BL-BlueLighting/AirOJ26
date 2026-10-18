<?php
$title = '系统设置';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: /login.php'); exit; }
$user = $_SESSION['airoj_user'];

$err = ''; $ok = '';
$uploads = __DIR__ . '/../data/uploads/';
if (!is_dir($uploads)) mkdir($uploads, 0755, true);

// Logo 上传
if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['png','jpg','jpeg','gif','svg'])) {
        $name = 'logo.' . $ext;
        move_uploaded_file($_FILES['logo_file']['tmp_name'], $uploads . $name);
        Database::setConfig('site_logo', 'data/uploads/' . $name);
        $ok = 'Logo 已更新';
    } else $err = '仅支持 PNG/JPG/GIF/SVG';
}

// Favicon 上传
if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['favicon_file']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['ico','png','jpg','jpeg','gif','svg'])) {
        // 统一存为 favicon.ico
        move_uploaded_file($_FILES['favicon_file']['tmp_name'], $uploads . 'favicon.ico');
        Database::setConfig('site_favicon', 'data/uploads/favicon.ico');
        $ok = 'Favicon 已更新';
    } else $err = '仅支持 ICO/PNG/JPG/GIF/SVG';
}

// 站点背景（未自定义背景的用户会自动使用它）
if (isset($_POST['save_site_background'])) {
    $bg = trim($_POST['site_background'] ?? '');
    $up = save_background_upload($_FILES['background_file'] ?? [], 'site-bg');
    if (is_array($up) && $up[0] === null) {
        $err = $up[1];
    } else {
        if (is_array($up)) $bg = $up[0];
        if (!is_valid_background($bg)) {
            $err = '背景值无效（支持 #颜色、渐变、图片地址或站内路径）';
        } else {
            Database::setConfig('site_background', $bg);
            $ok = $bg === '' ? '站点背景已清除' : '站点背景已更新';
        }
    }
}
if (isset($_GET['clear_background'])) {
    Database::exec("DELETE FROM system_config WHERE key_name='site_background'");
    foreach (glob($uploads . 'site-bg.*') ?: [] as $f) @unlink($f);
    $ok = '站点背景已清除';
}

// 自定义页脚（版权行始终由 inc/footer.php 强制输出，不受此处影响）
if (isset($_POST['save_footer'])) {
    Database::setConfig('site_footer', trim($_POST['site_footer'] ?? ''));
    $ok = '页脚已更新';
}
if (isset($_GET['clear_footer'])) {
    Database::exec("DELETE FROM system_config WHERE key_name='site_footer'");
    $ok = '页脚已恢复默认';
}

// Cloudflare Turnstile（注册 / 登录人机验证）
if (isset($_POST['save_turnstile'])) {
    Database::setConfig('turnstile_site_key', trim($_POST['turnstile_site_key'] ?? ''));
    Database::setConfig('turnstile_secret_key', trim($_POST['turnstile_secret_key'] ?? ''));
    $ok = 'Turnstile 设置已保存';
}
if (isset($_GET['clear_turnstile'])) {
    Database::exec("DELETE FROM system_config WHERE key_name IN ('turnstile_site_key','turnstile_secret_key')");
    $ok = 'Turnstile 已关闭';
}

// 清除设置
if (isset($_GET['clear_logo'])) {
    Database::exec("DELETE FROM system_config WHERE key_name='site_logo'");
    @unlink($uploads . 'logo.*');
    $ok = 'Logo 已清除';
}
if (isset($_GET['clear_favicon'])) {
    Database::exec("DELETE FROM system_config WHERE key_name='site_favicon'");
    @unlink($uploads . 'favicon.ico');
    $ok = 'Favicon 已清除';
}

$site_logo = Database::getConfig('site_logo');
$site_favicon = Database::getConfig('site_favicon');
$site_background = Database::getConfig('site_background');
$site_footer = Database::getConfig('site_footer');
$turnstile_site = Database::getConfig('turnstile_site_key');
$turnstile_secret = Database::getConfig('turnstile_secret_key');
$admin_current_group = 'appearance';
$admin_current_item  = 'site_settings';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header"><h1><i class="fa-solid fa-gear"></i> 系统设置</h1></div>

<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="card" style="max-width:600px">
    <h2><i class="fa-solid fa-image"></i> Logo 设置</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">上传后将在左上角显示。建议尺寸: 高 32px，格式 PNG/SVG。</p>
    <?php if ($site_logo): ?>
    <div style="margin-bottom:12px;padding:12px;background:var(--bg-secondary);border-radius:var(--radius);display:flex;align-items:center;gap:12px">
        <img src="/<?=$site_logo?>" style="max-height:32px" alt="logo">
        <span style="font-size:13px;color:var(--text-muted)">当前 Logo</span>
        <a href="?clear_logo=1" class="btn btn-danger btn-xs" style="margin-left:auto">清除</a>
    </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group"><input type="file" name="logo_file" accept="image/png,image/jpeg,image/gif,image/svg+xml" required></div>
            <button type="submit" class="btn btn-primary">上传 Logo</button>
        </div>
    </form>
</div>

<div class="card" style="max-width:600px">
    <h2><i class="fa-solid fa-star"></i> Favicon 设置</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">上传后将替换浏览器标签页图标。推荐 32×32 的 ICO 或 PNG。</p>
    <?php if ($site_favicon): ?>
    <div style="margin-bottom:12px;padding:12px;background:var(--bg-secondary);border-radius:var(--radius);display:flex;align-items:center;gap:12px">
        <img src="/<?=$site_favicon?>" style="max-width:32px;max-height:32px" alt="favicon">
        <span style="font-size:13px;color:var(--text-muted)">当前 Favicon</span>
        <a href="?clear_favicon=1" class="btn btn-danger btn-xs" style="margin-left:auto">清除</a>
    </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group"><input type="file" name="favicon_file" accept=".ico,image/png,image/jpeg,image/gif" required></div>
            <button type="submit" class="btn btn-primary">上传 Favicon</button>
        </div>
    </form>
</div>

<div class="card" style="max-width:600px">
    <h2><i class="fa-solid fa-palette"></i> 站点背景</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">
        设置后，<strong>没有自定义背景的用户会自动使用该背景</strong>；已自定义的用户不受影响。
        支持上传图片，或填写图片地址 / 纯色（<code>#1a1a2a</code>）/ 渐变。
    </p>
    <?php if ($site_background): ?>
    <div style="margin-bottom:12px;padding:12px;background:var(--bg-secondary);border-radius:var(--radius)">
        <div style="height:90px;border-radius:var(--radius);overflow:hidden;position:relative;background:var(--bg-primary);margin-bottom:8px">
            <div style="position:absolute;inset:0;<?= htmlspecialchars(background_css($site_background, current_theme($_SESSION['airoj_user'] ?? null)), ENT_COMPAT) ?>"></div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <span style="font-size:12px;color:var(--text-muted);word-break:break-all"><?=htmlspecialchars($site_background)?></span>
            <a href="?clear_background=1" class="btn btn-danger btn-xs" style="margin-left:auto" onclick="return confirm('清除站点背景？')">清除</a>
        </div>
    </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="form-group"><label>上传背景图片（≤4MB，PNG / JPG / GIF / WEBP）</label>
            <input type="file" name="background_file" class="form-input" accept="image/png,image/jpeg,image/gif,image/webp"></div>
        <div class="form-group"><label>或填写图片地址 / 颜色</label>
            <input type="text" name="site_background" class="form-input" value="<?=htmlspecialchars($site_background)?>"
                   placeholder="https://example.com/bg.jpg 或 data/uploads/site-bg.png 或 #1a1a2a"></div>
        <button type="submit" name="save_site_background" class="btn btn-primary">保存站点背景</button>
    </form>
</div>

<div class="card" style="max-width:600px">
    <h2><i class="fa-solid fa-file-lines"></i> 页脚设置</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">
        支持 Markdown，显示在页脚版权行<strong>上方</strong>。<br>
        版权行 <code>&amp;copy; Copyright <?= date('Y') ?> BL.BlueLighting &amp; AirOJ DX Team.</code> 由程序强制输出，无法通过此处修改。
    </p>
    <form method="post">
        <div class="form-group">
            <textarea name="site_footer" class="form-input" style="min-height:120px"
                      placeholder="例如：[关于我们](/faqs.php) · [提交 Bug](https://github.com/...)"><?= htmlspecialchars($site_footer) ?></textarea>
        </div>
        <button type="submit" name="save_footer" class="btn btn-primary">保存页脚</button>
        <?php if ($site_footer !== ''): ?>
        <a href="?clear_footer=1" class="btn btn-outline" onclick="return confirm('恢复默认页脚？')">恢复默认</a>
        <?php endif; ?>
    </form>
</div>

<div class="card" style="max-width:600px">
    <h2><i class="fa-solid fa-shield-halved"></i> Cloudflare Turnstile</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">
        用于注册与登录的人机验证，防止机器人批量注册。<strong>同时填写 Site Key 与 Secret Key 后自动启用</strong>；留空保存即关闭。<br>
        在 <a href="https://dash.cloudflare.com/" target="_blank" rel="noopener">Cloudflare 控制台</a> 创建 Turnstile 站点并添加域名即可获得密钥。
    </p>
    <?php if ($turnstile_site && $turnstile_secret): ?>
    <div class="alert alert-success" style="font-size:12px">✅ Turnstile 已启用（Site Key: <?=htmlspecialchars(mb_substr($turnstile_site, 0, 14))?>…）</div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group"><label>Site Key</label>
            <input type="text" name="turnstile_site_key" class="form-input" value="<?=htmlspecialchars($turnstile_site)?>" placeholder="0x4AAAAAAA..."></div>
        <div class="form-group"><label>Secret Key</label>
            <input type="text" name="turnstile_secret_key" class="form-input" value="<?=htmlspecialchars($turnstile_secret)?>" placeholder="0x4AAAAAAA..."></div>
        <button type="submit" name="save_turnstile" class="btn btn-primary">保存</button>
        <?php if ($turnstile_site || $turnstile_secret): ?>
        <a href="?clear_turnstile=1" class="btn btn-outline" onclick="return confirm('关闭 Turnstile？')">关闭</a>
        <?php endif; ?>
    </form>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>

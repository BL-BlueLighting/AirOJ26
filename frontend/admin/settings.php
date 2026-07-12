<?php
$title = '系统设置';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
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
        Database::insert("INSERT OR REPLACE INTO system_config (key_name,value) VALUES ('site_logo',?)", ['data/uploads/' . $name]);
        $ok = 'Logo 已更新';
    } else $err = '仅支持 PNG/JPG/GIF/SVG';
}

// Favicon 上传
if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['favicon_file']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['ico','png','jpg','jpeg','gif','svg'])) {
        // 统一存为 favicon.ico
        move_uploaded_file($_FILES['favicon_file']['tmp_name'], $uploads . 'favicon.ico');
        Database::insert("INSERT OR REPLACE INTO system_config (key_name,value) VALUES ('site_favicon',?)", ['data/uploads/favicon.ico']);
        $ok = 'Favicon 已更新';
    } else $err = '仅支持 ICO/PNG/JPG/GIF/SVG';
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

$site_logo = Database::fetchOne("SELECT value FROM system_config WHERE key_name='site_logo'")['value'] ?? '';
$site_favicon = Database::fetchOne("SELECT value FROM system_config WHERE key_name='site_favicon'")['value'] ?? '';
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1>⚙️ 系统设置</h1></div>

<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="card" style="max-width:600px">
    <h2>🖼️ Logo 设置</h2>
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
    <h2>🌟 Favicon 设置</h2>
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

<?php require __DIR__ . '/../inc/footer.php'; ?>

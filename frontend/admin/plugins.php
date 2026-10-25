<?php
$title = '插件管理';
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';
require_once __DIR__ . '/../include/plugin/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne(
    "SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'",
    [$_SESSION['airoj_user']['user_id'] ?? '']
);
if (!isset($_SESSION['airoj_user']) || !$is_admin) {
    header('Location: /login.php');
    exit;
}

$ok = '';
$err = '';
$pm = plugin_manager();

// 上传插件：先做静态校验，落盘后由下一次请求扫描元信息并同步数据库
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_plugin'])) {
    $uploadMax = 1024 * 1024;
    $file = $_FILES['plugin_file'] ?? null;
    $overwrite = !empty($_POST['overwrite']);

    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => '文件超过 PHP 的 upload_max_filesize 限制',
        UPLOAD_ERR_FORM_SIZE  => '文件超过表单允许的大小',
        UPLOAD_ERR_PARTIAL    => '文件只上传了一部分，请重试',
        UPLOAD_ERR_NO_FILE    => '没有选择文件',
        UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
        UPLOAD_ERR_CANT_WRITE => '服务器写入临时文件失败',
        UPLOAD_ERR_EXTENSION  => '上传被 PHP 扩展阻止',
    ];

    if (!is_array($file) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $err = '上传失败：文件可能超过服务器允许的大小';
    } elseif (!is_array($file) || !isset($file['error']) || is_array($file['error'])
        || !is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null)) {
        $err = '请选择要上传的插件文件';
    } elseif ((int)$file['error'] !== UPLOAD_ERR_OK) {
        $err = '上传失败：' . ($uploadErrors[(int)$file['error']] ?? ('错误码 ' . (int)$file['error']));
    } elseif (!is_uploaded_file((string)$file['tmp_name'])) {
        $err = '上传文件无效';
    } else {
        $size = @filesize((string)$file['tmp_name']);
        $original = basename(str_replace('\\', '/', (string)$file['name']));
        $ext = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
        $rawSlug = (string)pathinfo($original, PATHINFO_FILENAME);
        $slug = trim((string)preg_replace('/[^A-Za-z0-9_-]+/', '_', $rawSlug), '_');
        $src = ($size !== false && $size > 0 && $size <= $uploadMax)
            ? (string)@file_get_contents((string)$file['tmp_name'])
            : '';

        if ($size === false || $size <= 0) {
            $err = '不能上传空文件';
        } elseif ($size > $uploadMax) {
            $err = '插件文件不能超过 1 MB';
        } elseif ($ext !== 'php') {
            $err = '只支持上传 .php 文件';
        } elseif ($slug === '' || strlen($slug) > 64) {
            $err = '文件名无效：只能包含字母、数字、下划线和连字符（最长 64 字符）';
        } elseif (!preg_match('/^\s*<\?php/i', $src)) {
            $err = '插件文件必须以 <?php 开头';
        } elseif (strpos($src, 'AIR_OJ_PLUGIN_LOADER') === false) {
            $err = '插件文件缺少 AIR_OJ_PLUGIN_LOADER 防护，请参考示例插件';
        } else {
            $dir = $pm->dir();
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $dest = $dir . '/' . $slug . '.php';
            if (!is_dir($dir) || !is_writable($dir)) {
                $err = '插件目录不可写：' . $dir;
            } elseif (is_file($dest) && !$overwrite) {
                $err = '已存在同名插件 ' . $slug . '.php；如需更新请勾选“覆盖同名插件”';
            } elseif (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
                $err = '保存插件文件失败，请检查目录权限';
            } else {
                @chmod($dest, 0644);
                try {
                    // 上传/覆盖后先禁用，待管理员确认权限后再启用
                    Database::exec(
                        "UPDATE plugins SET enabled=0, updated_at=? WHERE slug=?",
                        [date('Y-m-d H:i:s'), $slug]
                    );
                } catch (Throwable $e) {
                    error_log('[AirOJ plugin] disable after upload failed: ' . $e->getMessage());
                }
                $_SESSION['arioj_plugin_upload_flash'] = $slug;
                header('Location: /admin/plugins.php');
                exit;
            }
        }
    }
}

try {
    $pm->syncDatabase();
} catch (Throwable $e) {
    $err = $err ?: ('同步插件失败：' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plugin'])) {
    $slug = (string)($_POST['slug'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug)) {
        $err = '插件标识无效';
    } else {
        $row = Database::fetchOne("SELECT slug FROM plugins WHERE slug=?", [$slug]);
        if (!$row) {
            $err = '插件不存在，请刷新列表后重试';
        } else {
            $enabled = !empty($_POST['enabled']) ? 1 : 0;
            Database::exec("UPDATE plugins SET enabled=?, updated_at=? WHERE slug=?", [$enabled, date('Y-m-d H:i:s'), $slug]);
            $plugin = $pm->plugin($slug);
            if ($plugin) {
                $checked = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
                foreach ($plugin->permissions as $perm) {
                    $on = in_array($perm, $checked, true) ? 1 : 0;
                    $exists = Database::fetchOne(
                        "SELECT permission FROM plugin_permissions WHERE slug=? AND permission=?",
                        [$slug, $perm]
                    );
                    if ($exists) {
                        Database::exec(
                            "UPDATE plugin_permissions SET enabled=? WHERE slug=? AND permission=?",
                            [$on, $slug, $perm]
                        );
                    } else {
                        Database::insert(
                            "INSERT INTO plugin_permissions (slug,permission,enabled) VALUES (?,?,?)",
                            [$slug, $perm, $on]
                        );
                    }
                }
            }
            header('Location: /admin/plugins.php?saved=1');
            exit;
        }
    }
}
if (isset($_GET['saved'])) $ok = '插件设置已保存，下一次请求生效';

$rows = [];
try {
    $rows = Database::fetchAll("SELECT * FROM plugins ORDER BY name ASC, slug ASC");
} catch (Throwable $e) {
    $err = $err ?: ('读取插件失败：' . $e->getMessage());
}
$states = [];
try {
    foreach (Database::fetchAll("SELECT slug,permission,enabled FROM plugin_permissions") as $r) {
        $states[(string)$r['slug']][(string)$r['permission']] = (int)$r['enabled'] === 1;
    }
} catch (Throwable $e) {}
$pluginErrors = $pm->errors();

// 上一次上传的结果（通过 session 传递，避免被 URL 伪造）
$uploadedSlug = (string)($_SESSION['arioj_plugin_upload_flash'] ?? '');
unset($_SESSION['arioj_plugin_upload_flash']);
if ($uploadedSlug !== '') {
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $uploadedSlug)) {
        $err = $err ?: '上传结果无效';
    } else {
        $uploadError = $pluginErrors[$uploadedSlug] ?? '';
        $uploadedRow = Database::fetchOne("SELECT name FROM plugins WHERE slug=?", [$uploadedSlug]);
        if ($uploadError !== '') {
            if (strpos($uploadError, '同步数据库失败：') === 0) {
                $err = $err ?: ('插件已上传，但' . $uploadError);
            } else {
                $err = $err ?: ('插件已上传，但加载失败：' . $uploadError
                    . '（已保持禁用；请修复后重新上传，或删除 plugins/' . $uploadedSlug . '.php）');
            }
        } elseif ($uploadedRow) {
            $ok = $ok ?: ('插件「' . $uploadedRow['name'] . '」已上传，当前为禁用状态，请检查权限后启用。');
        } else {
            $err = $err ?: '插件文件已上传，但未能生成插件记录，请检查文件内容或目录权限。';
        }
    }
}

// 加载失败且不在数据库中的插件文件（例如手动放入目录或上传后校验失败）
$knownSlugs = [];
foreach ($rows as $r) $knownSlugs[(string)$r['slug']] = true;
$orphanErrors = array_filter(
    $pluginErrors,
    static fn(string $slug): bool => !isset($knownSlugs[$slug]),
    ARRAY_FILTER_USE_KEY
);
if ($uploadedSlug !== '') unset($orphanErrors[$uploadedSlug]);

$groups = \AirOJ\Plugin\PermissionRegistry::groups();

$admin_current_group = 'plugin';
$admin_current_item  = 'plugin_list';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header"><h1><i class="fa-solid fa-puzzle-piece"></i> 插件管理</h1></div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="alert alert-info" style="font-size:13px">
    插件是管理员安装的 PHP 代码，请只启用可信插件。插件默认禁用；禁用插件不会执行 <code>boot()</code>。
    权限开关是执行层面的限制，不是 PHP 沙箱。
</div>

<div class="card" style="max-width:900px">
    <h2><i class="fa-solid fa-upload"></i> 上传插件</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">
        仅支持单个 <code>.php</code> 文件，最大 1 MB。文件必须以 <code>&lt;?php</code> 开头、使用
        <code>AIR_OJ_PLUGIN_LOADER</code> 防止直接访问，并 return 一个 <code>AirOJ\Plugin\Plugin</code> 实例。
        上传或覆盖后插件会自动禁用，请检查权限后再启用。
    </p>
    <form method="post" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group"><input type="file" name="plugin_file" accept=".php,application/x-php" required></div>
            <button type="submit" name="upload_plugin" class="btn btn-primary"><i class="fa-solid fa-upload"></i> 上传插件</button>
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:12px;color:var(--text-muted);cursor:pointer">
            <input type="checkbox" name="overwrite" value="1"> 覆盖同名插件（覆盖后同样会禁用）
        </label>
    </form>
</div>

<?php if ($orphanErrors): ?>
<div class="alert alert-error" style="max-width:900px">
    <strong>以下插件文件加载失败：</strong>
    <ul style="margin:8px 0 0 18px">
        <?php foreach ($orphanErrors as $slug => $msg): ?>
        <li><code><?= htmlspecialchars((string)$slug) ?>.php</code>：<?= htmlspecialchars((string)$msg) ?></li>
        <?php endforeach; ?>
    </ul>
    <p style="font-size:12px;margin:8px 0 0">请修复文件后重新上传，或从插件目录中删除。</p>
</div>
<?php endif; ?>

<?php if (!$rows): ?>
<div class="card"><p style="color:var(--text-muted)">还没有插件。使用上方上传功能，或将单个 PHP 文件放到 <code>frontend/plugins/</code> 后刷新本页。</p></div>
<?php endif; ?>

<?php foreach ($rows as $row):
    $slug = (string)$row['slug'];
    $plugin = $pm->plugin($slug);
    $declared = $plugin ? $plugin->permissions : [];
    $pstate = $states[$slug] ?? [];
    $loadError = $pluginErrors[$slug] ?? '';
?>
<div class="card" style="max-width:900px">
    <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap">
        <?php if (!empty($row['icon'])): ?>
        <img src="<?= htmlspecialchars((string)$row['icon']) ?>" alt="" style="width:42px;height:42px;object-fit:contain;border-radius:8px;background:var(--bg-secondary);padding:4px">
        <?php else: ?>
        <div style="width:42px;height:42px;border-radius:10px;background:var(--accent-bg);color:var(--accent);display:flex;align-items:center;justify-content:center">
            <i class="fa-solid fa-puzzle-piece"></i>
        </div>
        <?php endif; ?>
        <div style="flex:1;min-width:220px">
            <h2 style="margin:0 0 4px"><?= htmlspecialchars((string)$row['name']) ?>
                <span class="badge" style="margin-left:6px">v<?= htmlspecialchars((string)$row['version']) ?></span>
            </h2>
            <div style="font-size:12px;color:var(--text-muted)">
                作者：<?= htmlspecialchars((string)$row['author']) ?> · 标识：<code><?= htmlspecialchars($slug) ?></code>
                · <?= (int)$row['enabled'] ? '<span class="tag tag-green">已启用</span>' : '<span class="tag tag-red">未启用</span>' ?>
            </div>
            <?php if (!empty($row['description'])): ?>
            <p style="margin:8px 0 0;color:var(--text-secondary);font-size:13px"><?= nl2br(htmlspecialchars((string)$row['description'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($loadError !== ''): ?>
    <div class="alert alert-error" style="margin-top:12px;font-size:12px"><?= htmlspecialchars($loadError) ?></div>
    <?php endif; ?>

    <form method="post" style="margin-top:14px">
        <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer;font-weight:600">
            <input type="checkbox" name="enabled" value="1" <?= (int)$row['enabled'] ? 'checked' : '' ?>>
            启用插件
        </label>

        <?php if ($plugin && $declared): ?>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">权限（取消勾选后对应 Hook / Call / Have 不执行）</div>
        <?php foreach ($groups as $groupName => $entries):
            $mine = array_values(array_filter($entries, fn($e) => in_array($e['permission'], $declared, true)));
            if (!$mine) continue;
        ?>
        <div style="border:1px solid var(--border);border-radius:var(--radius);padding:10px 12px;margin-bottom:10px">
            <div style="font-size:12px;color:var(--text-secondary);font-weight:700;margin-bottom:8px"><?= htmlspecialchars($groupName) ?></div>
            <?php foreach ($mine as $e):
                $perm = $e['permission'];
                $checked = $pstate[$perm] ?? true;
            ?>
            <label style="display:flex;align-items:flex-start;gap:8px;margin:6px 0;cursor:pointer;font-size:12px">
                <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm) ?>" <?= $checked ? 'checked' : '' ?> style="margin-top:2px">
                <span>
                    <code><?= htmlspecialchars($perm) ?></code>
                    <span class="tag" style="margin-left:4px;background:var(--bg-secondary);color:var(--text-muted)"><?= htmlspecialchars($e['type']) ?></span>
                    <br><span style="color:var(--text-muted)"><?= htmlspecialchars($e['description']) ?></span>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php elseif ($plugin): ?>
        <p style="font-size:12px;color:var(--text-muted)">该插件未声明任何权限。</p>
        <?php else: ?>
        <p style="font-size:12px;color:var(--text-muted)">插件文件缺失或加载失败，无法读取权限声明。</p>
        <?php endif; ?>

        <button type="submit" name="save_plugin" class="btn btn-primary">保存插件设置</button>
    </form>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>

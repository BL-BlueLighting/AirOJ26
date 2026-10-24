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
try {
    $pm->syncDatabase();
} catch (Throwable $e) {
    $err = '同步插件失败：' . $e->getMessage();
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

<?php if (!$rows): ?>
<div class="card"><p style="color:var(--text-muted)">还没有插件。将单个 PHP 文件放到 <code>frontend/plugins/</code> 后刷新本页。</p></div>
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

<?php
$title = '收藏品管理';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$err = ''; $ok = '';
$typeColumns = [
    'frame'     => 'equipped_frame',
    'badge'     => 'equipped_badge',
    'bbs_style' => 'equipped_bbs_style',
    'bubble'    => 'equipped_bubble',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $type = $_POST['type'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $value = trim($_POST['value'] ?? '');
        if (!isset(AIR_OJ_COLLECTIBLE_TYPES[$type])) {
            $err = '请选择有效的收藏品类型';
        } elseif ($name === '') {
            $err = '请填写名称';
        } elseif ($value === '') {
            $err = '请填写样式 / 徽章配置';
        } elseif ($type === 'badge' && !preg_match('/^fa-[a-z0-9-]+\|#[0-9a-fA-F]{3,8}$/', $value)) {
            $err = '徽章格式应为 图标|颜色，例如 fa-crown|#ffd700';
        } else {
            if ($type !== 'badge') $value = collectible_css_value($value);
            if ($value === '') $err = '样式内容为空或包含非法字符';
            else {
                Database::insert("INSERT INTO collectibles (type,name,description,value) VALUES (?,?,?,?)",
                    [$type, $name, $desc, $value]);
                $ok = '收藏品已添加';
            }
        }
    } elseif ($act === 'grant') {
        $uid = trim($_POST['user_id'] ?? '');
        $cid = intval($_POST['collectible_id'] ?? 0);
        if (!Database::fetchOne("SELECT 1 AS x FROM users WHERE user_id=?", [$uid])) $err = '用户不存在';
        elseif (!Database::fetchOne("SELECT 1 AS x FROM collectibles WHERE collectible_id=?", [$cid])) $err = '收藏品不存在';
        else {
            if (DB_DRIVER === 'mysql') {
                Database::exec("INSERT IGNORE INTO user_collectibles (user_id,collectible_id) VALUES (?,?)", [$uid, $cid]);
            } else {
                Database::exec("INSERT OR IGNORE INTO user_collectibles (user_id,collectible_id) VALUES (?,?)", [$uid, $cid]);
            }
            $ok = "已授予 {$uid} 收藏品";
        }
    }
}

if (isset($_GET['del'])) {
    $cid = intval($_GET['del']);
    $c = Database::fetchOne("SELECT * FROM collectibles WHERE collectible_id=?", [$cid]);
    if ($c) {
        Database::exec("DELETE FROM user_collectibles WHERE collectible_id=?", [$cid]);
        $col = $typeColumns[$c['type']] ?? '';
        if ($col !== '') {
            Database::exec("UPDATE users SET `$col`=0 WHERE `$col`=?", [$cid]);
        }
        Database::exec("DELETE FROM collectibles WHERE collectible_id=?", [$cid]);
        $ok = '收藏品已删除';
    }
}

$rows = Database::fetchAll(
    "SELECT c.*, (SELECT COUNT(*) FROM user_collectibles uc WHERE uc.collectible_id=c.collectible_id) AS owner_count
     FROM collectibles c ORDER BY c.type, c.collectible_id"
);
$admin_current_group = 'growth';
$admin_current_item  = 'collectible_list';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1><i class="fa-solid fa-gem"></i> 收藏品管理</h1>
</div>

<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr>
    <th style="width:50px">#</th><th style="width:110px">类型</th><th style="width:180px">名称</th>
    <th>配置</th><th style="width:90px">拥有者</th><th style="width:90px">操作</th>
</tr></thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">暂无收藏品，请在下方添加</td></tr>
<?php else: foreach ($rows as $c): ?>
<tr>
    <td><?= (int)$c['collectible_id'] ?></td>
    <td><span class="tag tag-blue"><?= htmlspecialchars(AIR_OJ_COLLECTIBLE_TYPES[$c['type']]['label'] ?? $c['type']) ?></span></td>
    <td><strong><?= htmlspecialchars($c['name']) ?></strong>
        <?php if (!empty($c['description'])): ?><br><span style="font-size:12px;color:var(--text-muted)"><?=htmlspecialchars($c['description'])?></span><?php endif; ?></td>
    <td><code style="font-size:11px;color:var(--text-secondary);word-break:break-all"><?=htmlspecialchars($c['value'])?></code></td>
    <td><?= (int)$c['owner_count'] ?></td>
    <td><a href="?del=<?= (int)$c['collectible_id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('删除该收藏品？拥有者的记录也会删除。')">删除</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>

<div class="card" style="max-width:820px">
    <h2><i class="fa-solid fa-plus"></i> 添加收藏品</h2>
    <form method="post"><input type="hidden" name="act" value="add">
        <div class="form-row">
            <div class="form-group"><label>类型 *</label>
                <select name="type" class="form-input" required>
                    <?php foreach (AIR_OJ_COLLECTIBLE_TYPES as $k => $m): ?>
                    <option value="<?= $k ?>"><?=htmlspecialchars($m['label'])?>（<?= $k ?>）</option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>名称 *</label><input type="text" name="name" class="form-input" required></div>
        </div>
        <div class="form-group"><label>说明</label><input type="text" name="description" class="form-input"></div>
        <div class="form-group"><label>配置 *</label>
            <input type="text" name="value" class="form-input" required
                   placeholder="徽章：fa-crown|#ffd700；其它类型填 CSS 声明，例如 border:1px solid #a855f7;border-radius:8px;padding:2px 8px;">
            <div style="font-size:12px;color:var(--text-muted);margin-top:6px">
                徽章格式为 <code>图标|颜色</code>；姓名框 / 讨论区样式 / 站内信气泡填写 CSS 声明（不允许引号、尖括号、花括号）。
            </div>
        </div>
        <button type="submit" class="btn btn-primary">添加收藏品</button>
    </form>
</div>

<div class="card" style="max-width:820px">
    <h2><i class="fa-solid fa-hand-holding-heart"></i> 授予收藏品</h2>
    <form method="post"><input type="hidden" name="act" value="grant">
        <div class="form-row">
            <div class="form-group"><label>用户名 *</label><input type="text" name="user_id" class="form-input" required></div>
            <div class="form-group"><label>收藏品 *</label>
                <select name="collectible_id" class="form-input" required>
                    <?php foreach ($rows as $c): ?>
                    <option value="<?= (int)$c['collectible_id'] ?>">[<?=htmlspecialchars(AIR_OJ_COLLECTIBLE_TYPES[$c['type']]['label'] ?? $c['type'])?>] <?=htmlspecialchars($c['name'])?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <button type="submit" class="btn btn-primary">授予</button>
    </form>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

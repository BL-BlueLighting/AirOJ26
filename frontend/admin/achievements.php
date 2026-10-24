<?php
$title = '成就管理';
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$err = ''; $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $code = strtolower(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-award');
        $color = trim($_POST['color'] ?? '#a855f7');
        if (!preg_match('/^[a-z0-9_]{2,32}$/', $code)) $err = '标识只能包含小写字母、数字和下划线（2~32 位）';
        elseif ($name === '') $err = '请填写成就名称';
        elseif (!preg_match('/^fa-[a-z0-9-]+$/', $icon)) $err = '图标格式应为 fa-xxx';
        elseif (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $err = '颜色格式应为 #RRGGBB';
        elseif (Database::fetchOne("SELECT 1 AS x FROM achievements WHERE code=?", [$code])) $err = '该标识已存在';
        else {
            Database::insert("INSERT INTO achievements (code,name,description,icon,color) VALUES (?,?,?,?,?)",
                [$code, $name, $desc, $icon, $color]);
            $ok = '成就已添加';
        }
    } elseif ($act === 'grant') {
        $uid = trim($_POST['user_id'] ?? '');
        $aid = intval($_POST['achievement_id'] ?? 0);
        if (!Database::fetchOne("SELECT 1 AS x FROM users WHERE user_id=?", [$uid])) $err = '用户不存在';
        elseif (!Database::fetchOne("SELECT 1 AS x FROM achievements WHERE achievement_id=?", [$aid])) $err = '成就不存在';
        else {
            if (DB_DRIVER === 'mysql') {
                Database::exec("INSERT IGNORE INTO user_achievements (user_id,achievement_id) VALUES (?,?)", [$uid, $aid]);
            } else {
                Database::exec("INSERT OR IGNORE INTO user_achievements (user_id,achievement_id) VALUES (?,?)", [$uid, $aid]);
            }
            $ok = "已授予 {$uid} 成就";
        }
    }
}
if (isset($_GET['del'])) {
    $aid = intval($_GET['del']);
    $a = Database::fetchOne("SELECT code FROM achievements WHERE achievement_id=?", [$aid]);
    if ($a && $a['code'] === 'night_owl') {
        $err = '内置成就「夜猫子」不能删除';
    } else {
        Database::exec("DELETE FROM user_achievements WHERE achievement_id=?", [$aid]);
        Database::exec("DELETE FROM achievements WHERE achievement_id=?", [$aid]);
        $ok = '成就已删除';
    }
}

$rows = Database::fetchAll(
    "SELECT a.*, (SELECT COUNT(*) FROM user_achievements ua WHERE ua.achievement_id=a.achievement_id) AS unlocked_count
     FROM achievements a ORDER BY a.achievement_id"
);
$admin_current_group = 'growth';
$admin_current_item  = 'achievement_list';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1><i class="fa-solid fa-trophy"></i> 成就管理</h1>
</div>

<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr>
    <th style="width:50px">#</th><th style="width:220px">成就</th><th>说明</th>
    <th style="width:110px">解锁人数</th><th style="width:100px">操作</th>
</tr></thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">暂无成就</td></tr>
<?php else: foreach ($rows as $a): ?>
<tr>
    <td><?= (int)$a['achievement_id'] ?></td>
    <td><span style="color:<?=htmlspecialchars($a['color'])?>;font-weight:700"><i class="fa-solid <?=htmlspecialchars($a['icon'])?>"></i> <?=htmlspecialchars($a['name'])?></span>
        <br><code style="font-size:11px;color:var(--text-muted)"><?=htmlspecialchars($a['code'])?></code></td>
    <td style="font-size:13px;color:var(--text-secondary)"><?=htmlspecialchars($a['description'] ?? '')?></td>
    <td><?= (int)$a['unlocked_count'] ?></td>
    <td><a href="?del=<?= (int)$a['achievement_id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('删除该成就？用户已获得的记录也会删除。')">删除</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>

<div class="card" style="max-width:760px">
    <h2><i class="fa-solid fa-plus"></i> 添加成就</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">内置的「夜猫子」由系统自动判定；其余成就可在下方手动授予用户。</p>
    <form method="post"><input type="hidden" name="act" value="add">
        <div class="form-row">
            <div class="form-group"><label>标识 *</label><input type="text" name="code" class="form-input" placeholder="例如 early_bird" required></div>
            <div class="form-group"><label>名称 *</label><input type="text" name="name" class="form-input" required></div>
        </div>
        <div class="form-group"><label>说明</label><input type="text" name="description" class="form-input"></div>
        <div class="form-row">
            <div class="form-group"><label>图标（Font Awesome）</label><input type="text" name="icon" class="form-input" value="fa-award"></div>
            <div class="form-group"><label>颜色</label><input type="text" name="color" class="form-input" value="#a855f7"></div>
        </div>
        <button type="submit" class="btn btn-primary">添加成就</button>
    </form>
</div>

<div class="card" style="max-width:760px">
    <h2><i class="fa-solid fa-hand-holding-heart"></i> 授予成就</h2>
    <form method="post"><input type="hidden" name="act" value="grant">
        <div class="form-row">
            <div class="form-group"><label>用户名 *</label><input type="text" name="user_id" class="form-input" required></div>
            <div class="form-group"><label>成就 *</label>
                <select name="achievement_id" class="form-input" required>
                    <?php foreach ($rows as $a): ?>
                    <option value="<?= (int)$a['achievement_id'] ?>"><?=htmlspecialchars($a['name'])?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <button type="submit" class="btn btn-primary">授予</button>
    </form>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

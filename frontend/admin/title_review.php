<?php
$title = '称号审核';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$admin_id = $_SESSION['airoj_user']['user_id'];
$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'approve' || $act === 'reject') {
        $aid = intval($_POST['application_id'] ?? 0);
        $app = Database::fetchOne("SELECT * FROM title_applications WHERE application_id=?", [$aid]);
        if (!$app) {
            $err = '申请不存在';
        } elseif ($app['status'] !== 'pending') {
            $err = '该申请已处理';
        } else {
            $status = $act === 'approve' ? 'approved' : 'rejected';
            Database::exec("UPDATE title_applications SET status=?, reviewed_by=?, reviewed_at=? WHERE application_id=?",
                [$status, $admin_id, date('Y-m-d H:i:s'), $aid]);
            if ($act === 'approve') {
                Database::exec("UPDATE users SET title=? WHERE user_id=?", [$app['title'], $app['user_id']]);
                $ok = '已通过称号申请';
            } else {
                $ok = '已驳回称号申请';
            }
        }
    } elseif ($act === 'grant') {
        $uid = trim($_POST['user_id'] ?? '');
        $t = trim($_POST['title'] ?? '');
        if (!Database::fetchOne("SELECT 1 AS x FROM users WHERE user_id=?", [$uid])) $err = '用户不存在';
        elseif ($t === '') $err = '请填写称号';
        elseif (mb_strlen($t) > 32) $err = '称号不能超过 32 个字符';
        else {
            Database::exec("UPDATE users SET title=? WHERE user_id=?", [$t, $uid]);
            Database::insert("INSERT INTO title_applications (user_id,title,reason,status,reviewed_by,reviewed_at) VALUES (?,?,?,'approved',?,?)",
                [$uid, $t, '管理员直接授予', $admin_id, date('Y-m-d H:i:s')]);
            $ok = '称号已授予';
        }
    } elseif ($act === 'revoke') {
        $uid = trim($_POST['user_id'] ?? '');
        Database::exec("UPDATE users SET title='' WHERE user_id=?", [$uid]);
        $ok = '已移除称号';
    }
}

$apps = Database::fetchAll(
    "SELECT ta.*, u.nick FROM title_applications ta
     LEFT JOIN users u ON u.user_id=ta.user_id
     ORDER BY (ta.status='pending') DESC, ta.application_id DESC LIMIT 100"
);
$granted = Database::fetchAll(
    "SELECT user_id, title FROM users WHERE title<>'' ORDER BY user_id LIMIT 100"
);

$admin_current_group = 'growth';
$admin_current_item  = 'title_review';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1><i class="fa-solid fa-crown"></i> 称号审核</h1>
</div>

<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr>
    <th style="width:50px">#</th><th style="width:140px">用户</th><th style="width:160px">称号</th>
    <th>理由</th><th style="width:90px">状态</th><th style="width:150px">申请时间</th><th style="width:150px">操作</th>
</tr></thead>
<tbody>
<?php if (!$apps): ?>
<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">暂无称号申请</td></tr>
<?php else: foreach ($apps as $a):
    $st = ['pending' => '审核中', 'approved' => '已通过', 'rejected' => '已驳回'][$a['status']] ?? $a['status'];
    $cls = ['pending' => 'tag-gold', 'approved' => 'tag-green', 'rejected' => 'tag-red'][$a['status']] ?? '';
?>
<tr>
    <td><?= (int)$a['application_id'] ?></td>
    <td><?= user_role_html($a['user_id'], $a['nick'] ?: null) ?></td>
    <td><span class="u-title"><?= htmlspecialchars($a['title']) ?></span></td>
    <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($a['reason'] ?? '') ?></td>
    <td><span class="tag <?= $cls ?>"><?= $st ?></span></td>
    <td style="color:var(--text-muted);font-size:12px"><?= htmlspecialchars($a['created_at']) ?></td>
    <td>
        <?php if ($a['status'] === 'pending'): ?>
        <form method="post" style="display:inline"><input type="hidden" name="act" value="approve"><input type="hidden" name="application_id" value="<?= (int)$a['application_id'] ?>"><button class="btn btn-success btn-xs">通过</button></form>
        <form method="post" style="display:inline"><input type="hidden" name="act" value="reject"><input type="hidden" name="application_id" value="<?= (int)$a['application_id'] ?>"><button class="btn btn-danger btn-xs">驳回</button></form>
        <?php else: ?><span style="color:var(--text-muted);font-size:12px">已处理</span><?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>

<div class="card" style="max-width:760px">
    <h2><i class="fa-solid fa-hand-holding-heart"></i> 直接授予称号</h2>
    <form method="post"><input type="hidden" name="act" value="grant">
        <div class="form-row">
            <div class="form-group"><label>用户名 *</label><input type="text" name="user_id" class="form-input" required></div>
            <div class="form-group"><label>称号 *</label><input type="text" name="title" class="form-input" maxlength="32" required></div>
        </div>
        <button type="submit" class="btn btn-primary">授予称号</button>
    </form>
</div>

<div class="card" style="max-width:760px">
    <h2><i class="fa-solid fa-users"></i> 当前称号持有者</h2>
    <?php if (!$granted): ?>
    <p style="color:var(--text-muted)">暂无用户拥有称号。</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>用户</th><th>称号</th><th style="width:100px">操作</th></tr></thead>
        <tbody>
        <?php foreach ($granted as $g): ?>
        <tr>
            <td><?= user_role_html($g['user_id']) ?></td>
            <td><span class="u-title"><?= htmlspecialchars($g['title']) ?></span></td>
            <td><form method="post" onsubmit="return confirm('移除该用户的称号？')"><input type="hidden" name="act" value="revoke"><input type="hidden" name="user_id" value="<?=htmlspecialchars($g['user_id'])?>"><button class="btn btn-danger btn-xs">移除</button></form></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

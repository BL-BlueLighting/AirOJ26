<?php
$title = '管理团队';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (!isset($_SESSION['airoj_user'])) { header('Location: /login.php'); exit; }
$uid = $_SESSION['airoj_user']['user_id'];
$tid = intval($_GET['id'] ?? 0);
$team = Database::fetchOne("SELECT * FROM teams WHERE team_id=?", [$tid]);
if (!$team) { header('Location: /team.php'); exit; }

// Check permission
$member = Database::fetchOne("SELECT * FROM team_members WHERE team_id=? AND user_id=? AND status='approved'", [$tid, $uid]);
$is_owner = $member && $member['role'] === 'owner';
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$uid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target = $_POST['target'] ?? '';

    if ($action === 'edit' && $is_owner) {
        Database::exec("UPDATE teams SET name=?, description=? WHERE team_id=?", [$_POST['name'], $_POST['description'], $tid]);
        $ok = '团队信息已更新';
    }
    if ($action === 'invite' && $is_owner) {
        if ($target) {
            $existing = Database::fetchOne("SELECT * FROM team_members WHERE team_id=? AND user_id=?", [$tid, $target]);
            if (!$existing) {
                Database::insert("INSERT INTO team_members (team_id,user_id,role,status) VALUES (?,?,'member','approved')", [$tid, $target]);
                $ok = "已邀请 {$target}";
            } else $err = '该用户已是成员';
        }
    }
    if ($action === 'kick' && $is_owner) {
        Database::exec("DELETE FROM team_members WHERE team_id=? AND user_id=?", [$tid, $target]);
        $ok = "已移除成员 {$target}";
    }
    if ($action === 'transfer' && $is_owner) {
        Database::exec("UPDATE team_members SET role='member' WHERE team_id=? AND user_id=?", [$tid, $uid]);
        Database::exec("UPDATE team_members SET role='owner' WHERE team_id=? AND user_id=?", [$tid, $target]);
        Database::exec("UPDATE teams SET owner=? WHERE team_id=?", [$target, $tid]);
        $ok = "团队已转让给 {$target}";
    }
    if ($action === 'delete' && ($is_owner || $is_admin)) {
        Database::exec("DELETE FROM team_members WHERE team_id=?", [$tid]);
        Database::exec("DELETE FROM team_applications WHERE team_id=?", [$tid]);
        Database::exec("DELETE FROM teams WHERE team_id=?", [$tid]);
        header('Location: /team.php?deleted=1'); exit;
    }
    if ($action === 'approve' && ($is_owner || $is_admin)) {
        Database::exec("INSERT OR IGNORE INTO team_members (team_id,user_id,role,status) VALUES (?,?,'member','approved')", [$tid, $target]);
        Database::exec("UPDATE team_applications SET status='approved' WHERE team_id=? AND user_id=?", [$tid, $target]);
        $ok = "已通过 {$target} 的申请";
    }
    if ($action === 'reject' && ($is_owner || $is_admin)) {
        Database::exec("UPDATE team_applications SET status='rejected' WHERE team_id=? AND user_id=?", [$tid, $target]);
        $ok = "已拒绝 {$target} 的申请";
    }
    if ($action === 'verify' && $is_admin) {
        Database::exec("UPDATE teams SET verified=1 WHERE team_id=?", [$tid]);
        $ok = '团队已认证 ✅';
    }
    if ($action === 'unverify' && $is_admin) {
        Database::exec("UPDATE teams SET verified=0 WHERE team_id=?", [$tid]);
        $ok = '已取消认证';
    }
}

$members = Database::fetchAll("SELECT * FROM team_members WHERE team_id=? AND status='approved'", [$tid]);
$apps = Database::fetchAll("SELECT * FROM team_applications WHERE team_id=? AND status='pending'", [$tid]);
require 'inc/header.php';
?>
<div class="page-header"><h1>⚙️ 管理团队：<?=htmlspecialchars($team['name'])?></h1><a href="/team.php" class="btn btn-outline btn-sm">← 返回</a></div>

<?php if ($err??false): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($ok??false): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<?php if ($is_owner || $is_admin): ?>
<div class="card">
    <h2>✏️ 编辑团队信息</h2>
    <form method="post"><input type="hidden" name="action" value="edit">
        <div class="form-group"><label>团队名称</label><input type="text" name="name" class="form-input" value="<?=htmlspecialchars($team['name'])?>" required></div>
        <div class="form-group"><label>介绍</label><textarea name="description" class="form-input" style="min-height:80px"><?=htmlspecialchars($team['description']??'')?></textarea></div>
        <button type="submit" class="btn btn-primary">保存</button>
    </form>
</div>

<div class="card">
    <h2>👥 成员管理 (<?=count($members)?>)</h2>
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px">
    <?php foreach ($members as $m): ?>
    <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--bg-secondary);border-radius:var(--radius);font-size:13px">
        <span style="font-weight:600"><?=htmlspecialchars($m['user_id'])?></span>
        <span style="color:var(--text-muted);font-size:12px">(<?=$m['role']?>)</span>
        <?php if ($is_owner && $m['user_id'] !== $uid): ?>
        <form method="post" style="margin-left:auto;display:inline"><input type="hidden" name="action" value="kick"><input type="hidden" name="target" value="<?=$m['user_id']?>"><button type="submit" class="btn btn-danger btn-xs">移除</button></form>
        <?php if ($is_owner): ?><form method="post" style="display:inline"><input type="hidden" name="action" value="transfer"><input type="hidden" name="target" value="<?=$m['user_id']?>"><button type="submit" class="btn btn-outline btn-xs" onclick="return confirm('确定转让？')">转让</button></form><?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <form method="post" class="form-row"><input type="hidden" name="action" value="invite">
        <div class="form-group" style="flex:1"><input type="text" name="target" class="form-input" placeholder="用户名"></div>
        <button type="submit" class="btn btn-primary btn-sm">邀请</button>
    </form>
</div>

<?php if ($apps): ?>
<div class="card">
    <h2>📩 待处理的申请 (<?=count($apps)?>)</h2>
    <?php foreach ($apps as $a): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:8px 12px;background:var(--bg-secondary);border-radius:var(--radius);margin-bottom:6px">
        <span style="font-weight:600"><?=htmlspecialchars($a['user_id'])?></span>
        <span style="color:var(--text-muted);font-size:12px;flex:1"><?=htmlspecialchars($a['reason']?:'无留言')?></span>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="approve"><input type="hidden" name="target" value="<?=$a['user_id']?>"><button type="submit" class="btn btn-success btn-xs">通过</button></form>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="reject"><input type="hidden" name="target" value="<?=$a['user_id']?>"><button type="submit" class="btn btn-danger btn-xs">拒绝</button></form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($is_admin): ?>
<div class="card">
    <h2>🔑 管理员操作</h2>
    <form method="post" style="display:inline-block;margin-right:8px">
        <input type="hidden" name="action" value="<?=$team['verified']?'unverify':'verify'?>">
        <button type="submit" class="btn btn-<?=$team['verified']?'danger':'success'?> btn-sm"><?=$team['verified']?'取消认证':'认证团队 ✅'?></button>
    </form>
    <form method="post" style="display:inline-block">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('删除团队及其所有成员数据？')">删除团队</button>
    </form>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card"><p style="color:var(--text-muted)">只有团队所有者可以管理团队。</p></div>
<?php endif; ?>

<?php require 'inc/footer.php'; ?>

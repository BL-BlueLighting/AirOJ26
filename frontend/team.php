<?php
$title = '团队';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
$user = $_SESSION['airoj_user'] ?? null;
$err = ''; $ok = '';

// 创建团队
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$user) { header('Location: /login.php'); exit; }
    $uid = $user['user_id'];

    if ($_POST['action'] === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $err = '请填写团队名称';
        } elseif (Database::fetchOne("SELECT team_id FROM teams WHERE name=?", [$name])) {
            $err = '团队名称已存在';
        } else {
            $tid = Database::insert("INSERT INTO teams (name,description,owner) VALUES (?,?,?)", [$name, $desc, $uid]);
            Database::insert("INSERT INTO team_members (team_id,user_id,role,status) VALUES (?,?,'owner','approved')", [$tid, $uid]);
            $ok = "团队「{$name}」已创建，可进入管理页上传头像与申请认证。";
        }
    } elseif ($_POST['action'] === 'apply' && isset($_POST['team_id'])) {
        $tid = intval($_POST['team_id']);
        $reason = trim($_POST['reason'] ?? '');
        $existing = Database::fetchOne("SELECT * FROM team_members WHERE team_id=? AND user_id=?", [$tid, $uid]);
        $applied = Database::fetchOne("SELECT * FROM team_applications WHERE team_id=? AND user_id=? AND status='pending'", [$tid, $uid]);
        if (!$existing && !$applied) {
            Database::insert("INSERT INTO team_applications (team_id,user_id,reason) VALUES (?,?,?)", [$tid, $uid, $reason]);
            $ok = '申请已提交';
        } else $err = '你已经是成员或已有待处理的申请';
    }
}

$teams = Database::fetchAll("SELECT t.*,(SELECT COUNT(*) FROM team_members WHERE team_id=t.team_id) AS member_count FROM teams t ORDER BY t.verified DESC, t.team_id DESC");
$my_teams = $user ? Database::fetchAll("SELECT t.*,tm.role FROM teams t JOIN team_members tm ON t.team_id=tm.team_id WHERE tm.user_id=? AND tm.status='approved'", [$user['user_id']]) : [];

// 团队徽标：头像优先，否则用首字母
function team_badge(array $t, int $size = 38): string
{
    $name = htmlspecialchars($t['name'] ?? '');
    if (!empty($t['avatar'])) {
        return '<img src="/' . htmlspecialchars($t['avatar']) . '" alt="" style="width:' . $size . 'px;height:' . $size . 'px;border-radius:'.($size/4).'px;object-fit:cover;flex-shrink:0">';
    }
    $ch = mb_substr($t['name'] ?: '?', 0, 1);
    return '<span style="width:' . $size . 'px;height:' . $size . 'px;border-radius:'.($size/4).'px;display:inline-flex;align-items:center;justify-content:center;background:var(--accent-bg);color:var(--accent);font-weight:700;flex-shrink:0">' . htmlspecialchars($ch) . '</span>';
}
?>
<div class="page-header"><h1><i class="fa-solid fa-users"></i> 团队系统</h1></div>

<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<?php if ($user): ?>
<div class="card" style="margin-bottom:16px">
    <h2>我的团队</h2>
    <?php if ($my_teams): ?>
    <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($my_teams as $t): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--bg-secondary);border-radius:var(--radius)">
        <?= team_badge($t, 34) ?>
        <div style="flex:1;min-width:0">
            <strong><?=htmlspecialchars($t['name'])?></strong>
            <?php if ($t['verified']): ?><i class="fa-solid fa-circle-check" style="color:var(--green)" title="已认证"></i><?php endif; ?>
            <span style="color:var(--text-muted);font-size:12px">(<?=$t['role'] === 'owner' ? '所有者' : '成员'?>)</span>
        </div>
        <a href="team_manage.php?id=<?=$t['team_id']?>" class="btn btn-primary btn-sm">管理</a>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?><p style="color:var(--text-muted)">你还没有加入任何团队。</p><?php endif; ?>
    <div style="margin-top:12px"><button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('create-form').style.display='block'"><i class="fa-solid fa-plus"></i> 创建团队</button></div>
</div>

<div id="create-form" style="display:none" class="card" style="max-width:500px">
    <h2>创建团队</h2>
    <form method="post"><input type="hidden" name="action" value="create">
        <div class="form-group"><label>团队名称 *</label><input type="text" name="name" class="form-input" required></div>
        <div class="form-group"><label>短介绍</label><textarea name="description" class="form-input" style="min-height:80px" maxlength="300" placeholder="一句话介绍你的团队"></textarea></div>
        <button type="submit" class="btn btn-primary">创建</button>
    </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px;padding:0"><div class="table-wrap"><table>
<thead><tr><th style="width:52px"></th><th>团队</th><th style="width:70px">成员</th><th style="width:130px">创建者</th><th style="width:130px">操作</th></tr></thead>
<tbody>
<?php if (!$teams): ?><tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">暂无团队</td></tr>
<?php else: foreach ($teams as $t): ?>
<tr>
<td><?= team_badge($t, 36) ?></td>
<td>
    <strong><?=htmlspecialchars($t['name'])?></strong>
    <?php if ($t['verified']): ?><i class="fa-solid fa-circle-check" style="color:var(--green)" title="已认证"></i><?php endif; ?>
    <br><span style="font-size:12px;color:var(--text-muted)"><?=htmlspecialchars(mb_substr($t['description'] ?? '', 0, 90))?></span>
</td>
<td><?=$t['member_count']?></td>
<td><?=user_role_html($t['owner'])?></td>
<td>
<?php if ($user && !Database::fetchOne("SELECT * FROM team_members WHERE team_id=? AND user_id=?", [$t['team_id'], $user['user_id']])): ?>
<form method="post" style="display:inline"><input type="hidden" name="action" value="apply"><input type="hidden" name="team_id" value="<?=$t['team_id']?>">
<button type="submit" class="btn btn-primary btn-xs">申请加入</button></form>
<?php elseif ($user): ?>
<a href="team_manage.php?id=<?=$t['team_id']?>" class="btn btn-outline btn-xs">进入</a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>

<?php require 'inc/footer.php'; ?>

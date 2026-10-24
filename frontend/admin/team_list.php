<?php
$title = '团队管理';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$ok = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid = intval($_POST['team_id'] ?? 0);
    $act = $_POST['act'] ?? '';
    if ($tid) {
        if ($act === 'approve') {
            // 直接认证时若没有填写认证名称，则以团队名称作为认证名称
            $t = Database::fetchOne("SELECT name, verify_name FROM teams WHERE team_id=?", [$tid]);
            if ($t && trim((string)$t['verify_name']) === '') {
                Database::exec("UPDATE teams SET verify_name=? WHERE team_id=?", [$t['name'], $tid]);
            }
            Database::exec("UPDATE teams SET verified=1, verify_status='approved' WHERE team_id=?", [$tid]);
            $ok = '已通过认证';
        } elseif ($act === 'reject') {
            Database::exec("UPDATE teams SET verify_status='rejected' WHERE team_id=?", [$tid]);
            $ok = '已驳回认证申请';
        } elseif ($act === 'unverify') {
            Database::exec("UPDATE teams SET verified=0, verify_status='none' WHERE team_id=?", [$tid]);
            $ok = '已取消认证';
        }
    }
}

$teams = Database::fetchAll("SELECT t.*, (SELECT COUNT(*) FROM team_members WHERE team_id=t.team_id) AS member_count
                             FROM teams t ORDER BY (verify_status='pending') DESC, t.team_id DESC");
$admin_current_group = 'team';
$admin_current_item  = 'team_list';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header"><h1><i class="fa-solid fa-users"></i> 团队管理</h1></div>
<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr>
    <th style="width:50px">#</th><th>团队</th><th style="width:70px">成员</th><th style="width:130px">所有者</th>
    <th style="width:100px">认证状态</th><th style="width:150px">认证名称</th><th>申请理由</th><th style="width:220px">操作</th>
</tr></thead>
<tbody>
<?php if (!$teams): ?><tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">暂无团队</td></tr>
<?php else: foreach ($teams as $t):
    $status = $t['verify_status'] ?? 'none';
    $label = ['none' => '未申请', 'pending' => '审核中', 'approved' => '已认证', 'rejected' => '已驳回'][$status] ?? '未申请';
    $color = $status === 'pending' ? 'var(--accent-gold)' : ($t['verified'] ? 'var(--green)' : 'var(--text-muted)');
?>
<tr>
<td><?=$t['team_id']?></td>
<td>
    <strong><?=htmlspecialchars($t['name'])?></strong>
    <?php if ($t['verified']): ?><i class="fa-solid fa-circle-check" style="color:var(--green)"></i><?php endif; ?>
    <br><span style="font-size:12px;color:var(--text-muted)"><?=htmlspecialchars(mb_substr($t['description'] ?? '', 0, 60))?></span>
</td>
<td><?=$t['member_count']?></td>
<td><?=user_role_html($t['owner'])?></td>
<td style="color:<?=$color?>;font-weight:600"><?=$label?></td>
<td style="font-size:12px"><?=htmlspecialchars($t['verify_name'] ?? '') ?: '<span style="color:var(--text-muted)">—</span>'?></td>
<td style="font-size:12px;color:var(--text-secondary)"><?=htmlspecialchars(mb_substr($t['verify_reason'] ?? '', 0, 80))?></td>
<td>
    <a href="../team_manage.php?id=<?=$t['team_id']?>" class="btn btn-outline btn-xs" target="_blank">查看</a>
    <?php if ($status === 'pending'): ?>
    <form method="post" style="display:inline"><input type="hidden" name="team_id" value="<?=$t['team_id']?>"><input type="hidden" name="act" value="approve"><button class="btn btn-success btn-xs">通过</button></form>
    <form method="post" style="display:inline"><input type="hidden" name="team_id" value="<?=$t['team_id']?>"><input type="hidden" name="act" value="reject"><button class="btn btn-danger btn-xs">驳回</button></form>
    <?php elseif ($t['verified']): ?>
    <form method="post" style="display:inline"><input type="hidden" name="team_id" value="<?=$t['team_id']?>"><input type="hidden" name="act" value="unverify"><button class="btn btn-outline btn-xs">取消认证</button></form>
    <?php else: ?>
    <form method="post" style="display:inline"><input type="hidden" name="team_id" value="<?=$t['team_id']?>"><input type="hidden" name="act" value="approve"><button class="btn btn-outline btn-xs">直接认证</button></form>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

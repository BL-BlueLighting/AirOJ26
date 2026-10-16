<?php
$title = '比赛管理';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$contests = Database::fetchAll("SELECT * FROM contest ORDER BY contest_id DESC");
$counts = [];
foreach (Database::fetchAll("SELECT contest_id, COUNT(*) AS c FROM contest_problem GROUP BY contest_id") as $r) {
    $counts[(int)$r['contest_id']] = (int)$r['c'];
}
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header">
    <h1>🏁 比赛管理</h1>
    <a href="contest_edit.php?id=0" class="btn btn-primary btn-sm">➕ 添加比赛</a>
</div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th style="width:50px">#</th><th>名称</th><th style="width:70px">赛制</th><th style="width:60px">题目</th><th style="width:160px">开始</th><th style="width:160px">结束</th><th style="width:80px">状态</th><th style="width:200px">操作</th></tr></thead>
<tbody>
<?php if (!$contests): ?><tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">暂无比赛</td></tr>
<?php else: foreach ($contests as $c): $sts = contest_status($c); ?>
<tr>
<td><?=$c['contest_id']?></td>
<td><?=htmlspecialchars($c['title'])?><?= ($c['defunct'] === 'Y') ? ' <span class="tag tag-red">隐藏</span>' : '' ?></td>
<td><?= ((int)($c['contest_type'] ?? 0) === 1) ? 'OI' : 'ICPC' ?></td>
<td><?= $counts[(int)$c['contest_id']] ?? 0 ?></td>
<td><?=$c['start_time']?></td>
<td><?=$c['end_time']?></td>
<td style="color:<?=contest_status_color($sts)?>;font-weight:600"><?=$sts?></td>
<td>
    <a href="contest_edit.php?id=<?=$c['contest_id']?>" class="btn btn-primary btn-xs">编辑</a>
    <a href="../contest_detail.php?cid=<?=$c['contest_id']?>" class="btn btn-outline btn-xs" target="_blank">查看</a>
    <a href="contest_del.php?id=<?=$c['contest_id']?>" class="btn btn-danger btn-xs" onclick="return confirm('确定删除该比赛？')">删除</a>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../inc/footer.php'; ?>

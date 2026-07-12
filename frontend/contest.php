<?php
$title = '比赛列表';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';

$contests = Database::fetchAll("SELECT * FROM contest ORDER BY contest_id DESC LIMIT 50");
?>
<div class="page-header"><h1>🏁 比赛列表</h1></div>

<?php if ($contests): ?>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th>#</th><th>名称</th><th>开始时间</th><th>结束时间</th><th>状态</th><th>操作</th></tr></thead>
<tbody>
<?php foreach ($contests as $c):
$now = time();
$st = strtotime($c['start_time']);
$et = strtotime($c['end_time']);
if ($now < $st) $sts = '未开始';
elseif ($now > $et) $sts = '已结束';
else $sts = '进行中';
$cls = $sts === '进行中' ? 'var(--green)' : ($sts === '已结束' ? 'var(--text-muted)' : 'var(--accent)');
?>
<tr>
<td><?=$c['contest_id']?></td>
<td><a href="contest_rank.php?cid=<?=$c['contest_id']?>"><?=htmlspecialchars($c['title'])?></a></td>
<td><?=$c['start_time']?></td>
<td><?=$c['end_time']?></td>
<td style="font-weight:600;color:<?=$cls?>"><?=$sts?></td>
<td><a href="contest_rank.php?cid=<?=$c['contest_id']?>" class="btn btn-primary btn-xs">排名</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php else: ?>
<div class="empty"><p>暂无比赛</p></div>
<?php endif;

require 'inc/footer.php'; ?>

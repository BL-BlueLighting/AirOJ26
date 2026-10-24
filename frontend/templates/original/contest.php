<div class="page-header"><h1><i class="fa-solid fa-flag-checkered"></i> 比赛列表</h1></div>

<?php if ($contests): ?>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th style="width:60px">#</th><th>名称</th><th style="width:80px">赛制</th><th style="width:64px">题目</th><th style="width:170px">开始时间</th><th style="width:170px">结束时间</th><th style="width:90px">状态</th><th style="width:150px">操作</th></tr></thead>
<tbody>
<?php foreach ($contests as $c):
$sts = contest_status($c);
$cls = contest_status_color($sts);
?>
<tr>
<td><?=$c['contest_id']?></td>
<td><a href="contest_detail.php?cid=<?=$c['contest_id']?>"><?=htmlspecialchars($c['title'])?></a>
    <?php if (($c['private'] ?? '0') === '1'): ?><span class="tag tag-gold"><i class="fa-solid fa-lock"></i> 私有</span><?php endif; ?>
</td>
<td><?= ((int)($c['contest_type'] ?? 0) === 1) ? 'OI' : 'ICPC' ?></td>
<td><?= $counts[(int)$c['contest_id']] ?? 0 ?></td>
<td><?=$c['start_time']?></td>
<td><?=$c['end_time']?></td>
<td style="font-weight:600;color:<?=$cls?>"><?=$sts?></td>
<td>
    <a href="contest_detail.php?cid=<?=$c['contest_id']?>" class="btn btn-primary btn-xs">进入</a>
    <a href="contest_rank.php?cid=<?=$c['contest_id']?>" class="btn btn-outline btn-xs">排名</a>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php else: ?>
<div class="empty"><p>暂无比赛</p></div>
<?php endif;


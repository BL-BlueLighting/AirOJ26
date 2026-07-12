<?php
$title = '评测状态';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 30;
$total = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions")['c'] ?? 0;
$rows = Database::fetchAll("SELECT id,problem_id,judge_lang,status,score,passed_cases,total_cases FROM submissions ORDER BY id DESC LIMIT ? OFFSET ?", [$limit, ($page-1)*$limit]);
$maxP = max(1, ceil($total / $limit));
?>
<div class="page-header"><h1>📊 评测状态</h1></div>
<div class="card" style="padding:0">
<div class="table-wrap">
<table>
<thead><tr><th>#</th><th>题目</th><th>语言</th><th>状态</th><th>分数</th><th>通过</th><th>操作</th></tr></thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">暂无提交记录</td></tr>
<?php else: foreach ($rows as $r): $sc = $r['score']; ?>
<tr>
<td style="font-weight:600"><?= $r['id'] ?></td>
<td><a href="problem.php?id=<?= $r['problem_id'] ?>"><?= $r['problem_id'] ?></a></td>
<td><?= $r['judge_lang'] ?></td>
<td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
<td style="font-weight:600;color:<?= $sc>=100?'var(--green)':($sc!==null?'var(--red)':'var(--text-muted)') ?>"><?= $sc !== null ? $sc : '-' ?></td>
<td><?= $r['passed_cases'] ?> / <?= $r['total_cases'] ?></td>
<td><a href="result.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-xs">查看</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>
<?php if ($maxP > 1): ?>
<div class="pagination"><?php for ($i=1;$i<=$maxP;$i++): ?><a href="?page=<?=$i?>" class="page-link <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?></div>
<?php endif;
require 'inc/footer.php'; ?>

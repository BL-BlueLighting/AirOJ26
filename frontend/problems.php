<?php
$title = '题目列表';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/functions.php';

$problems = [];
if (is_dir(INPUTS_DIR)) {
    foreach (scandir(INPUTS_DIR) as $d) {
        if ($d === '.' || $d === '..' || !is_dir(INPUTS_DIR.'/'.$d)) continue;
        $f = scandir(INPUTS_DIR.'/'.$d);
        $in = count(array_filter($f, fn($x) => str_ends_with($x, '.in')));
        $out = count(array_filter($f, fn($x) => str_ends_with($x, '.out')));
        $problems[] = ['id' => $d, 'cases' => min($in, $out)];
    }
}
usort($problems, fn($a, $b) => (int)$a['id'] - (int)$b['id']);
?>
<div class="page-header"><h1>📋 题目列表</h1></div>
<div class="card" style="padding:0">
<div class="table-wrap">
<table>
<thead><tr><th style="width:60px">#</th><th>题目名称</th><th style="width:80px">测试点</th><th style="width:90px">操作</th></tr></thead>
<tbody>
<?php if (!$problems): ?>
<tr><td colspan="4" style="text-align:center;padding:32px;color:var(--text-muted)">暂无题目</td></tr>
<?php else: foreach ($problems as $p): ?>
<tr>
<td style="font-weight:700;color:var(--accent)"><?= $p['id'] ?></td>
<td><a href="problem.php?id=<?= $p['id'] ?>">Problem <?= $p['id'] ?></a></td>
<td><?= $p['cases'] ?></td>
<td><a href="problem.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-xs">提交</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>
<?php require 'inc/footer.php'; ?>

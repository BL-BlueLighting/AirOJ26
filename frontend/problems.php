<?php
$title = '题目列表';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/functions.php';

require_once 'inc/db.php';

$q     = trim($_GET['q'] ?? '');
$page  = max(1, intval($_GET['page'] ?? 1));
$limit = 50;

// 题库 = 数据库中的题目 ∪ inputs_outputs 里有测试数据的目录
$problems = [];
foreach (Database::fetchAll("SELECT problem_id, title, defunct FROM problem WHERE defunct<>'Y'") as $row) {
    $problems[(int)$row['problem_id']] = ['id' => (int)$row['problem_id'], 'title' => $row['title'] ?: ('Problem ' . $row['problem_id'])];
}
if (is_dir(INPUTS_DIR)) {
    foreach (scandir(INPUTS_DIR) as $d) {
        if ($d === '.' || $d === '..' || !is_dir(INPUTS_DIR . '/' . $d) || !ctype_digit($d)) continue;
        $id = (int)$d;
        if (!isset($problems[$id])) $problems[$id] = ['id' => $id, 'title' => 'Problem ' . $id];
    }
}
ksort($problems);

// 关键字搜索
if ($q !== '') {
    $problems = array_filter($problems, fn($p) => stripos((string)$p['id'], $q) !== false || stripos($p['title'], $q) !== false);
}

// 通过 / 提交统计
$stats = [];
foreach (Database::fetchAll("SELECT problem_id, COUNT(*) AS total, SUM(CASE WHEN status='done' AND score>=100 THEN 1 ELSE 0 END) AS ac FROM submissions GROUP BY problem_id") as $r) {
    $stats[(int)$r['problem_id']] = $r;
}

// 当前用户已通过的题目
$solved = [];
$uid = $_SESSION['airoj_user']['user_id'] ?? null;
if ($uid) {
    foreach (Database::fetchAll("SELECT DISTINCT problem_id FROM submissions WHERE user_id=? AND status='done' AND score>=100", [$uid]) as $r) {
        $solved[(int)$r['problem_id']] = true;
    }
}

$totalP = count($problems);
$maxP = max(1, ceil($totalP / $limit));
$items = array_slice(array_values($problems), ($page - 1) * $limit, $limit);
?>
<div class="page-header">
    <h1>📋 题目列表</h1>
    <form method="get" class="flex" style="margin:0">
        <input type="text" name="q" class="form-input" style="width:220px"
               value="<?=htmlspecialchars($q)?>" placeholder="搜索题号 / 题目标题">
        <button type="submit" class="btn btn-primary btn-sm">🔍 搜索</button>
        <?php if ($q !== ''): ?><a href="problems.php" class="btn btn-outline btn-sm">重置</a><?php endif; ?>
    </form>
</div>

<div class="card" style="padding:0">
<div class="table-wrap">
<table>
<thead><tr>
    <th style="width:70px">#</th><th>题目名称</th>
    <th style="width:90px">测试点</th><th style="width:120px">通过 / 提交</th>
    <th style="width:110px">我的状态</th><th style="width:90px">操作</th>
</tr></thead>
<tbody>
<?php if (!$items): ?>
<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)"><?= $q !== '' ? '没有匹配的题目' : '暂无题目' ?></td></tr>
<?php else: foreach ($items as $p):
    $id = (int)$p['id'];
    $st = $stats[$id] ?? null;
?>
<tr>
<td style="font-weight:700;color:var(--accent)"><?= $id ?></td>
<td><a href="problem.php?id=<?= $id ?>"><?= htmlspecialchars($p['title']) ?></a></td>
<td><?= count_test_cases((string)$id) ?></td>
<td>
    <span style="color:var(--green);font-weight:600"><?= (int)($st['ac'] ?? 0) ?></span>
    <span style="color:var(--text-muted)"> / <?= (int)($st['total'] ?? 0) ?></span>
</td>
<td><?= isset($solved[$id]) ? '<span class="tag tag-green">✅ 已通过</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
<td><a href="problem.php?id=<?= $id ?>" class="btn btn-primary btn-xs">做题</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>

<?php if ($maxP > 1): ?>
<div class="pagination"><?php for ($i = 1; $i <= $maxP; $i++): ?><a href="?<?= $q !== '' ? 'q=' . urlencode($q) . '&' : '' ?>page=<?=$i?>" class="page-link <?=$i === $page ? 'active' : ''?>"><?=$i?></a><?php endfor; ?></div>
<?php endif; ?>
<div style="text-align:center;color:var(--text-muted);font-size:12px">共 <?= $totalP ?> 道题目</div>

<?php require 'inc/footer.php'; ?>

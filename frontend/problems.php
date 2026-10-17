<?php
$title = '题目列表';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/functions.php';

require_once 'inc/db.php';

$q     = trim($_GET['q'] ?? '');
$page  = max(1, intval($_GET['page'] ?? 1));
$limit = 50;

// 全部题目记录（包含题目组的所有难度版本）
$allRows = Database::fetchAll("SELECT problem_id, title, tags, author, group_id, defunct, description FROM problem WHERE defunct<>'Y'");

// 搜索：题号 / 名称模糊 / 标签精确与模糊 / 内容模糊 / 出题人精确与模糊
$matchedIds = null;   // null = 不筛选
if ($q !== '') {
    $like  = '%' . $q . '%';
    $tagExact = [$q, $q . ',%', '%,' . $q, '%,' . $q . ',%'];
    $rows = Database::fetchAll(
        "SELECT problem_id FROM problem
         WHERE defunct<>'Y' AND (
              CAST(problem_id AS TEXT) = ?
           OR title LIKE ?
           OR description LIKE ?
           OR author = ?
           OR author LIKE ?
           OR tags = ? OR tags LIKE ? OR tags LIKE ? OR tags LIKE ?
         )",
        array_merge([$q, $like, $like, $q, $like], $tagExact)
    );
    $matchedIds = [];
    foreach ($rows as $r) $matchedIds[(int)$r['problem_id']] = true;
}

// 按题目组归类：group_id 为 0 的是独立题目，否则归到主记录
$byGroup = [];
foreach ($allRows as $r) {
    $key = (int)($r['group_id'] > 0 ? $r['group_id'] : $r['problem_id']);
    $byGroup[$key][] = $r;
}
// 目录里存在但数据库没有记录的题目
if (is_dir(INPUTS_DIR)) {
    foreach (scandir(INPUTS_DIR) as $d) {
        if ($d === '.' || $d === '..' || !is_dir(INPUTS_DIR . '/' . $d) || !ctype_digit($d)) continue;
        $id = (int)$d;
        if (!isset($byGroup[$id])) {
            $byGroup[$id] = [['problem_id' => $id, 'title' => 'Problem ' . $id, 'tags' => '', 'author' => '', 'group_id' => 0, 'defunct' => 'N']];
        }
    }
}

// 过滤（搜索命中组内任一版本即保留整个组）
if ($matchedIds !== null) {
    foreach (array_keys($byGroup) as $key) {
        $hit = isset($matchedIds[$key]);
        if (!$hit) {
            foreach ($byGroup[$key] as $m) {
                if (isset($matchedIds[(int)$m['problem_id']])) { $hit = true; break; }
            }
        }
        if (!$hit) unset($byGroup[$key]);
    }
}
ksort($byGroup);

// 通过 / 提交统计（按题目记录）
$stats = [];
foreach (Database::fetchAll("SELECT problem_id, COUNT(*) AS total, SUM(CASE WHEN status='done' AND score>=100 THEN 1 ELSE 0 END) AS ac FROM submissions GROUP BY problem_id") as $r) {
    $stats[(int)$r['problem_id']] = $r;
}

// 难度（按题目记录）
$diffsByPid = [];
foreach (Database::fetchAll("SELECT problem_id, category, level, position FROM problem_difficulty ORDER BY level DESC") as $r) {
    $diffsByPid[(int)$r['problem_id']][] = $r;
}

// 当前用户已通过
$solved = [];
$uid = $_SESSION['airoj_user']['user_id'] ?? null;
if ($uid) {
    foreach (Database::fetchAll("SELECT DISTINCT problem_id FROM submissions WHERE user_id=? AND status='done' AND score>=100", [$uid]) as $r) {
        $solved[(int)$r['problem_id']] = true;
    }
}

$diffClass = ['BASIC' => 'basic', 'ADVANCED' => 'advanced', 'EXPERT' => 'expert', 'MASTER' => 'master', 'Re:MASTER' => 'remaster'];

// 组装列表项
$items = [];
foreach ($byGroup as $key => $members) {
    $main = $members[0];
    foreach ($members as $m) if ((int)$m['problem_id'] === $key) { $main = $m; break; }

    // 组的全部难度
    $gDiffs = [];
    $isSolved = false;
    $acTotal = 0; $allTotal = 0; $caseTotal = 0;
    foreach ($members as $m) {
        $mid = (int)$m['problem_id'];
        foreach ($diffsByPid[$mid] ?? [] as $d) $gDiffs[] = $d;
        if (isset($solved[$mid])) $isSolved = true;
        $st = $stats[$mid] ?? null;
        $acTotal += (int)($st['ac'] ?? 0);
        $allTotal += (int)($st['total'] ?? 0);
        $caseTotal += count_test_cases((string)$mid);
    }
    usort($gDiffs, fn($a, $b) => (int)$b['level'] <=> (int)$a['level']);

    $items[$key] = [
        'id'       => $key,
        'title'    => $main['title'] ?: ('Problem ' . $key),
        'tags'     => (string)($main['tags'] ?? ''),
        'author'   => (string)($main['author'] ?? ''),
        'members'  => count($members),
        'diffs'    => $gDiffs,
        'ac'       => $acTotal,
        'total'    => $allTotal,
        'cases'    => $caseTotal,
        'solved'   => $isSolved,
    ];
}

$totalP = count($items);
$maxP = max(1, ceil($totalP / $limit));
$pageItems = array_slice(array_values($items), ($page - 1) * $limit, $limit);
?>
<div class="page-header">
    <h1><i class="fa-solid fa-clipboard-list"></i> 题目列表</h1>
    <form method="get" class="flex" style="margin:0">
        <input type="text" name="q" class="form-input" style="width:280px"
               value="<?=htmlspecialchars($q)?>" placeholder="题号 / 名称 / 标签 / 内容 / 出题人">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> 搜索</button>
        <?php if ($q !== ''): ?><a href="problems.php" class="btn btn-outline btn-sm">重置</a><?php endif; ?>
    </form>
</div>

<div class="card" style="padding:0">
<div class="table-wrap">
<table>
<thead><tr>
    <th style="width:70px">#</th><th>题目名称</th><th style="width:190px">难度</th><th style="width:190px">标签</th>
    <th style="width:120px">出题人</th>
    <th style="width:90px">测试点</th><th style="width:120px">通过 / 提交</th>
    <th style="width:110px">我的状态</th><th style="width:90px">操作</th>
</tr></thead>
<tbody>
<?php if (!$pageItems): ?>
<tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text-muted)"><?= $q !== '' ? '没有匹配的题目' : '暂无题目' ?></td></tr>
<?php else: foreach ($pageItems as $p):
    $tags = $p['tags'] !== '' ? explode(',', $p['tags']) : [];
?>
<tr>
<td style="font-weight:700;color:var(--accent)"><?= $p['id'] ?></td>
<td>
    <a href="problem.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a>
    <?php if ($p['members'] > 1): ?><span class="tag tag-gold"><?= $p['members'] ?> 个难度版本</span><?php endif; ?>
</td>
<td>
    <?php foreach ($p['diffs'] as $d): ?>
    <span class="diff-tag diff-<?= $diffClass[$d['category']] ?? 'basic' ?>"><?= htmlspecialchars(difficulty_label($d['category'], (int)$d['level'], $d['position'])) ?></span>
    <?php endforeach; ?>
</td>
<td>
    <?php foreach ($tags as $tag): ?>
    <a href="problems.php?q=<?= urlencode($tag) ?>" class="tag tag-blue"><?= htmlspecialchars($tag) ?></a>
    <?php endforeach; ?>
</td>
<td><?= $p['author'] !== '' ? user_role_html($p['author']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
<td><?= (int)$p['cases'] ?></td>
<td>
    <span style="color:var(--green);font-weight:600"><?= (int)$p['ac'] ?></span>
    <span style="color:var(--text-muted)"> / <?= (int)$p['total'] ?></span>
</td>
<td><?= $p['solved'] ? '<span class="tag tag-green"><i class="fa-solid fa-circle-check"></i> 已通过</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
<td><a href="problem.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-xs">做题</a></td>
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

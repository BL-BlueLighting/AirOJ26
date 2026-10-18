<?php
/**
 * AirOJ — 章节详情
 */
$title = '章节';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';

$id = intval($_GET['id'] ?? 0);
$chapter = Database::fetchOne("SELECT * FROM chapters WHERE chapter_id=?", [$id]);

// 章节设置了背景时，整页背景也使用它（而不是回落到站点背景）
$chapter_bg = trim((string)($chapter['background'] ?? ''));
if ($chapter_bg !== '' && is_valid_background($chapter_bg)) {
    $page_background_override = $chapter_bg;
}

require 'inc/header.php';
require_once 'inc/Parsedown.php';

if (!$chapter) {
    echo '<div class="empty"><div class="icon"><i class="fa-solid fa-book-open"></i></div><p>章节不存在</p></div>';
    require 'inc/footer.php';
    exit;
}

$problems = Database::fetchAll(
    "SELECT p.*, cp.position FROM chapter_problem cp
     LEFT JOIN problem p ON p.problem_id = cp.problem_id
     WHERE cp.chapter_id=? ORDER BY cp.position ASC, cp.problem_id ASC",
    [$id]
);

$pd = new Parsedown();
$pd->setSafeMode(true);
$cover = background_css((string)($chapter['background'] ?? ''));

// 统计与个人通过状态
$stats = [];
if ($problems) {
    $ids = array_map(fn($p) => (string)$p['problem_id'], $problems);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    foreach (Database::fetchAll("SELECT problem_id, COUNT(*) AS total, SUM(CASE WHEN status='done' AND score>=100 THEN 1 ELSE 0 END) AS ac FROM submissions WHERE problem_id IN ($ph) GROUP BY problem_id", $ids) as $r) {
        $stats[(int)$r['problem_id']] = $r;
    }
}
$solved = [];
$uid = $_SESSION['airoj_user']['user_id'] ?? null;
if ($uid && $problems) {
    foreach (Database::fetchAll("SELECT DISTINCT problem_id FROM submissions WHERE user_id=? AND status='done' AND score>=100", [$uid]) as $r) {
        $solved[(int)$r['problem_id']] = true;
    }
}
$diffClass = ['BASIC' => 'basic', 'ADVANCED' => 'advanced', 'EXPERT' => 'expert', 'MASTER' => 'master', 'Re:MASTER' => 'remaster'];
?>
<div class="chapter-hero<?= $cover === '' ? ' chapter-hero-empty' : '' ?>" style="<?= htmlspecialchars($cover, ENT_COMPAT) ?>">
    <div class="chapter-hero-inner">
        <h1><?= htmlspecialchars($chapter['title']) ?></h1>
        <div class="chapter-hero-meta">
            <span><i class="fa-solid fa-user"></i> 创建人：<?= user_role_html($chapter['creator']) ?></span>
            <span><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($chapter['created_at']) ?></span>
            <span><i class="fa-solid fa-list-check"></i> <?= count($problems) ?> 道题</span>
        </div>
        <?php if (trim((string)$chapter['description']) !== ''): ?>
        <div class="chapter-hero-desc mdui-prose"><?= $pd->text((string)$chapter['description']) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="padding:0">
    <div style="padding:16px 20px 8px;font-weight:700;font-size:14px"><i class="fa-solid fa-list-check"></i> 题目列表</div>
    <?php if (!$problems): ?>
    <div class="empty"><p>本章节还没有添加题目</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr>
            <th style="width:60px">#</th><th>题目</th><th style="width:190px">难度</th>
            <th style="width:120px">通过 / 提交</th><th style="width:110px">我的状态</th><th style="width:90px">操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($problems as $i => $p):
            $pid = (int)$p['problem_id'];
            $st = $stats[$pid] ?? null;
            $diffs = problem_difficulties((string)$pid);
        ?>
        <tr>
            <td style="font-weight:700;color:var(--accent)"><?= $i + 1 ?></td>
            <td>
                <?php if ($p['title'] === null): ?>
                <span style="color:var(--text-muted)">题目 #<?= $pid ?>（已删除）</span>
                <?php else: ?>
                <a href="/problem.php?id=<?= $pid ?>"><?= htmlspecialchars($p['title']) ?></a>
                <?php endif; ?>
            </td>
            <td>
                <?php foreach ($diffs as $d): ?>
                <span class="diff-tag diff-<?= $diffClass[$d['category']] ?? 'basic' ?>"><?= htmlspecialchars(difficulty_label($d['category'], (int)$d['level'], $d['position'])) ?></span>
                <?php endforeach; ?>
            </td>
            <td>
                <span style="color:var(--green);font-weight:600"><?= (int)($st['ac'] ?? 0) ?></span>
                <span style="color:var(--text-muted)"> / <?= (int)($st['total'] ?? 0) ?></span>
            </td>
            <td><?= isset($solved[$pid]) ? '<span class="tag tag-green"><i class="fa-solid fa-circle-check"></i> 已通过</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
            <td><a href="/problem.php?id=<?= $pid ?>" class="btn btn-primary btn-xs">做题</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php require 'inc/footer.php'; ?>

<?php
$title = '比赛详情';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';

$cid = intval($_GET['cid'] ?? 0);
$contest = Database::fetchOne("SELECT * FROM contest WHERE contest_id=?", [$cid]);
if (!$contest) {
    echo '<div class="empty"><div class="icon">🏁</div><p>比赛不存在</p></div>';
    require 'inc/footer.php';
    exit;
}

$status = contest_status($contest);
$problems = contest_problem_list($cid);
$uid = $_SESSION['airoj_user']['user_id'] ?? null;

// 我在本场比赛中的每题最好成绩
$my = [];
if ($uid && $problems) {
    foreach (Database::fetchAll(
        "SELECT problem_id, MAX(score) AS best, MAX(CASE WHEN status='done' AND score>=100 THEN 1 ELSE 0 END) AS ac
         FROM submissions WHERE contest_id=? AND user_id=? GROUP BY problem_id", [$cid, $uid]) as $r) {
        $my[(int)$r['problem_id']] = $r;
    }
}

// 每题总体通过 / 提交数
$tries = [];
foreach (Database::fetchAll(
    "SELECT problem_id, COUNT(*) AS total, SUM(CASE WHEN status='done' AND score>=100 THEN 1 ELSE 0 END) AS ac
     FROM submissions WHERE contest_id=? GROUP BY problem_id", [$cid]) as $r) {
    $tries[(int)$r['problem_id']] = $r;
}
?>
<style>
.contest-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 20px; }
.contest-tabs a { padding: 10px 20px; font-size: 14px; font-weight: 600; color: var(--text-secondary); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.contest-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
</style>

<div class="page-header">
    <h1>🏁 <?= htmlspecialchars($contest['title']) ?></h1>
    <div>
        <a href="contest.php" class="btn btn-outline btn-sm">← 比赛列表</a>
        <a href="contest_rank.php?cid=<?=$cid?>" class="btn btn-primary btn-sm">🏆 排名</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">状态</div><div class="stat-value" style="font-size:20px;color:<?=contest_status_color($status)?>"><?=$status?></div></div>
    <div class="stat-card"><div class="stat-label">赛制</div><div class="stat-value" style="font-size:20px"><?= ((int)($contest['contest_type'] ?? 0) === 1) ? 'OI' : 'ICPC' ?></div></div>
    <div class="stat-card"><div class="stat-label">开始时间</div><div class="stat-value" style="font-size:14px"><?=$contest['start_time']?></div></div>
    <div class="stat-card"><div class="stat-label">结束时间</div><div class="stat-value" style="font-size:14px"><?=$contest['end_time']?></div></div>
    <div class="stat-card"><div class="stat-label">题目数</div><div class="stat-value"><?=count($problems)?></div></div>
</div>

<?php if (!empty($contest['description'])): ?>
<div class="card">
    <h2>📄 比赛说明</h2>
    <div style="font-size:14px;color:var(--text-secondary);line-height:1.7"><?php
        require_once 'inc/Parsedown.php';
        $pd = new Parsedown();
        $pd->setSafeMode(true);
        echo $pd->text($contest['description']);
    ?></div>
</div>
<?php endif; ?>

<div class="card" style="padding:0">
    <div style="padding:16px 20px 8px;font-weight:700;font-size:14px">📋 题目列表</div>
    <?php if ($status === '未开始'): ?>
        <div class="empty"><div class="icon">⏳</div><p>比赛尚未开始，题目将在 <?=htmlspecialchars($contest['start_time'])?> 公布</p></div>
    <?php elseif (!$problems): ?>
        <div class="empty"><p>本场比赛还没有添加题目</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
    <thead><tr><th style="width:60px">题号</th><th>题目</th><th style="width:110px">通过 / 提交</th><th style="width:100px">我的状态</th><th style="width:100px">操作</th></tr></thead>
    <tbody>
    <?php foreach ($problems as $i => $p):
        $pid = (int)$p['problem_id'];
        $letter = chr(65 + ($i % 26));
        $t = $tries[$pid] ?? null;
        $m = $my[$pid] ?? null;
    ?>
    <tr>
        <td style="font-weight:700;color:var(--accent)"><?=$letter?></td>
        <td><a href="problem.php?id=<?=$pid?>&cid=<?=$cid?>"><?=htmlspecialchars($p['title'] ?? ('Problem ' . $pid))?></a></td>
        <td><?= (int)($t['ac'] ?? 0) ?> / <?= (int)($t['total'] ?? 0) ?></td>
        <td>
            <?php if ($m && (int)$m['ac'] === 1): ?><span class="tag tag-green">✅ 已通过</span>
            <?php elseif ($m): ?><span class="tag tag-gold">得分 <?= (float)$m['best'] ?></span>
            <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
        </td>
        <td><a href="problem.php?id=<?=$pid?>&cid=<?=$cid?>" class="btn btn-primary btn-xs">去做题</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>

<?php require 'inc/footer.php'; ?>

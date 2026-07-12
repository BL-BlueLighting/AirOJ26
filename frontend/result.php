<?php
$title = '评测结果';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';

$id = intval($_GET['id'] ?? 0);
$row = Database::fetchOne("SELECT * FROM submissions WHERE id=?", [$id]);
if (!$row) { echo '<div class="empty"><div class="icon">🔍</div><p>提交记录不存在</p></div>'; require 'inc/footer.php'; exit; }

// 自动更新 solved 计数
if ($row['status'] === 'done' && ($row['score'] ?? 0) >= 100) {
    $cases_tmp = json_decode($row['result_json'] ?: '[]', true);
    $all_ac = true;
    foreach ($cases_tmp as $c) { if (($c['verdict'] ?? '') !== 'AC') { $all_ac = false; break; } }
    if ($all_ac && $row['user_id'] ?? null) {
        $user = Database::fetchOne("SELECT solved_count FROM users WHERE user_id=?", [$row['user_id']]);
        if ($user) {
            $pid = $row['problem_id'];
            $already = Database::fetchOne("SELECT id FROM submissions WHERE problem_id=? AND status='done' AND score>=100 AND user_id=? LIMIT 1", [$pid, $row['user_id']]);
            if ($already) { /* already counted */ }
            else { Database::exec("UPDATE users SET solved_count = solved_count + 1, solved = solved + 1 WHERE user_id=?", [$row['user_id']]); }
        }
    }
}

$cases = json_decode($row['result_json'] ?: '[]', true);
$sc = $row['score'];
$scCls = $sc >= 100 ? 'score-good' : ($sc >= 50 ? 'score-mid' : ($sc !== null ? 'score-bad' : 'score-na'));
?>
<div class="page-header"><h1>📄 提交 #<?= $id ?> 详情</h1></div>

<div class="card">
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div class="score-big <?= $scCls ?>"><?= $sc !== null ? $sc : '-' ?></div>
        <div>
            <div style="font-size:16px;font-weight:600">题目 <?= htmlspecialchars($row['problem_id']) ?></div>
            <div style="color:var(--text-secondary);font-size:13px"><?= $row['judge_lang'] ?> · <?= $row['judge_type'] ?></div>
        </div>
        <div style="margin-left:auto"><span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] ?></span></div>
    </div>
</div>

<div class="result-meta">
    <div class="rm-item"><div class="rm-label">状态</div><div class="rm-value"><?= $row['status'] ?></div></div>
    <div class="rm-item"><div class="rm-label">分数</div><div class="rm-value"><?= $sc !== null ? $sc : '-' ?></div></div>
    <div class="rm-item"><div class="rm-label">通过</div><div class="rm-value"><?= $row['passed_cases'] ?> / <?= $row['total_cases'] ?></div></div>
</div>

<div class="card">
    <h2>测试点详情</h2>
    <?php if ($cases): ?>
    <div class="case-list"><?php foreach ($cases as $c): ?>
    <div class="case-item">
        <span class="badge badge-<?= strtolower($c['verdict'] ?? 'uk') ?>"><?= $c['verdict'] ?? '?' ?></span>
        <span class="case-name">测试点 #<?= htmlspecialchars($c['name']) ?></span>
    </div><?php endforeach; ?></div>
    <?php elseif ($row['status']==='pending'||$row['status']==='judging'): ?>
    <div class="empty"><p>⏳ 评测进行中...</p><p style="margin-top:8px"><a href="?id=<?=$id?>" class="btn btn-primary btn-sm">⟳ 刷新</a></p></div>
    <?php elseif ($row['status']==='failed'): ?>
    <div class="empty"><p>⚠ 评测失败</p>
    <div class="code-block" style="text-align:left;margin-top:8px;max-height:300px;font-size:12px;color:var(--red)"><?= htmlspecialchars($row['result_json'] ?? '') ?></div>
    </div>
    <?php else: ?><div class="empty"><p>暂无详情</p></div><?php endif; ?>
</div>

<?php require 'inc/footer.php'; ?>

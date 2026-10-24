<?php
$title = '评测结果';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';

$id = intval($_GET['id'] ?? 0);
$row = Database::fetchOne("SELECT * FROM submissions WHERE id=?", [$id]);
if (!$row) {
    echo '<div class="empty"><div class="icon"><i class="fa-solid fa-magnifying-glass"></i></div><p>提交记录不存在</p></div>';
    require 'inc/footer.php';
    exit;
}

$cases = json_decode($row['result_json'] ?: '[]', true);
if (!is_array($cases)) $cases = [];

$problem = Database::fetchOne("SELECT problem_id, title FROM problem WHERE problem_id=?", [$row['problem_id']]);
$diffs = problem_difficulties((string)$row['problem_id']);
$diffClass = ['BASIC' => 'basic', 'ADVANCED' => 'advanced', 'EXPERT' => 'expert', 'MASTER' => 'master', 'Re:MASTER' => 'remaster'];

$total  = (int)$row['total_cases'];
$passed = (int)$row['passed_cases'];
if ($total <= 0 && $cases) {
    $total  = count($cases);
    $passed = count(array_filter($cases, fn($c) => strtoupper((string)($c['verdict'] ?? '')) === 'AC'));
}
$acc   = $total > 0 ? round($passed / $total * 100, 1) : 0.0;
$allAc = $total > 0 && $passed >= $total;

// 整体评测状态
$verdicts = array_map(fn($c) => strtoupper((string)($c['verdict'] ?? '')), $cases);
$has = fn(string $v) => in_array($v, $verdicts, true);
if ($row['status'] === 'failed') {
    $verdict = 'System Error'; $verdictCn = '评测失败'; $verdictClass = 'se';
} elseif ($row['status'] !== 'done') {
    $verdict = 'Judging'; $verdictCn = '评测中'; $verdictClass = 'judging';
} elseif ($allAc || (float)($row['score'] ?? 0) >= 100) {
    $verdict = 'Accepted'; $verdictCn = '答案正确'; $verdictClass = 'ac';
} elseif ($has('TLE')) {
    $verdict = 'Time Limit Exceeded'; $verdictCn = '运行超时'; $verdictClass = 'tle';
} elseif ($has('MLE')) {
    $verdict = 'Memory Limit Exceeded'; $verdictCn = '内存超限'; $verdictClass = 'mle';
} elseif ($has('RE')) {
    $verdict = 'Runtime Error'; $verdictCn = '运行时错误'; $verdictClass = 're';
} elseif ($has('SE')) {
    $verdict = 'System Error'; $verdictCn = '系统错误'; $verdictClass = 'se';
} else {
    $verdict = 'Wrong Answer'; $verdictCn = '答案错误'; $verdictClass = 'wa';
}

$score = $row['score'] !== null ? (float)$row['score'] : 0.0;
$caseScore = $total > 0 ? 100 / $total : 0;

$ratingRow = Database::fetchOne("SELECT rating FROM users WHERE user_id=?", [$row['user_id']]);
$rating = (int)round($ratingRow['rating'] ?? 0);
$tier = rating_tier($rating);
$ratingColor = AIR_OJ_RATING_TIERS[$tier]['color'] ?? '#1677ff';

$viewer = $_SESSION['airoj_user'] ?? null;
$sourceAllowed = $viewer && (
    (string)$viewer['user_id'] === (string)$row['user_id']
    || user_can_view_all_sources($viewer)
);
?>
<div class="lg-record">
    <div class="lg-breadcrumb">
        <a href="/index.php">首页</a> / <a href="/status.php">评测状态</a> / <span>#<?= $id ?></span>
    </div>

    <div class="lg-card">
        <div class="lg-head">
            <div class="lg-verdict lg-<?= $verdictClass ?>">
                <i class="fa-solid <?= $verdictClass === 'ac' ? 'fa-circle-check' : ($verdictClass === 'judging' ? 'fa-hourglass-half' : 'fa-circle-xmark') ?>"></i>
                <div>
                    <h1><?= htmlspecialchars($verdict) ?></h1>
                    <p><?= htmlspecialchars($verdictCn) ?></p>
                </div>
            </div>
            <div class="lg-score"><b><?= htmlspecialchars(rtrim(rtrim(number_format($score, 1), '0'), '.')) ?></b><span>分</span></div>
        </div>

        <div class="lg-meta-grid">
            <div class="lg-meta-item">
                <span>题目</span>
                <a href="/problem.php?id=<?= urlencode($row['problem_id']) ?>"><?= htmlspecialchars($problem['title'] ?? ('#' . $row['problem_id'])) ?></a>
                <?php foreach ($diffs as $d): ?>
                <span class="diff-tag diff-<?= $diffClass[$d['category']] ?? 'basic' ?>"><?= htmlspecialchars(difficulty_label($d['category'], (int)$d['level'], $d['position'])) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="lg-meta-item"><span>提交者</span><?= user_role_html($row['user_id']) ?></div>
            <div class="lg-meta-item"><span>语言</span><b><?= htmlspecialchars($row['judge_lang']) ?></b></div>
            <div class="lg-meta-item"><span>提交时间</span><b><?= htmlspecialchars($row['created_at'] ?? '-') ?></b></div>
            <div class="lg-meta-item"><span>代码长度</span><b><?= number_format(mb_strlen((string)$row['code'])) ?> B</b></div>
            <div class="lg-meta-item"><span>测试点</span><b><?= $passed ?> / <?= $total ?></b></div>
            <div class="lg-meta-item"><span>正确率</span><b><?= number_format($acc, 1) ?>%</b></div>
            <div class="lg-meta-item"><span>Rating</span><b style="color:<?= $ratingColor ?>"><?= $rating ?></b></div>
        </div>
    </div>

    <div class="lg-card">
        <div class="lg-section-head"><i class="fa-solid fa-list-check"></i> 测试点 <span>共 <?= $total ?> 个</span></div>
        <?php if ($row['status'] === 'failed' && trim((string)$row['result_json']) !== ''): ?>
        <div class="lg-error"><?= htmlspecialchars((string)$row['result_json']) ?></div>
        <?php elseif ($cases): ?>
        <div class="table-wrap">
            <table class="lg-table">
                <thead><tr><th style="width:70px">#</th><th>测试点</th><th style="width:180px">状态</th><th style="width:110px">分数</th></tr></thead>
                <tbody>
                <?php foreach ($cases as $i => $c):
                    $v = strtoupper((string)($c['verdict'] ?? 'UK'));
                    $vc = strtolower($v);
                    $vName = ['AC' => 'Accepted', 'WA' => 'Wrong Answer', 'TLE' => 'Time Limit Exceeded', 'MLE' => 'Memory Limit Exceeded', 'RE' => 'Runtime Error', 'SE' => 'System Error'][$v] ?? $v;
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>测试点 #<?= htmlspecialchars((string)($c['name'] ?? ($i + 1))) ?></td>
                    <td><span class="lg-case-status lg-case-<?= $vc ?>"><i class="fa-solid <?= $v === 'AC' ? 'fa-check' : 'fa-xmark' ?>"></i> <?= htmlspecialchars($vName) ?></span></td>
                    <td><?= $v === 'AC' ? htmlspecialchars(rtrim(rtrim(number_format($caseScore, 1), '0'), '.')) : '0' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="lg-empty"><?= $row['status'] === 'pending' || $row['status'] === 'judging' ? '评测进行中…' : '暂无测试点详情' ?></div>
        <?php endif; ?>
    </div>

    <div class="lg-actions">
        <?php if ($sourceAllowed): ?><a href="/source.php?id=<?= $id ?>" class="lg-btn lg-btn-default"><i class="fa-solid fa-code"></i> 查看源代码</a><?php endif; ?>
        <a href="/status.php" class="lg-btn lg-btn-default"><i class="fa-solid fa-list"></i> 评测状态</a>
        <a href="/problem.php?id=<?= urlencode($row['problem_id']) ?>" class="lg-btn lg-btn-primary"><i class="fa-solid fa-rotate-right"></i> 再试一次</a>
    </div>
</div>
<?php require 'inc/footer.php'; ?>

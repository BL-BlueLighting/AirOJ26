<?php
$title = '评测结果';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';

$id = intval($_GET['id'] ?? 0);
$row = Database::fetchOne("SELECT * FROM submissions WHERE id=?", [$id]);
if (!$row) {
    render_error('提交记录不存在');
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

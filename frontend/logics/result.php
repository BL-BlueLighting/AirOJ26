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

$problem = Database::fetchOne("SELECT problem_id, title, author, tags FROM problem WHERE problem_id=?", [$row['problem_id']]);
$diffs = problem_difficulties((string)$row['problem_id']);

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

$userRow = Database::fetchOne(
    "SELECT user_id,nick,email,avatar,rating,solved_count,submit_count FROM users WHERE user_id=?",
    [$row['user_id']]
);
$rating = (int)round($userRow['rating'] ?? 0);
$tier = rating_tier($rating);
$tierInfo = AIR_OJ_RATING_TIERS[$tier] ?? AIR_OJ_RATING_TIERS['green'];
$ratingColor = $tierInfo['color'] ?? '#1677ff';
$tierLabel = $tierInfo['label'] ?? $tier;
$tierMin = (int)($tierInfo['min'] ?? 0);
$tierMax = (int)($tierInfo['max'] ?? ($tierMin + 1));
$tierProgress = $tierMax > $tierMin
    ? min(100, max(0, ($rating - $tierMin) / ($tierMax - $tierMin) * 100))
    : 100;
$avatarUrl = $userRow ? user_avatar_url($userRow, 180) : '';

$quality = 0.0;
$difficultyMain = '未分级';
foreach ($diffs as $d) {
    $q = difficulty_quality((string)$d['category'], (int)$d['level'], (string)$d['position']);
    if ($q > $quality) {
        $quality = $q;
        $difficultyMain = difficulty_label((string)$d['category'], (int)$d['level'], (string)$d['position']);
    }
}
$qualityBar = min(100, max(0, $quality / 20000 * 100));

// 等级结算：S/A/B/C/D/F
if ($row['status'] === 'failed') {
    $grade = 'F'; $gradeClass = 'f';
} elseif ($row['status'] !== 'done') {
    $grade = '…'; $gradeClass = 'judging';
} elseif ($score >= 100) {
    $grade = 'S'; $gradeClass = 's';
} elseif ($score >= 90) {
    $grade = 'A'; $gradeClass = 'a';
} elseif ($score >= 80) {
    $grade = 'B'; $gradeClass = 'b';
} elseif ($score >= 60) {
    $grade = 'C'; $gradeClass = 'c';
} elseif ($score > 0) {
    $grade = 'D'; $gradeClass = 'd';
} else {
    $grade = 'F'; $gradeClass = 'f';
}

$judgeOrder = ['AC', 'WA', 'TLE', 'MLE', 'RE', 'SE', 'UK'];
$judgeNames = [
    'AC' => 'Accepted', 'WA' => 'Wrong Answer', 'TLE' => 'Time Limit',
    'MLE' => 'Memory Limit', 'RE' => 'Runtime Error', 'SE' => 'System Error', 'UK' => 'Unknown',
];
$judgeCounts = array_fill_keys($judgeOrder, 0);
foreach ($cases as $case) {
    $v = strtoupper((string)($case['verdict'] ?? 'UK'));
    if (!isset($judgeCounts[$v])) $v = 'UK';
    $judgeCounts[$v]++;
}
$judgeMax = max(1, max($judgeCounts));

$scoreDisplay = rtrim(rtrim(number_format($score, 1), '0'), '.');
$accDisplay = number_format($acc, 1);
$idDisplay = str_pad((string)$id, 7, '0', STR_PAD_LEFT);

// 结算页主视觉：优先使用站点背景，角色图叠加在右侧
$heroBg = resolve_background($_SESSION['airoj_user'] ?? null);
$heroStyle = 'background:linear-gradient(120deg,#2b2118,#17110e 58%,#241a14);';
if (is_valid_background($heroBg)) {
    if (preg_match('~^(#|rgb|hsl|linear-gradient|radial-gradient)~i', $heroBg)) {
        $heroStyle = 'background:' . $heroBg . ';';
    } else {
        $heroUrl = preg_match('~^(https?://|data:image/)~i', $heroBg)
            ? $heroBg
            : '/' . ltrim($heroBg, '/');
        $heroUrl = addcslashes($heroUrl, "\\'");
        $heroStyle = "background-image:linear-gradient(90deg,rgba(18,12,8,.86),rgba(18,12,8,.34) 55%,rgba(18,12,8,.88)),url('{$heroUrl}');background-size:cover;background-position:center;";
    }
}
$rc = Database::getConfig('result_character');
$rcUrl = '';
if ($rc !== '') {
    $rcUrl = preg_match('~^(https?://|data:image/)~i', $rc) ? $rc : '/' . ltrim($rc, '/');
}

$viewer = $_SESSION['airoj_user'] ?? null;
$sourceAllowed = $viewer && (
    (string)$viewer['user_id'] === (string)$row['user_id']
    || user_can_view_all_sources($viewer)
);

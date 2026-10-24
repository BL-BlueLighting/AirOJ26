<?php
/**
 * AirOJ — 章节详情
 */
$title = '章节';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';

$id = intval($_GET['id'] ?? 0);
$chapter = Database::fetchOne("SELECT * FROM chapters WHERE chapter_id=?", [$id]);

// 章节设置了背景时，整页背景也使用它（而不是回落到站点背景）
$chapter_bg = trim((string)($chapter['background'] ?? ''));
if ($chapter_bg !== '' && is_valid_background($chapter_bg)) {
    $page_background_override = $chapter_bg;
}

require_once 'include/Parsedown.php';

if (!$chapter) {
    render_error('章节不存在');
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

<?php
$title = '比赛详情';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';

$cid = intval($_GET['cid'] ?? 0);
$contest = Database::fetchOne("SELECT * FROM contest WHERE contest_id=?", [$cid]);
if (!$contest) {
    render_error('比赛不存在');
}

$status = contest_status($contest);
$problems = contest_problem_list($cid);
$uid = $_SESSION['airoj_user']['user_id'] ?? null;

$my = [];
if ($uid && $problems) {
    foreach (Database::fetchAll(
        "SELECT problem_id, MAX(score) AS best, MAX(CASE WHEN status='done' AND score>=100 THEN 1 ELSE 0 END) AS ac
         FROM submissions WHERE contest_id=? AND user_id=? GROUP BY problem_id", [$cid, $uid]) as $r) {
        $my[(int)$r['problem_id']] = $r;
    }
}

$tries = [];
foreach (Database::fetchAll(
    "SELECT problem_id, COUNT(*) AS total, SUM(CASE WHEN status='done' AND score>=100 THEN 1 ELSE 0 END) AS ac
     FROM submissions WHERE contest_id=? GROUP BY problem_id", [$cid]) as $r) {
    $tries[(int)$r['problem_id']] = $r;
}

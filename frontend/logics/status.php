<?php
$title = '评测状态';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';

$pid    = trim($_GET['pid'] ?? '');
$uid    = trim($_GET['uid'] ?? '');
$lang   = trim($_GET['lang'] ?? '');
$result = $_GET['result'] ?? '';
$mine   = isset($_GET['mine']);
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 30;

if ($mine && !empty($_SESSION['airoj_user']['user_id'])) {
    $uid = $_SESSION['airoj_user']['user_id'];
}

// 组装筛选条件
$where = [];
$params = [];
if ($pid !== '')  { $where[] = "problem_id = ?"; $params[] = $pid; }
if ($uid !== '')  { $where[] = "user_id = ?";    $params[] = $uid; }
if ($lang !== '') { $where[] = "judge_lang = ?"; $params[] = $lang; }
if ($result === 'ac')  $where[] = "status = 'done' AND score >= 100";
if ($result === 'wa')  $where[] = "status = 'done' AND score < 100";
if ($result === 'err') $where[] = "status IN ('failed','pending','judging')";
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions $whereSql", $params)['c'] ?? 0;
$rows  = Database::fetchAll(
    "SELECT id, problem_id, judge_lang, status, score, passed_cases, total_cases, user_id, created_at
     FROM submissions $whereSql ORDER BY id DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, ($page - 1) * $limit])
);
$maxP = max(1, ceil($total / $limit));
$langs = get_language_configs();

// 源码可见性：管理员 / source_viewer 可看全部，普通用户只能看自己的
$viewer = $_SESSION['airoj_user'] ?? null;
$view_all_sources = user_can_view_all_sources($viewer);

// 保留筛选条件的分页链接
$qs = $_GET; unset($qs['page']);
function page_url(array $qs, int $p): string { return '?' . http_build_query(array_merge($qs, ['page' => $p])); }

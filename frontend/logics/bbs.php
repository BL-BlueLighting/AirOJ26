<?php
$title = '讨论区';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';

$page  = max(1, intval($_GET['page'] ?? 1));
$pid   = intval($_GET['pid'] ?? 0);
$limit = 20;
$user  = $_SESSION['airoj_user'] ?? null;

$where  = $pid ? "WHERE t.problem_id = ?" : "";
$params = $pid ? [$pid] : [];

$total  = Database::fetchOne("SELECT COUNT(*) AS c FROM bbs_topic t $where", $params)['c'] ?? 0;
$topics = Database::fetchAll(
    "SELECT t.*, p.title AS problem_title FROM bbs_topic t
     LEFT JOIN problem p ON p.problem_id = t.problem_id
     $where
     ORDER BY COALESCE(t.last_reply, t.created_at) DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, ($page - 1) * $limit])
);
$maxP = max(1, ceil($total / $limit));

$problem = $pid ? Database::fetchOne("SELECT title FROM problem WHERE problem_id=?", [$pid]) : null;

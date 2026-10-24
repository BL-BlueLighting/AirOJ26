<?php
$title = '个人中心';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/Parsedown.php';
if (session_status() === PHP_SESSION_NONE) @session_start();

$viewer = $_SESSION['airoj_user'] ?? null;
$profile_uid = trim((string)($_GET['uid'] ?? ''));
if ($profile_uid === '') {
    if (!$viewer) { header('Location: /login.php'); exit; }
    $profile_uid = (string)$viewer['user_id'];
}

$profile_user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$profile_uid]);
if (!$profile_user) {
    $title = '用户不存在';
    render_error('用户不存在');
}

$is_self = $viewer && (string)$viewer['user_id'] === $profile_uid;
$title = $is_self ? '个人中心' : '用户主页';
$uid = $profile_uid;

$rating = round($profile_user['rating'] ?? 1500);
$solved_count = $profile_user['solved_count'] ?? 0;
$submit_count = $profile_user['submit_count'] ?? 0;
$total_sub = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions WHERE user_id=?", [$uid])['c'] ?? 0;
$ac_sub = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions WHERE user_id=? AND score>=100", [$uid])['c'] ?? 0;

// 已通过题目列表（按ID排序）
$ac_problems = Database::fetchAll(
    "SELECT DISTINCT s.problem_id FROM submissions s WHERE s.user_id=? AND s.score>=100 AND s.status='done' ORDER BY CAST(s.problem_id AS INTEGER) ASC",
    [$uid]
);
$ac_count = count($ac_problems);

// 最近提交
$subs = Database::fetchAll("SELECT id,problem_id,judge_lang,status,score FROM submissions WHERE user_id=? ORDER BY id DESC LIMIT 15", [$uid]);

// 成就与收藏品
$achievements = Database::fetchAll(
    "SELECT a.*, ua.unlocked_at FROM achievements a
     LEFT JOIN user_achievements ua ON ua.achievement_id=a.achievement_id AND ua.user_id=?
     ORDER BY a.achievement_id", [$uid]);
$equipped = user_equipped_collectibles($uid);
$ownedCount = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM user_collectibles WHERE user_id=?", [$uid])['c'] ?? 0);

$pd = new Parsedown();
$pd->setSafeMode(true);
$userBio = trim((string)($profile_user['bio'] ?? ''));

// header.php 会用 session 覆盖 $profile_user，这里重新读一次数据库，保证资料/头像/背景是最新的
$profile_user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
$userBio = trim((string)($profile_user['bio'] ?? ''));

<?php
$title = '成就';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';

$view_uid = trim((string)($_GET['uid'] ?? ''));
$me = $_SESSION['airoj_user']['user_id'] ?? null;
if ($view_uid === '') $view_uid = $me;
$view_user = $view_uid ? Database::fetchOne("SELECT user_id, nick FROM users WHERE user_id=?", [$view_uid]) : null;

$achievements = Database::fetchAll(
    "SELECT a.*, ua.unlocked_at FROM achievements a
     LEFT JOIN user_achievements ua ON ua.achievement_id=a.achievement_id AND ua.user_id=?
     ORDER BY a.achievement_id",
    [$view_uid ?: '']
);
$unlocked = 0;
foreach ($achievements as $a) if (!empty($a['unlocked_at'])) $unlocked++;

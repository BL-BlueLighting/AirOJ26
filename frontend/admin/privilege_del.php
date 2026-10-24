<?php
$title = 'privilege_del';
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/mailer.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$uid = $_GET['uid'] ?? '';
$right = $_GET['right'] ?? '';
if ($uid && $right) {
    $targetUser = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
    $hadPriv = Database::fetchOne("SELECT 1 AS x FROM privilege WHERE user_id=? AND right_str=?", [$uid, $right]);
    Database::exec("DELETE FROM privilege WHERE user_id=? AND right_str=?", [$uid, $right]);
    if ($targetUser && $hadPriv) {
        [$sent, $mailErr] = send_admin_action_email($targetUser, '权限变更', ['已移除权限：' . $right]);
        if ($mailErr !== '') error_log('[AirOJ mail] admin action notify failed: ' . $mailErr);
    }
}
header('Location: privilege_list.php');

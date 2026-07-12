<?php
$title = 'privilege_del';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$uid = $_GET['uid'] ?? '';
$right = $_GET['right'] ?? '';
if ($uid && $right) {
    Database::exec("DELETE FROM privilege WHERE user_id=? AND right_str=?", [$uid, $right]);
}
header('Location: privilege_list.php');

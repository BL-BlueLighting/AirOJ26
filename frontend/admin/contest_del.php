<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id) {
    Database::exec("DELETE FROM contest_problem WHERE contest_id=?", [$id]);
    Database::exec("DELETE FROM contest WHERE contest_id=?", [$id]);
}
header('Location: contest_list.php');

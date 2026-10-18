<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$id = intval($_GET['id'] ?? 0);
if ($id) {
    foreach (glob(__DIR__ . '/../data/uploads/chapter-' . $id . '.*') ?: [] as $f) @unlink($f);
    Database::exec("DELETE FROM chapter_problem WHERE chapter_id=?", [$id]);
    Database::exec("DELETE FROM chapters WHERE chapter_id=?", [$id]);
}
header('Location: chapter_list.php');

<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$id = intval($_GET['id'] ?? 0);
$row = Database::fetchOne("SELECT * FROM problem WHERE problem_id=?", [$id]);
if ($row) {
    Database::exec("DELETE FROM problem WHERE problem_id=?", [$id]);
    // Optionally remove test data
    $dir = INPUTS_DIR . '/' . $id;
    if (is_dir($dir)) {
        array_map('unlink', glob("$dir/*"));
        rmdir($dir);
    }
}
header('Location: problem_list.php');

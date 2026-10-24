<?php
$title = '题解';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/Parsedown.php';

$pid = intval($_GET['id'] ?? 0);
$user = $_SESSION['airoj_user'] ?? null;
$pd = new Parsedown();
$err = ''; $ok = '';

// 提交题解
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_solution'])) {
    if (!$user) { header('Location: /login.php'); exit; }
    $title_s = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title_s && $content) {
        Database::insert("INSERT INTO solutions (problem_id, user_id, title, content) VALUES (?,?,?,?)",
            [$pid, $user['user_id'], $title_s, $content]);
        $ok = '题解已发布';
    } else $err = '请填写标题和内容';
}

// 获取题解列表
$solutions = Database::fetchAll("SELECT s.*, u.nick FROM solutions s LEFT JOIN users u ON s.user_id=u.user_id WHERE s.problem_id=? ORDER BY s.created_at DESC", [$pid]);
$problem = Database::fetchOne("SELECT title FROM problem WHERE problem_id=?", [$pid]);

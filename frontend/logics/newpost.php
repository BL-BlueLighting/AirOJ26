<?php
$title = '发帖';
require_once 'include/config.php';
require_once 'include/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (!isset($_SESSION['airoj_user'])) { header('Location: login.php'); exit; }

$pid = intval($_GET['pid'] ?? $_POST['pid'] ?? 0);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_t = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title_t !== '' && $content !== '') {
        $tid = Database::insert(
            "INSERT INTO bbs_topic (problem_id, user_id, title, content, reply_count) VALUES (?,?,?,?,0)",
            [$pid, $_SESSION['airoj_user']['user_id'], $title_t, $content]
        );
        plugin_do('Hook:Discuss/Topic.Send', [
            'topic_id' => $tid, 'problem_id' => $pid,
            'user_id' => $_SESSION['airoj_user']['user_id'],
            'title' => $title_t, 'content' => $content,
        ]);
        header('Location: topic.php?id=' . $tid);
        exit;
    }
    $err = '请填写标题和内容';
}

$problem = $pid ? Database::fetchOne("SELECT title FROM problem WHERE problem_id=?", [$pid]) : null;

<?php
$title = '源代码';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();

$id = intval($_GET['id'] ?? 0);
$row = Database::fetchOne("SELECT * FROM submissions WHERE id=?", [$id]);
$user = $_SESSION['airoj_user'] ?? null;

if (!$row) {
    render_error('提交记录不存在');
}

// 仅提交者本人、管理员或拥有 source_viewer 权限的用户可查看
$allowed = $user && (
    (string)$user['user_id'] === (string)$row['user_id']
    || user_can_view_all_sources($user)
);

if (!$allowed) {
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    http_response_code(403);
    render_error('你没有权限查看该提交的源代码', '无权查看');
}

<?php
/**
 * 后台题目编辑页的实时预览接口（仅管理员可访问）
 * 接收题目字段（POST），返回与 problem.php 相同规则的预览 HTML。
 */
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();

$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) {
    http_response_code(403);
    exit('forbidden');
}

header('Content-Type: text/html; charset=utf-8');
echo problem_preview_html($_POST);

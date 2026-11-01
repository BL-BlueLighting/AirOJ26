<?php
/**
 * 桌面端轮询实人认证状态。
 */
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/db.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/frv.php';

if (session_status() === PHP_SESSION_NONE) @session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$uid = (string)($_SESSION['airoj_user']['user_id'] ?? '');
if ($uid === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = Database::fetchOne(
    "SELECT frv_status, frv_verified_at, frv_last_error FROM users WHERE user_id=?",
    [$uid]
);
if (!$user) {
    echo json_encode(['ok' => false, 'error' => '用户不存在'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode([
    'ok'          => true,
    'status'      => (string)($user['frv_status'] ?? 'none'),
    'verified_at' => (string)($user['frv_verified_at'] ?? ''),
    'error'       => (string)($user['frv_last_error'] ?? ''),
], JSON_UNESCAPED_UNICODE);

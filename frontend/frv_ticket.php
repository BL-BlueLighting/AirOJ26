<?php
/**
 * 桌面端生成手机扫码认证票据。
 */
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/db.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/frv.php';

if (session_status() === PHP_SESSION_NONE) @session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function frv_ticket_json(bool $ok, array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    frv_ticket_json(false, ['error' => '请求方式错误']);
}
$uid = (string)($_SESSION['airoj_user']['user_id'] ?? '');
if ($uid === '') {
    http_response_code(401);
    frv_ticket_json(false, ['error' => '请先登录']);
}

$cfg = frv_config();
if (!$cfg['enabled']) {
    frv_ticket_json(false, ['error' => '管理员尚未开启实人认证']);
}

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    frv_ticket_json(false, ['error' => '请求格式错误']);
}
$realName = frv_normalize_name((string)($in['real_name'] ?? ''));
$idCard = strtoupper(trim((string)($in['id_card'] ?? '')));
if (!frv_valid_real_name($realName)) {
    frv_ticket_json(false, ['error' => '请输入正确的中文真实姓名']);
}
if (!frv_valid_id_card($idCard)) {
    frv_ticket_json(false, ['error' => '请输入正确的 18 位身份证号']);
}

$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
if (!$user) {
    frv_ticket_json(false, ['error' => '用户不存在']);
}
if ((string)($user['frv_status'] ?? 'none') === 'verified') {
    frv_ticket_json(false, ['error' => '你已完成实人认证']);
}
if (frv_remaining_attempts($user) <= 0) {
    frv_ticket_json(false, ['error' => '今日实人认证次数已用完，请明天再试']);
}

$ticket = frv_create_ticket($uid, $realName, $idCard, 'mobile');
$mobileUrl = frv_site_base() . '/frv_mobile.php?ticket=' . urlencode($ticket['ticket']);
frv_ticket_json(true, [
    'ticket'     => $ticket['ticket'],
    'mobile_url' => $mobileUrl,
    'expires_in' => max(0, $ticket['expires_at'] - time()),
]);

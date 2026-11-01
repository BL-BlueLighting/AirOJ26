<?php
/**
 * 发起 uniCloud 实人认证：返回 certifyUrl，由浏览器跳转刷脸。
 */
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/db.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/frv.php';

if (session_status() === PHP_SESSION_NONE) @session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function frv_json(bool $ok, array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    frv_json(false, ['error' => '请求方式错误']);
}
$uid = (string)($_SESSION['airoj_user']['user_id'] ?? '');
if ($uid === '') {
    http_response_code(401);
    frv_json(false, ['error' => '请先登录']);
}

$cfg = frv_config();
if (!$cfg['enabled']) {
    frv_json(false, ['error' => '管理员尚未开启实人认证']);
}

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    frv_json(false, ['error' => '请求格式错误']);
}
$realName = frv_normalize_name((string)($in['real_name'] ?? ''));
$idCard = strtoupper(trim((string)($in['id_card'] ?? '')));
$metaInfo = (string)($in['meta_info'] ?? '');

if (!frv_valid_real_name($realName)) {
    frv_json(false, ['error' => '请输入正确的中文真实姓名']);
}
if (!frv_valid_id_card($idCard)) {
    frv_json(false, ['error' => '请输入正确的 18 位身份证号']);
}
if ($metaInfo === '' || strlen($metaInfo) > 8192) {
    frv_json(false, ['error' => '设备信息无效，请刷新页面后重试']);
}

$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
if (!$user) {
    frv_json(false, ['error' => '用户不存在']);
}
if ((string)($user['frv_status'] ?? 'none') === 'verified') {
    frv_json(false, ['error' => '你已完成实人认证']);
}
$today = date('Y-m-d');
$count = ((string)($user['frv_start_date'] ?? '') === $today) ? (int)($user['frv_start_count'] ?? 0) : 0;
if ($count >= $cfg['daily_limit']) {
    frv_json(false, ['error' => '今日实人认证次数已用完，请明天再试']);
}

$ticket = frv_create_ticket($uid, $realName, $idCard, 'desktop');
$returnUrl = frv_ticket_return_url($ticket['ticket']);

$res = frv_api('getCertifyUrl', [
    'uid'       => $uid,
    'realName'  => $realName,
    'idCard'    => $idCard,
    'metaInfo'  => $metaInfo,
    'returnUrl' => $returnUrl,
]);
if (!$res['ok']) {
    Database::exec("UPDATE frv_tickets SET status='failed' WHERE ticket=?", [$ticket['ticket']]);
    Database::exec(
        "UPDATE users SET frv_status='failed', frv_last_error=? WHERE user_id=?",
        [mb_substr((string)$res['error'], 0, 200), $uid]
    );
    frv_json(false, ['error' => $res['error']]);
}

$data = $res['data'];
$certifyUrl = (string)($data['certifyUrl'] ?? $data['certify_url'] ?? '');
if ($certifyUrl === '' || !preg_match('~^https?://~i', $certifyUrl)) {
    Database::exec("UPDATE frv_tickets SET status='failed' WHERE ticket=?", [$ticket['ticket']]);
    Database::exec(
        "UPDATE users SET frv_status='failed', frv_last_error=? WHERE user_id=?",
        ['未获取到认证地址', $uid]
    );
    frv_json(false, ['error' => '未获取到认证地址，请稍后重试']);
}
$certifyId = (string)($data['certifyId'] ?? $data['certify_id'] ?? '');

Database::exec(
    "UPDATE frv_tickets SET started_at=?, certify_id=? WHERE ticket=?",
    [date('Y-m-d H:i:s'), $certifyId, $ticket['ticket']]
);
Database::exec(
    "UPDATE users SET frv_status='pending', frv_certify_id=?, frv_real_name=?, frv_last_error='',
        frv_start_date=?, frv_start_count=? WHERE user_id=?",
    [$certifyId, $realName, $today, $count + 1, $uid]
);

frv_json(true, ['certify_url' => $certifyUrl]);

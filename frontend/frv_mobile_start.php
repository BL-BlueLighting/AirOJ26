<?php
/**
 * 手机端发起实人认证：ticket + 手机自己的 metaInfo。
 */
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/db.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/frv.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function frv_mobile_json(bool $ok, array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    frv_mobile_json(false, ['error' => '请求方式错误']);
}
$cfg = frv_config();
if (!$cfg['enabled']) {
    frv_mobile_json(false, ['error' => '管理员尚未开启实人认证']);
}

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    frv_mobile_json(false, ['error' => '请求格式错误']);
}
$ticketCode = trim((string)($in['ticket'] ?? ''));
$realName = frv_normalize_name((string)($in['real_name'] ?? ''));
$idCard = strtoupper(trim((string)($in['id_card'] ?? '')));
$metaInfo = (string)($in['meta_info'] ?? '');

$ticket = frv_get_ticket($ticketCode);
if (!$ticket) {
    frv_mobile_json(false, ['error' => '二维码无效或已过期']);
}
if ((string)$ticket['status'] !== 'pending') {
    frv_mobile_json(false, ['error' => '该二维码已使用或已失效']);
}
if ((int)$ticket['expires_at'] < time()) {
    frv_mobile_json(false, ['error' => '二维码已过期，请回到电脑端重新生成']);
}
if (trim((string)$ticket['started_at']) !== '') {
    frv_mobile_json(false, ['error' => '该二维码已开始认证']);
}

if (!frv_valid_real_name($realName)) {
    frv_mobile_json(false, ['error' => '请输入正确的中文真实姓名']);
}
if (!frv_valid_id_card($idCard)) {
    frv_mobile_json(false, ['error' => '请输入正确的 18 位身份证号']);
}
if (!frv_ticket_identity_ok($ticket, $realName, $idCard)) {
    frv_mobile_json(false, ['error' => '姓名或身份证号与电脑端填写的不一致']);
}
if ($metaInfo === '' || strlen($metaInfo) > 8192) {
    frv_mobile_json(false, ['error' => '设备信息无效，请刷新页面后重试']);
}

$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [(string)$ticket['user_id']]);
if (!$user) {
    frv_mobile_json(false, ['error' => '用户不存在']);
}
if ((string)($user['frv_status'] ?? 'none') === 'verified') {
    frv_mobile_json(false, ['error' => '该账号已完成实人认证']);
}
$today = date('Y-m-d');
$count = ((string)($user['frv_start_date'] ?? '') === $today) ? (int)($user['frv_start_count'] ?? 0) : 0;
if ($count >= $cfg['daily_limit']) {
    frv_mobile_json(false, ['error' => '今日实人认证次数已用完，请明天再试']);
}

$started = Database::exec(
    "UPDATE frv_tickets SET started_at=?, mode='mobile' WHERE ticket=? AND status='pending' AND started_at=''",
    [date('Y-m-d H:i:s'), $ticketCode]
);
if ($started < 1) {
    frv_mobile_json(false, ['error' => '该二维码已开始认证，请回到电脑端查看结果']);
}

$res = frv_api('getCertifyUrl', [
    'uid'       => (string)$ticket['user_id'],
    'realName'  => $realName,
    'idCard'    => $idCard,
    'metaInfo'  => $metaInfo,
    'returnUrl' => frv_ticket_return_url($ticketCode),
]);
if (!$res['ok']) {
    Database::exec("UPDATE frv_tickets SET status='failed' WHERE ticket=?", [$ticketCode]);
    Database::exec(
        "UPDATE users SET frv_status='failed', frv_last_error=? WHERE user_id=?",
        [mb_substr((string)$res['error'], 0, 200), (string)$ticket['user_id']]
    );
    frv_mobile_json(false, ['error' => $res['error']]);
}

$data = $res['data'];
$certifyUrl = (string)($data['certifyUrl'] ?? $data['certify_url'] ?? '');
if ($certifyUrl === '' || !preg_match('~^https?://~i', $certifyUrl)) {
    Database::exec("UPDATE frv_tickets SET status='failed' WHERE ticket=?", [$ticketCode]);
    Database::exec(
        "UPDATE users SET frv_status='failed', frv_last_error=? WHERE user_id=?",
        ['未获取到认证地址', (string)$ticket['user_id']]
    );
    frv_mobile_json(false, ['error' => '未获取到认证地址，请回到电脑端刷新二维码重试']);
}
$certifyId = (string)($data['certifyId'] ?? $data['certify_id'] ?? '');

Database::exec(
    "UPDATE frv_tickets SET certify_id=? WHERE ticket=?",
    [$certifyId, $ticketCode]
);
Database::exec(
    "UPDATE users SET frv_status='pending', frv_certify_id=?, frv_real_name=?, frv_last_error='',
        frv_start_date=?, frv_start_count=? WHERE user_id=?",
    [$certifyId, $realName, $today, $count + 1, (string)$ticket['user_id']]
);

frv_mobile_json(true, ['certify_url' => $certifyUrl]);

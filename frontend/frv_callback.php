<?php
/**
 * uniCloud 实人认证回调：凭一次性 ticket 查询 getAuthResult 并写入认证状态。
 * 手机扫码时手机端通常没有登录态，因此不能依赖 session 识别用户。
 */
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/db.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/frv.php';

if (session_status() === PHP_SESSION_NONE) @session_start();

function frv_result_page(bool $ok, string $message): void
{
    $status = $ok
        ? '<div class="big ok">✓</div><h1>认证成功</h1>'
        : '<div class="big err">!</div><h1>认证未通过</h1>';
    $body = $status
        . '<p class="' . ($ok ? 'ok' : 'err') . '">' . htmlspecialchars($message) . '</p>'
        . '<p>请回到电脑端查看结果。</p>';
    echo frv_mobile_shell($ok ? '实人认证成功' : '实人认证未通过', $body);
    exit;
}

/** 保留当前 ticket 状态供手机结果页使用，同时清空姓名/身份证哈希 */
function frv_close_ticket(string $ticketCode, string $userId, string $status): void
{
    Database::exec(
        "UPDATE frv_tickets SET status=?, name_hash='', id_hash='', certify_id='' WHERE ticket=?",
        [$status, $ticketCode]
    );
    Database::exec("DELETE FROM frv_tickets WHERE user_id=? AND ticket<>?", [$userId, $ticketCode]);
}

$ticketCode = (string)($_GET['ticket'] ?? '');
$ticket = frv_get_ticket($ticketCode);
$uid = (string)($_SESSION['airoj_user']['user_id'] ?? '');
$isOwner = $ticket && $uid !== '' && $uid === (string)$ticket['user_id'];

if (!$ticket) {
    frv_result_page(false, '认证票据无效或已过期，请回到电脑端重新发起。');
}
if ((string)$ticket['status'] === 'verified') {
    if ($isOwner) {
        $_SESSION['settings_flash'] = '实人认证已通过';
        header('Location: /settings.php?tab=frv');
        exit;
    }
    frv_result_page(true, '实人认证已通过。');
}
if ((string)$ticket['status'] === 'failed') {
    if ($isOwner) {
        $_SESSION['settings_flash'] = '实人认证未通过，请重新发起';
        header('Location: /settings.php?tab=frv');
        exit;
    }
    frv_result_page(false, '实人认证未通过。');
}
if (trim((string)$ticket['started_at']) === '') {
    frv_result_page(false, '该二维码尚未发起认证，请回到电脑端刷新后重试。');
}

$raw = (string)($_GET['response'] ?? '');
$response = json_decode($raw, true);
if (!is_array($response)) {
    $response = json_decode(urldecode($raw), true);
}
$certifyId = frv_extract_certify_id($response);
if ($certifyId === '') {
    Database::exec(
        "UPDATE users SET frv_status='failed', frv_real_name='', frv_last_error=? WHERE user_id=?",
        ['未获取到认证结果', (string)$ticket['user_id']]
    );
    frv_close_ticket($ticketCode, (string)$ticket['user_id'], 'failed');
    frv_result_page(false, '未获取到认证结果，请回到电脑端重新发起。');
}

$stored = trim((string)($ticket['certify_id'] ?? ''));
if ($stored !== '' && !hash_equals($stored, $certifyId)) {
    Database::exec(
        "UPDATE users SET frv_status='failed', frv_real_name='', frv_last_error=? WHERE user_id=?",
        ['认证结果与当前申请不匹配', (string)$ticket['user_id']]
    );
    frv_close_ticket($ticketCode, (string)$ticket['user_id'], 'failed');
    frv_result_page(false, '认证结果与当前申请不匹配，请回到电脑端重新发起。');
}

$res = frv_api('getAuthResult', ['uid' => (string)$ticket['user_id'], 'certifyId' => $certifyId]);
if ($res['ok'] && frv_auth_success($res['data'])) {
    $u = Database::fetchOne("SELECT frv_real_name FROM users WHERE user_id=?", [(string)$ticket['user_id']]);
    $realName = trim((string)($res['data']['realName'] ?? ($u['frv_real_name'] ?? '')));
    Database::exec(
        "UPDATE users SET frv_status='verified', frv_real_name=?, frv_verified_at=?,
            frv_certify_id='', frv_last_error='' WHERE user_id=?",
        [$realName, date('Y-m-d H:i:s'), (string)$ticket['user_id']]
    );
    frv_close_ticket($ticketCode, (string)$ticket['user_id'], 'verified');
    if ($isOwner) {
        $_SESSION['settings_flash'] = '实人认证已通过';
        header('Location: /settings.php?tab=frv');
        exit;
    }
    frv_result_page(true, '实人认证已通过。');
}

$error = $res['ok'] ? '认证未通过' : (string)$res['error'];
Database::exec(
    "UPDATE users SET frv_status='failed', frv_real_name='', frv_certify_id='', frv_last_error=? WHERE user_id=?",
    [mb_substr($error, 0, 200), (string)$ticket['user_id']]
);
frv_close_ticket($ticketCode, (string)$ticket['user_id'], 'failed');
if ($isOwner) {
    $_SESSION['settings_flash'] = '实人认证未通过：' . $error;
    header('Location: /settings.php?tab=frv');
    exit;
}
frv_result_page(false, '实人认证未通过：' . $error);

<?php
/**
 * uniCloud 实人认证（FRV）辅助函数。
 *
 * 网页端使用阿里云实人认证 Web SDK 的 window.getMetaInfo() 生成 metaInfo，
 * 服务端通过 URL 化云函数调用 getCertifyUrl / getAuthResult。
 */

/** 阿里云实人认证 Web SDK（提供 window.getMetaInfo） */
function frv_default_meta_script(): string
{
    return 'https://cn-shanghai-aliyun-cloudauth.oss-cn-shanghai.aliyuncs.com/web_sdk_js/jsvm_all.js';
}

/** 实人认证配置 */
function frv_config(): array
{
    return [
        'enabled'         => (string)Database::getConfig('frv_enabled', '0') === '1',
        'base_url'        => rtrim(trim((string)Database::getConfig('frv_base_url', '')), '/'),
        'app_id'          => trim((string)Database::getConfig('frv_app_id', '')),
        'connect_code'    => trim((string)Database::getConfig('frv_connect_code', '')),
        'meta_script_url' => trim((string)Database::getConfig('frv_meta_script_url', '')) ?: frv_default_meta_script(),
        'return_url'      => trim((string)Database::getConfig('frv_return_url', '')),
        'daily_limit'     => max(1, (int)Database::getConfig('frv_daily_limit', '3')),
        'manual_award'    => (string)Database::getConfig('award_manual_enabled', '0') === '1',
    ];
}

/** 认证结束后 uniCloud 回调的地址（必须为 HTTPS） */
function frv_return_url(): string
{
    $cfg = frv_config();
    if ($cfg['return_url'] !== '') return $cfg['return_url'];

    $site = trim((string)Database::getConfig('mail_site_url', ''));
    if ($site !== '') return rtrim($site, '/') . '/frv_callback.php';

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host . '/frv_callback.php';
}

/** 站点根地址（用于手机扫码页） */
function frv_site_base(): string
{
    $site = trim((string)Database::getConfig('mail_site_url', ''));
    if ($site !== '') return rtrim($site, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** 实人认证姓名归一化 */
function frv_normalize_name(string $name): string
{
    return (string)preg_replace('/[\s\x{3000}]+/u', '', trim($name));
}

/** 是否为合法的中文姓名 */
function frv_valid_real_name(string $name): bool
{
    return (bool)preg_match('/^[\x{4e00}-\x{9fa5}·]{2,20}$/u', frv_normalize_name($name));
}

/** 是否为合法的 18 位身份证号 */
function frv_valid_id_card(string $idCard): bool
{
    return (bool)preg_match('/^\d{17}[\dX]$/', strtoupper(trim($idCard)));
}

/** 二维码票据的 HMAC 密钥（自动生成并保存在 system_config） */
function frv_ticket_key(): string
{
    $key = (string)Database::getConfig('frv_ticket_key', '');
    if ($key === '') {
        $key = bin2hex(random_bytes(32));
        Database::setConfig('frv_ticket_key', $key);
    }
    return $key;
}

/** 姓名 / 身份证号哈希，用于手机扫码时与电脑端填写内容比对 */
function frv_identity_hash(string $realName, string $idCard): array
{
    $key = frv_ticket_key();
    return [
        hash_hmac('sha256', frv_normalize_name($realName), $key),
        hash_hmac('sha256', strtoupper(trim($idCard)), $key),
    ];
}

/** 创建一次性扫码/认证票据，有效期 30 分钟 */
function frv_create_ticket(string $userId, string $realName, string $idCard, string $mode = 'desktop'): array
{
    $ticket = bin2hex(random_bytes(32));
    [$nameHash, $idHash] = frv_identity_hash($realName, $idCard);
    $expires = time() + 1800;
    Database::exec("DELETE FROM frv_tickets WHERE expires_at < ?", [time() - 86400]);
    Database::exec(
        "INSERT INTO frv_tickets (ticket,user_id,name_hash,id_hash,mode,status,expires_at)
         VALUES (?,?,?,?,?,'pending',?)",
        [$ticket, $userId, $nameHash, $idHash, $mode, $expires]
    );
    return ['ticket' => $ticket, 'expires_at' => $expires];
}

/** 读取票据 */
function frv_get_ticket(string $ticket): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $ticket)) return null;
    return Database::fetchOne("SELECT * FROM frv_tickets WHERE ticket=?", [$ticket]);
}

/** 手机端填写的姓名/身份证号是否与电脑端一致 */
function frv_ticket_identity_ok(array $ticket, string $realName, string $idCard): bool
{
    [$nameHash, $idHash] = frv_identity_hash($realName, $idCard);
    return hash_equals((string)$ticket['name_hash'], $nameHash)
        && hash_equals((string)$ticket['id_hash'], $idHash);
}

/** 带 ticket 的认证回调地址 */
function frv_ticket_return_url(string $ticket): string
{
    $base = frv_return_url();
    return $base . (strpos($base, '?') === false ? '?' : '&') . 'ticket=' . urlencode($ticket);
}

/** 当前用户今日剩余实人认证次数 */
function frv_remaining_attempts(?array $user): int
{
    $cfg = frv_config();
    if (!$user) return 0;
    if (($user['frv_status'] ?? 'none') === 'verified') return 0;
    $count = ((string)($user['frv_start_date'] ?? '') === date('Y-m-d'))
        ? (int)($user['frv_start_count'] ?? 0) : 0;
    return max(0, $cfg['daily_limit'] - $count);
}

/** 从 uniCloud 回调的 response 参数中提取 certifyId */
function frv_extract_certify_id($response): string
{
    if (is_string($response)) {
        $response = json_decode($response, true);
    }
    if (!is_array($response)) return '';
    return (string)($response['extInfo']['certifyId'] ?? ($response['certifyId'] ?? ''));
}

/** 判断 getAuthResult 是否表示认证成功 */
function frv_auth_success(array $data): bool
{
    if (isset($data['authState'])) return strtoupper((string)$data['authState']) === 'SUCCESS';
    if (isset($data['status'])) return (int)$data['status'] === 2;
    return false;
}

/**
 * 调用 URL 化 uniCloud 云函数。
 * @return array{ok:bool,data:array,error:string}
 */
function frv_api(string $action, array $payload): array
{
    $cfg = frv_config();
    if ($cfg['base_url'] === '') {
        return ['ok' => false, 'data' => [], 'error' => '管理员尚未配置实人认证服务地址'];
    }
    if (!in_array($action, ['getCertifyUrl', 'getAuthResult'], true)) {
        return ['ok' => false, 'data' => [], 'error' => '未知的实人认证操作'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'data' => [], 'error' => '服务器未安装 PHP cURL 扩展'];
    }

    $url = $cfg['base_url'] . '/' . $action;
    if ($cfg['app_id'] !== '' && !isset($payload['appId'])) $payload['appId'] = $cfg['app_id'];

    $headers = [
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json',
    ];
    if ($cfg['connect_code'] !== '') {
        $headers[] = 'Unicloud-S2s-Authorization: CONNECTCODE ' . $cfg['connect_code'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $curlErr = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        return ['ok' => false, 'data' => [], 'error' => '实人认证服务连接失败'];
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        return ['ok' => false, 'data' => [], 'error' => '实人认证服务返回格式错误（HTTP ' . $status . '）'];
    }

    // 兼容 { errCode, errMsg, data: {...} } 与直接返回字段两种格式
    $flat = $data;
    if (isset($data['data']) && is_array($data['data'])) {
        $flat = array_merge($data, $data['data']);
    }
    $code = $flat['errCode'] ?? $flat['err_code'] ?? null;
    $ok = ($code === null || $code === 0 || $code === '0' || $code === 'SUCCESS');
    $msg = trim((string)($flat['errMsg'] ?? $flat['err_msg'] ?? $flat['message'] ?? ''));
    if (!$ok) {
        return ['ok' => false, 'data' => $flat, 'error' => $msg !== '' ? $msg : '实人认证服务返回错误'];
    }
    return ['ok' => true, 'data' => $flat, 'error' => ''];
}

/** 实人认证状态中文名 */
function frv_status_label(string $status): string
{
    return ['none' => '未认证', 'pending' => '认证中', 'verified' => '已认证', 'failed' => '未通过'][$status] ?? $status;
}

/** 手机端独立页面外壳（扫码认证 / 结果页） */
function frv_mobile_shell(string $title, string $body, string $metaScriptUrl = ''): string
{
    $siteName = trim((string)Database::getConfig('site_name', '')) ?: 'AirOJ';
    $logo = trim((string)Database::getConfig('site_logo', ''));
    $logoUrl = '';
    if ($logo !== '') {
        $logoUrl = preg_match('~^(https?://|data:image/)~i', $logo) ? $logo : '/' . ltrim($logo, '/');
    }
    $logoHtml = $logoUrl !== ''
        ? '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES) . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES) . '">'
        : '<span>' . htmlspecialchars($siteName) . '</span>';
    $script = $metaScriptUrl !== ''
        ? '<script src="' . htmlspecialchars($metaScriptUrl, ENT_QUOTES) . '"></script>'
        : '';
    $favicon = trim((string)Database::getConfig('site_favicon', ''));
    $faviconUrl = '';
    if ($favicon !== '') {
        $faviconUrl = preg_match('~^(https?://|data:image/)~i', $favicon) ? $favicon : '/' . ltrim($favicon, '/');
    }
    $faviconHtml = $faviconUrl !== ''
        ? '<link rel="icon" href="' . htmlspecialchars($faviconUrl, ENT_QUOTES) . '">'
        : '';
    $titleEsc = htmlspecialchars($title, ENT_QUOTES);
    return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$titleEsc}</title>
{$faviconHtml}
{$script}
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:radial-gradient(circle at 50% 0,#1b2a22,#0b0f0d 70%);color:#e6efe9;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans SC","Microsoft YaHei",sans-serif}
.card{width:100%;max-width:420px;background:#111814;border:1px solid rgba(255,255,255,.09);border-radius:20px;padding:26px 22px;box-shadow:0 24px 60px rgba(0,0,0,.45)}
.brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:18px;font-weight:800;letter-spacing:.02em}
.brand img{max-height:36px;max-width:150px;border-radius:8px}
h1{font-size:20px;margin:0 0 10px;text-align:center}
p{font-size:13px;line-height:1.7;color:#9fb3a8;margin:8px 0}
label{display:block;font-size:12px;color:#9fb3a8;margin:14px 0 6px}
input[type=text]{width:100%;padding:12px 13px;border-radius:10px;border:1px solid rgba(255,255,255,.14);background:#0b110e;color:#e6efe9;font-size:15px;outline:none}
input[type=text]:focus{border-color:#49d28e}
.btn{display:inline-flex;width:100%;justify-content:center;align-items:center;gap:8px;margin-top:18px;padding:13px 16px;border:0;border-radius:10px;background:#49d28e;color:#08120c;font-size:15px;font-weight:800;cursor:pointer}
.btn:disabled{opacity:.55;cursor:not-allowed}
.consent{display:flex;align-items:flex-start;gap:8px;margin-top:14px;font-size:12px;color:#9fb3a8;line-height:1.5}
.msg{margin-top:12px;font-size:12px;color:#ff8b83;min-height:18px;line-height:1.6;word-break:break-word}
.ok{color:#6be0a4}.err{color:#ff8b83}
.big{font-size:44px;text-align:center;margin:10px 0}
</style>
</head>
<body>
<div class="card">
<div class="brand">{$logoHtml}</div>
{$body}
</div>
</body>
</html>
HTML;
}

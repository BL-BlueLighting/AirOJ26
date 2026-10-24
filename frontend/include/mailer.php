<?php
/**
 * AirOJ — 邮件服务
 *
 * 不依赖 Composer，直接用 socket 实现 SMTP（SSL / STARTTLS / AUTH LOGIN / AUTH PLAIN）。
 * 配置保存在 system_config，由后台「系统设置 → 邮件服务」维护。
 */

/** 读取邮件相关配置（表不存在时返回默认值） */
function mail_setting(string $key, string $default = ''): string
{
    try {
        $v = Database::getConfig($key);
        return $v === null ? $default : trim((string)$v);
    } catch (Throwable $e) {
        return $default;
    }
}

/** SMTP 配置数组 */
function mail_smtp_config(): array
{
    $secure = strtolower(mail_setting('smtp_secure', 'tls'));
    if (!in_array($secure, ['tls', 'ssl', 'none'], true)) $secure = 'tls';

    $defaultPort = $secure === 'ssl' ? 465 : ($secure === 'tls' ? 587 : 25);
    $port = (int)mail_setting('smtp_port', (string)$defaultPort);
    if ($port < 1 || $port > 65535) $port = $defaultPort;

    $from = mail_setting('smtp_from');
    $user = mail_setting('smtp_user');
    if ($from === '' && filter_var($user, FILTER_VALIDATE_EMAIL)) $from = $user;

    return [
        'host'        => mail_setting('smtp_host'),
        'port'        => $port,
        'secure'      => $secure,
        'user'        => $user,
        'pass'        => (string)mail_setting('smtp_pass'),
        'from'        => $from,
        'from_name'   => mail_setting('smtp_from_name', 'AirOJ') ?: 'AirOJ',
        'peer_name'   => mail_setting('smtp_peer_name'),
        'verify_peer' => mail_setting('smtp_verify_peer', '1') !== '0',
    ];
}

/** 邮件服务是否已启用且配置完整 */
function mail_service_available(): bool
{
    if (mail_setting('mail_enabled', '0') !== '1') return false;
    $cfg = mail_smtp_config();
    return $cfg['host'] !== '' && filter_var($cfg['from'], FILTER_VALIDATE_EMAIL) !== false;
}

/** 注册是否必须验证邮箱 */
function email_verification_enabled(): bool
{
    return mail_service_available() && mail_setting('email_verification_enabled', '0') === '1';
}

/** 是否允许通过邮箱找回密码 */
function password_reset_enabled(): bool
{
    return mail_service_available() && mail_setting('password_reset_enabled', '0') === '1';
}

function mail_site_name(): string
{
    return mail_setting('site_name', 'AirOJ') ?: 'AirOJ';
}

/** 邮件里的绝对链接前缀（优先使用后台配置的站点 URL） */
function mail_base_url(): string
{
    $base = rtrim(mail_setting('mail_site_url'), '/');
    if ($base !== '' && preg_match('~^https?://~i', $base)) return $base;

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;

    // 优先使用服务器配置的 SERVER_NAME，避免用 Host 头构造邮件链接（防 Host 注入）
    $host = (string)($_SERVER['SERVER_NAME'] ?? '');
    if ($host === '') $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') return '';

    $port = (int)($_SERVER['SERVER_PORT'] ?? 0);
    $hostForUrl = $host;
    if ($port > 0 && $port !== 80 && $port !== 443 && strpos($hostForUrl, ':') === false) {
        $hostForUrl .= ':' . $port;
    }
    return ($https ? 'https' : 'http') . '://' . $hostForUrl;
}

function mail_absolute_url(string $path = ''): string
{
    $base = mail_base_url();
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function mail_token(): string
{
    return bin2hex(random_bytes(32));
}

function mail_token_hash(string $token): string
{
    return hash('sha256', $token);
}

/** 收到站内信时是否自动抄送邮件 */
function message_email_notify_enabled(): bool
{
    return mail_service_available() && mail_setting('message_email_notify', '1') === '1';
}

/** 发送站内信副本邮件 */
function send_message_email(array $recipient, array $sender, array $message): array
{
    $email = trim((string)($recipient['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, ''];
    if (email_verification_enabled() && isset($recipient['email_verified']) && (int)$recipient['email_verified'] === 0) {
        return [false, ''];
    }

    $siteName = mail_site_name();
    $siteEsc = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $recipientName = htmlspecialchars((string)($recipient['nick'] ?: $recipient['user_id']), ENT_QUOTES, 'UTF-8');
    $senderName = htmlspecialchars((string)($sender['nick'] ?: $sender['user_id']), ENT_QUOTES, 'UTF-8');
    $subjectText = (string)($message['subject'] ?? '');
    $subjectEsc = htmlspecialchars($subjectText, ENT_QUOTES, 'UTF-8');
    $mid = (int)($message['id'] ?? 0);
    $url = mail_absolute_url('/messages.php?id=' . $mid);

    require_once __DIR__ . '/Parsedown.php';
    $pd = new Parsedown();
    $pd->setSafeMode(true);
    $contentHtml = $pd->text((string)($message['content'] ?? ''));

    $html = mail_html_wrap(
        '你收到一条新站内信',
        '<p>你好，' . $recipientName . '：</p>'
        . '<p><strong>' . $senderName . '</strong> 给你发送了一条站内信。</p>'
        . '<p style="margin:18px 0 8px;color:#7d8f85;font-size:12px;letter-spacing:.04em">主题</p>'
        . '<p style="margin:0 0 14px;font-size:16px;font-weight:700;color:#17241d">' . $subjectEsc . '</p>'
        . '<div style="margin:0;padding:16px 18px;background:#f7faf8;border-left:3px solid #2bb96f;border-radius:8px;color:#40524a;font-size:14px;line-height:1.8">'
        . $contentHtml . '</div>',
        '查看站内信',
        $url,
        '这是一封站内信副本，请登录 ' . $siteEsc . ' 查看和回复。'
    );
    $text = "你好，{$recipient['nick']}：\n\n{$sender['nick']} 给你发送了一条站内信。\n\n主题：{$subjectText}\n\n{$message['content']}\n\n查看：{$url}\n";
    return send_system_mail($email, '新站内信：' . $subjectText, $html, $text);
}

/**
 * 更新用户邮箱，并在开启邮箱验证时重置验证状态、发送验证邮件。
 * @return array{0:bool,1:string} [是否成功, 提示信息]
 */
function update_user_email(string $userId, string $newEmail): array
{
    $newEmail = trim($newEmail);
    $current = Database::fetchOne("SELECT email, email_verified FROM users WHERE user_id=?", [$userId]);
    if (!$current) return [false, '用户不存在'];

    if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        return [false, '邮箱格式不正确'];
    }
    if (mb_strlen($newEmail) > 200) {
        return [false, '邮箱地址过长'];
    }
    if ($newEmail === '' && email_verification_enabled()) {
        return [false, '请填写邮箱地址'];
    }
    if ($newEmail !== '') {
        $dup = Database::fetchOne(
            "SELECT user_id FROM users WHERE LOWER(email)=LOWER(?) AND user_id<>? LIMIT 1",
            [$newEmail, $userId]
        );
        if ($dup) return [false, '该邮箱已被其他账号使用'];
    }

    if (strcasecmp($newEmail, (string)$current['email']) === 0) {
        return [true, '邮箱未变更'];
    }

    if ($newEmail !== '' && email_verification_enabled()) {
        $raw = mail_token();
        Database::exec(
            "UPDATE users SET email=?, email_verified=0, email_verify_token=?, email_verify_expires=?, email_verify_sent_at=? WHERE user_id=?",
            [$newEmail, mail_token_hash($raw), time() + 86400, time(), $userId]
        );
        $row = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$userId]);
        [$sent, $mailErr] = $row ? send_verification_email($row, $raw) : [false, '用户记录读取失败'];
        if (!$sent) {
            error_log('[AirOJ mail] verification after email change failed for ' . $userId . ': ' . $mailErr);
            return [true, '邮箱已更新，但验证邮件发送失败，请稍后在验证页面重发或联系管理员'];
        }
        return [true, '邮箱已更新，验证邮件已发送；下次登录前需要完成验证'];
    }

    Database::exec(
        "UPDATE users SET email=?, email_verified=1, email_verify_token='', email_verify_expires=0, email_verify_sent_at=0 WHERE user_id=?",
        [$newEmail, $userId]
    );
    return [true, '邮箱已更新'];
}

/** 邮件 HTML 外框 */
function mail_html_wrap(string $title, string $contentHtml, string $buttonText = '', string $buttonUrl = '', string $note = ''): string
{
    $siteName = mail_site_name();
    $site = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $titleHtml = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $siteUrl = mail_base_url();
    $siteUrlHtml = $siteUrl !== '' ? htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') : '';
    $buttonUrlHtml = $buttonUrl !== '' ? htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8') : '';
    $buttonTextHtml = $buttonText !== '' ? htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8') : '';
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Noto Sans SC','Microsoft YaHei',sans-serif";
    $year = date('Y');
    $preheader = mb_substr(trim(strip_tags($title . ' ' . $contentHtml)), 0, 80);
    $preheaderHtml = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');

    // 站点 Logo：配置了就用绝对地址；没有就显示站点名
    $logo = mail_setting('site_logo');
    $logoUrl = '';
    if ($logo !== '') {
        if (preg_match('~^(https?://|data:image/)~i', $logo)) {
            $logoUrl = $logo;
        } elseif ($siteUrl !== '') {
            $logoUrl = mail_absolute_url('/' . ltrim($logo, '/'));
        }
    }
    if ($logoUrl !== '') {
        $brandInner = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $site . '" '
            . 'height="40" style="display:block;height:40px;width:auto;max-width:220px;border:0;outline:none;text-decoration:none;">';
    } else {
        $brandInner = '<span style="display:inline-block;vertical-align:middle;width:9px;height:9px;border-radius:50%;'
            . 'background:#2bb96f;margin-right:9px"></span><span style="display:inline-block;vertical-align:middle;'
            . 'font-size:20px;line-height:40px;font-weight:800;color:#16251d;letter-spacing:-.02em">' . $site . '</span>';
    }
    $brand = $siteUrlHtml !== ''
        ? '<a href="' . $siteUrlHtml . '" style="display:inline-block;text-decoration:none;color:inherit">' . $brandInner . '</a>'
        : $brandInner;

    $button = '';
    if ($buttonTextHtml !== '' && $buttonUrlHtml !== '') {
        $button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0">'
            . '<tr><td align="center" bgcolor="#2bb96f" style="border-radius:10px">'
            . '<a href="' . $buttonUrlHtml . '" style="display:inline-block;padding:13px 28px;font-family:' . $font . ';'
            . 'font-size:15px;font-weight:700;line-height:1.2;color:#ffffff;text-decoration:none;border-radius:10px">'
            . $buttonTextHtml . '</a></td></tr></table>';
    }
    $urlLine = $buttonUrlHtml !== ''
        ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="margin:18px 0 0;background:#f7faf8;border:1px solid #e5eee9;border-radius:10px">'
            . '<tr><td style="padding:12px 14px;color:#7d8f85;font-size:12px;line-height:1.7;word-break:break-all">'
            . '如果按钮无法点击，请复制以下链接到浏览器打开：<br>'
            . '<a href="' . $buttonUrlHtml . '" style="color:#2bb96f;text-decoration:none">' . $buttonUrlHtml . '</a>'
            . '</td></tr></table>'
        : '';
    $noteHtml = $note !== ''
        ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="margin:20px 0 0;background:#f4faf6;border:1px solid #dff0e6;border-radius:10px">'
            . '<tr><td style="padding:12px 14px;color:#6b8075;font-size:12px;line-height:1.7">' . $note . '</td></tr></table>'
        : '';
    $contentHtml = str_replace('<p>', '<p style="margin:0 0 14px">', $contentHtml);
    $siteLink = $siteUrlHtml !== ''
        ? '<a href="' . $siteUrlHtml . '" style="color:#2bb96f;text-decoration:none">' . $site . '</a>'
        : $site;

    return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{$titleHtml}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f2;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all">{$preheaderHtml}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f2">
<tr><td align="center" style="padding:28px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border:1px solid #e3eae6;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(15,40,25,.06)">
<tr><td style="height:4px;background:#2bb96f;font-size:0;line-height:0">&nbsp;</td></tr>
<tr><td style="padding:18px 28px;border-bottom:1px solid #e9efeb;background:#ffffff">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td align="left" style="font-family:{$font}">{$brand}</td>
<td align="right" style="font-family:{$font};font-size:11px;color:#9aaba2;letter-spacing:.08em;white-space:nowrap">ACCOUNT NOTICE</td>
</tr></table>
</td></tr>
<tr><td style="padding:30px 28px 8px;font-family:{$font}">
<h1 style="margin:0 0 18px;font-size:22px;line-height:1.35;color:#17241d;font-weight:700;letter-spacing:-.01em">{$titleHtml}</h1>
<div style="font-size:15px;line-height:1.8;color:#40524a">{$contentHtml}</div>
{$button}
{$urlLine}
{$noteHtml}
</td></tr>
<tr><td style="padding:22px 28px 26px;border-top:1px solid #edf2ee;background:#fbfdfb;font-family:{$font}">
<p style="margin:0;color:#8b9c93;font-size:12px;line-height:1.7">此邮件由 {$siteLink} 自动发送，请勿直接回复。</p>
<p style="margin:7px 0 0;color:#a7b5ad;font-size:11px;line-height:1.6">如果你没有发起此操作，可以忽略本邮件。&nbsp;&copy; {$year} {$site}</p>
</td></tr>
</table>
<p style="margin:14px 0 0;font-family:{$font};color:#a2b0a8;font-size:11px;line-height:1.6">{$site}</p>
</td></tr>
</table>
</body>
</html>
HTML;
}

function mail_test_html(): string
{
    return mail_html_wrap(
        '邮件服务测试成功',
        '<p>你好，这是一封来自 ' . htmlspecialchars(mail_site_name(), ENT_QUOTES, 'UTF-8') . ' 的测试邮件。</p>'
        . '<p>如果你能看到这封邮件，说明 SMTP 配置已经可用，注册邮箱验证与找回密码功能可以正常发信。</p>',
        '',
        '',
        '如果这不是你触发的操作，请检查后台邮件配置。'
    );
}

/** 发送验证邮件 */
function send_verification_email(array $user, string $token): array
{
    $url = mail_absolute_url('/verify_email.php?token=' . urlencode($token));
    $name = htmlspecialchars((string)($user['nick'] ?: $user['user_id']), ENT_QUOTES, 'UTF-8');
    $site = htmlspecialchars(mail_site_name(), ENT_QUOTES, 'UTF-8');
    $html = mail_html_wrap(
        '验证你的邮箱',
        '<p>你好，' . $name . '：</p>'
        . '<p>感谢注册 ' . $site . '。请点击下面的按钮验证邮箱地址，验证链接 <strong>24 小时</strong>内有效。</p>',
        '验证邮箱',
        $url,
        '如果这不是你的操作，请忽略此邮件，你的账号不会受到影响。'
    );
    $text = "你好，{$user['nick']}：\n\n请打开以下链接验证 {$site} 邮箱（24 小时内有效）：\n{$url}\n\n如果这不是你的操作，请忽略此邮件。";
    return send_system_mail((string)$user['email'], '验证你的 ' . mail_site_name() . ' 邮箱', $html, $text);
}

/** 发送找回密码邮件 */
function send_password_reset_email(array $user, string $token): array
{
    $url = mail_absolute_url('/reset_password.php?token=' . urlencode($token));
    $name = htmlspecialchars((string)($user['nick'] ?: $user['user_id']), ENT_QUOTES, 'UTF-8');
    $site = htmlspecialchars(mail_site_name(), ENT_QUOTES, 'UTF-8');
    $html = mail_html_wrap(
        '重置你的密码',
        '<p>你好，' . $name . '：</p>'
        . '<p>我们收到了重置 ' . $site . ' 账号密码的请求。请点击下面的按钮设置新密码，链接 <strong>1 小时</strong>内有效。</p>'
        . '<p>如果你没有申请找回密码，请忽略此邮件，原密码不会被修改。</p>',
        '重置密码',
        $url,
        '为了账号安全，请不要把此链接转发给他人。'
    );
    $text = "你好，{$user['nick']}：\n\n我们收到了重置 {$site} 密码的请求。\n请在 1 小时内打开以下链接设置新密码：\n{$url}\n\n如果这不是你的操作，请忽略此邮件。";
    return send_system_mail((string)$user['email'], '重置你的 ' . mail_site_name() . ' 密码', $html, $text);
}

/**
 * 发送 SMTP 邮件
 * @return array{0:bool,1:string} [是否成功, 错误信息]
 */
function send_system_mail(string $to, string $subject, string $html, string $text = ''): array
{
    if (!mail_service_available()) {
        return [false, '邮件服务未启用或配置不完整（请先在后台填写 SMTP 服务器和发件邮箱）'];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [false, '收件邮箱格式不正确'];
    }

    $cfg = mail_smtp_config();
    $timeout = 10;
    $hostname = (string)($_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost');
    $hostname = preg_replace('/[^A-Za-z0-9.\-]/', '', $hostname) ?: 'localhost';

    $sslOpts = [
        'verify_peer'       => $cfg['verify_peer'],
        'verify_peer_name'  => $cfg['verify_peer'],
        'peer_name'         => $cfg['peer_name'] !== '' ? $cfg['peer_name'] : $cfg['host'],
        'SNI_enabled'       => true,
        'allow_self_signed' => !$cfg['verify_peer'],
    ];
    $context = stream_context_create(['ssl' => $sslOpts]);
    $remote = ($cfg['secure'] === 'ssl' ? 'ssl://' : '') . $cfg['host'] . ':' . $cfg['port'];
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return [false, '无法连接 SMTP 服务器：' . ($errstr !== '' ? $errstr : '未知错误') . ' (' . (int)$errno . ')'];
    }
    stream_set_timeout($fp, $timeout);

    try {
        [$code, $lines] = airoj_smtp_read($fp);
        if ($code !== 220) throw new RuntimeException('SMTP 问候失败：' . airoj_smtp_last($lines));

        [$code, $lines] = airoj_smtp_cmd($fp, 'EHLO ' . $hostname);
        if ($code !== 250) throw new RuntimeException('SMTP EHLO 失败：' . airoj_smtp_last($lines));

        if ($cfg['secure'] === 'tls') {
            [$code, $lines] = airoj_smtp_cmd($fp, 'STARTTLS');
            if ($code !== 220) throw new RuntimeException('SMTP STARTTLS 失败：' . airoj_smtp_last($lines));
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS 握手失败，请检查加密方式或服务器证书');
            }
            [$code, $lines] = airoj_smtp_cmd($fp, 'EHLO ' . $hostname);
            if ($code !== 250) throw new RuntimeException('SMTP EHLO 失败：' . airoj_smtp_last($lines));
        }

        if ($cfg['user'] !== '') {
            [$code, $lines] = airoj_smtp_cmd($fp, 'AUTH LOGIN');
            if ($code === 334) {
                [$code, $lines] = airoj_smtp_cmd($fp, base64_encode($cfg['user']));
                if ($code !== 334) throw new RuntimeException('SMTP 用户名验证失败：' . airoj_smtp_last($lines));
                [$code, $lines] = airoj_smtp_cmd($fp, base64_encode($cfg['pass']));
            } elseif (in_array($code, [500, 502, 504], true)) {
                [$code, $lines] = airoj_smtp_cmd($fp, 'AUTH PLAIN ' . base64_encode("\0" . $cfg['user'] . "\0" . $cfg['pass']));
            }
            if ($code !== 235) throw new RuntimeException('SMTP 认证失败：' . airoj_smtp_last($lines));
        }

        [$code, $lines] = airoj_smtp_cmd($fp, 'MAIL FROM:<' . $cfg['from'] . '>');
        if (!in_array($code, [250], true)) throw new RuntimeException('SMTP MAIL FROM 失败：' . airoj_smtp_last($lines));

        [$code, $lines] = airoj_smtp_cmd($fp, 'RCPT TO:<' . $to . '>');
        if (!in_array($code, [250, 251], true)) throw new RuntimeException('SMTP RCPT TO 失败：' . airoj_smtp_last($lines));

        [$code, $lines] = airoj_smtp_cmd($fp, 'DATA');
        if ($code !== 354) throw new RuntimeException('SMTP DATA 失败：' . airoj_smtp_last($lines));

        $data = airoj_mail_build($cfg['from'], $cfg['from_name'], $to, $subject, $html, $text);
        $data = preg_replace('/^\./m', '..', $data);
        fwrite($fp, $data . "\r\n.\r\n");
        [$code, $lines] = airoj_smtp_read($fp);
        if ($code !== 250) throw new RuntimeException('SMTP 投递失败：' . airoj_smtp_last($lines));

        airoj_smtp_cmd($fp, 'QUIT');
        fclose($fp);
        return [true, ''];
    } catch (Throwable $e) {
        @fclose($fp);
        return [false, $e->getMessage()];
    }
}

/** 读取一行或多行 SMTP 响应，返回 [状态码, 行数组] */
function airoj_smtp_read($fp): array
{
    $lines = [];
    $code = 0;
    while (!feof($fp)) {
        $line = fgets($fp, 2048);
        if ($line === false) {
            $meta = stream_get_meta_data($fp);
            if (!empty($meta['timed_out'])) throw new RuntimeException('SMTP 读取超时');
            break;
        }
        $line = rtrim($line, "\r\n");
        $lines[] = $line;
        if (preg_match('/^(\d{3})([ -])/', $line, $m)) {
            $code = (int)$m[1];
            if ($m[2] === ' ') break;
        }
    }
    return [$code, $lines];
}

function airoj_smtp_cmd($fp, string $cmd): array
{
    fwrite($fp, $cmd . "\r\n");
    return airoj_smtp_read($fp);
}

function airoj_smtp_last(array $lines): string
{
    $last = end($lines);
    return is_string($last) ? mb_substr($last, 0, 200) : '无响应';
}

/** 组装 MIME 邮件（multipart/alternative） */
function airoj_mail_build(string $from, string $fromName, string $to, string $subject, string $html, string $text): string
{
    $eol = "\r\n";
    $boundary = '=_AirOJ_' . bin2hex(random_bytes(12));
    if ($text === '') $text = trim(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $html)));

    $encName = mb_encode_mimeheader($fromName, 'UTF-8', 'B');
    $encSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B');
    $domain = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'airoj.local')) ?: 'airoj.local';

    $headers = [
        'Date: ' . date('r'),
        'From: ' . $encName . ' <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: ' . $encSubject,
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: AirOJ',
    ];

    $body = '--' . $boundary . $eol
        . 'Content-Type: text/plain; charset=UTF-8' . $eol
        . 'Content-Transfer-Encoding: base64' . $eol . $eol
        . chunk_split(base64_encode($text), 76, $eol)
        . '--' . $boundary . $eol
        . 'Content-Type: text/html; charset=UTF-8' . $eol
        . 'Content-Transfer-Encoding: base64' . $eol . $eol
        . chunk_split(base64_encode($html), 76, $eol)
        . '--' . $boundary . '--' . $eol;

    return implode($eol, $headers) . $eol . $eol . $body;
}

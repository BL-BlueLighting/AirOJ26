<?php
$title = '邮箱验证';
$error = '';
$notice = '';
$show_resend = false;
$verify_enabled = email_verification_enabled();
$email = $user['email'] ?? '';

$token = trim((string)($_GET['token'] ?? ''));
if ($token !== '') {
    if (!$verify_enabled) {
        $error = '邮箱验证功能当前未开启。';
        $show_resend = true;
    } elseif (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $error = '验证链接无效或已过期。';
        $show_resend = true;
    } else {
        $row = Database::fetchOne(
            "SELECT * FROM users WHERE email_verify_token=? LIMIT 1",
            [mail_token_hash($token)]
        );
        if (!$row || (int)($row['email_verify_expires'] ?? 0) < time()) {
            $error = '验证链接无效或已过期，请重新发送验证邮件。';
            $show_resend = true;
        } else {
            Database::exec(
                "UPDATE users SET email_verified=1, email_verify_token='', email_verify_expires=0, email_verify_sent_at=0 WHERE user_id=?",
                [$row['user_id']]
            );
            header('Location: /login.php?verified=1');
            exit;
        }
    }
} else {
    $show_resend = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $show_resend = true;
    $email = trim((string)($_POST['email'] ?? ''));
    if (!$verify_enabled) {
        $error = '邮箱验证功能当前未开启。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址。';
    } elseif (!turnstile_verify($_POST['cf-turnstile-response'] ?? '')) {
        $error = '人机验证失败，请重试。';
    } else {
        // 无论邮箱是否存在都返回同样的提示，避免暴露账号信息
        $row = Database::fetchOne(
            "SELECT * FROM users WHERE LOWER(email)=LOWER(?) AND email_verified=0 LIMIT 1",
            [$email]
        );
        if ($row && (int)($row['email_verify_sent_at'] ?? 0) <= time() - 60) {
            $raw = mail_token();
            Database::exec(
                "UPDATE users SET email_verify_token=?, email_verify_expires=?, email_verify_sent_at=? WHERE user_id=?",
                [mail_token_hash($raw), time() + 86400, time(), $row['user_id']]
            );
            [$sent, $mailErr] = send_verification_email($row, $raw);
            if (!$sent) {
                error_log('[AirOJ mail] resend verification failed for ' . $row['user_id'] . ': ' . $mailErr);
            }
        }
        $notice = '如果该邮箱对应的账号尚未验证，我们已发送新的验证邮件。请检查收件箱和垃圾邮件。';
    }
}

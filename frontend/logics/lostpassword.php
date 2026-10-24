<?php
$title = '找回密码';
$error = '';
$notice = '';
$enabled = password_reset_enabled();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $enabled) {
    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址。';
    } elseif (!turnstile_verify($_POST['cf-turnstile-response'] ?? '')) {
        $error = '人机验证失败，请重试。';
    } else {
        // 无论邮箱是否存在都返回相同提示，避免暴露账号信息
        $row = Database::fetchOne(
            "SELECT * FROM users WHERE LOWER(email)=LOWER(?) AND email_verified=1 LIMIT 1",
            [$email]
        );
        if ($row && (int)($row['password_reset_expires'] ?? 0) <= time() + 3600 - 60) {
            $raw = mail_token();
            Database::exec(
                "UPDATE users SET password_reset_token=?, password_reset_expires=? WHERE user_id=?",
                [mail_token_hash($raw), time() + 3600, $row['user_id']]
            );
            [$sent, $mailErr] = send_password_reset_email($row, $raw);
            if (!$sent) {
                error_log('[AirOJ mail] password reset email failed for ' . $row['user_id'] . ': ' . $mailErr);
            }
        }
        $notice = '如果该邮箱已注册并完成验证，重置密码的邮件已发送。请检查收件箱和垃圾邮件。';
    }
}

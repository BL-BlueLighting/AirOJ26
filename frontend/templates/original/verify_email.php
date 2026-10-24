<div class="card" style="max-width:560px;margin:40px auto;padding:28px">
    <h2><i class="fa-solid fa-envelope-circle-check"></i> 邮箱验证</h2>

    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <?php if ($show_resend && $verify_enabled): ?>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:14px">
        输入注册时使用的邮箱地址，我们会重新发送验证链接。验证链接 24 小时内有效。
    </p>
    <form method="post">
        <input type="hidden" name="action" value="resend">
        <div class="form-group">
            <label>邮箱地址</label>
            <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($email) ?>" required autocomplete="email">
        </div>
        <?= turnstile_widget() ?>
        <button type="submit" class="btn btn-primary">发送验证邮件</button>
    </form>
    <?php elseif (!$verify_enabled): ?>
    <p style="color:var(--text-secondary);font-size:13px">管理员尚未开启邮箱验证功能，可直接返回登录。</p>
    <?php endif; ?>

    <p style="margin-top:18px;font-size:13px"><a href="/login.php">返回登录</a></p>
</div>

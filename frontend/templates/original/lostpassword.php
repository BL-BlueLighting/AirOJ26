<div class="card" style="max-width:560px;margin:40px auto;padding:28px">
    <h2><i class="fa-solid fa-key"></i> 找回密码</h2>

    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <?php if ($enabled): ?>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:14px">
        输入账号绑定的邮箱地址，我们会发送一封包含重置链接的邮件。链接 1 小时内有效。
    </p>
    <form method="post">
        <div class="form-group">
            <label>邮箱地址</label>
            <input type="email" name="email" class="form-input" required autocomplete="email">
        </div>
        <?= turnstile_widget() ?>
        <button type="submit" class="btn btn-primary">发送重置邮件</button>
    </form>
    <?php else: ?>
    <p style="color:var(--text-secondary);font-size:13px">找回密码功能当前未开启，请联系管理员处理。</p>
    <?php endif; ?>

    <p style="margin-top:18px;font-size:13px"><a href="/login.php">返回登录</a></p>
</div>

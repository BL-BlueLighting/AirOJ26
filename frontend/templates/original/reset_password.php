<div class="card" style="max-width:560px;margin:40px auto;padding:28px">
    <h2><i class="fa-solid fa-lock-open"></i> 重置密码</h2>

    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <?php if ($valid): ?>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:14px">
        为账号 <strong><?= htmlspecialchars($target['user_id']) ?></strong> 设置新密码。重置成功后需要重新登录。
    </p>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="form-group">
            <label>新密码</label>
            <input type="password" name="password" class="form-input" minlength="6" required autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>确认新密码</label>
            <input type="password" name="password_confirm" class="form-input" minlength="6" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">设置新密码</button>
    </form>
    <?php else: ?>
    <p style="color:var(--text-secondary);font-size:13px">
        请重新申请找回密码，或联系管理员。
    </p>
    <?php endif; ?>

    <p style="margin-top:18px;font-size:13px">
        <a href="/lostpassword.php">重新申请找回密码</a>
        <span style="margin:0 8px;color:var(--border)">·</span>
        <a href="/login.php">返回登录</a>
    </p>
</div>

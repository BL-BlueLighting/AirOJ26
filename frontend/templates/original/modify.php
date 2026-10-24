<div class="page-header"><h1><i class="fa-solid fa-gear"></i> 修改资料</h1></div>
<div class="auth-card" style="max-width:500px">
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>用户名</label><input type="text" class="form-input" value="<?= htmlspecialchars($user['user_id']) ?>" disabled></div>
        <div class="form-group"><label>昵称</label><input type="text" name="nick" class="form-input" value="<?= htmlspecialchars($user['nick'] ?? '') ?>"></div>
        <div class="form-group"><label>邮箱</label><input type="email" name="email" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>"></div>
        <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">
        <div class="form-group"><label>原密码（修改密码时填写）</label><input type="password" name="password_old" class="form-input" autocomplete="current-password"></div>
        <div class="form-group"><label>新密码</label><input type="password" name="password_new" class="form-input" autocomplete="new-password"></div>
        <button type="submit" class="btn btn-primary btn-block">保存修改</button>
    </form>
</div>

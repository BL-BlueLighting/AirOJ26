<div class="page-header">
    <h1><i class="fa-solid fa-gear"></i> 管理团队：<?=htmlspecialchars($team['name'])?></h1>
    <a href="/team.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回</a>
</div>

<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<?php if ($is_owner || $is_admin): ?>
<div class="card">
    <h2><i class="fa-solid fa-pen-to-square"></i> 团队信息</h2>
    <form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="edit">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:14px">
            <?php if (!empty($team['avatar'])): ?>
            <img src="/<?=htmlspecialchars($team['avatar'])?>" alt="" style="width:64px;height:64px;border-radius:16px;object-fit:cover">
            <?php else: ?>
            <span style="width:64px;height:64px;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;background:var(--accent-bg);color:var(--accent);font-size:24px;font-weight:700"><?=htmlspecialchars(mb_substr($team['name'],0,1))?></span>
            <?php endif; ?>
            <div class="form-group" style="margin:0;flex:1">
                <label>团队头像（≤2MB）</label>
                <input type="file" name="avatar" class="form-input" accept="image/png,image/jpeg,image/gif,image/webp">
            </div>
        </div>
        <div class="form-group"><label>团队名称</label><input type="text" name="name" class="form-input" value="<?=htmlspecialchars($team['name'])?>" required></div>
        <div class="form-group"><label>短介绍</label><textarea name="description" class="form-input" style="min-height:80px" maxlength="300"><?=htmlspecialchars($team['description'] ?? '')?></textarea></div>
        <button type="submit" class="btn btn-primary">保存</button>
    </form>
</div>

<div class="card">
    <h2><i class="fa-solid fa-certificate"></i> 团队认证</h2>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
        当前状态：<strong style="color:<?= $team['verified'] ? 'var(--green)' : 'var(--text-muted)' ?>"><?= $team['verified'] ? '✅ 已认证' : $status_text ?></strong>
        <?php if (!empty($team['verify_name'])): ?>
        <br>认证名称：<strong><?=htmlspecialchars($team['verify_name'])?></strong>
        <?php endif; ?>
        <?php if ($verify_status === 'pending'): ?>
        <br>申请理由：<?=htmlspecialchars($team['verify_reason'] ?? '')?>
        <?php endif; ?>
    </p>
    <?php if (!$team['verified'] && $verify_status !== 'pending'): ?>
    <form method="post"><input type="hidden" name="action" value="apply_verify">
        <div class="form-group"><label>认证名称 *</label>
            <input type="text" name="verify_name" class="form-input" maxlength="100" required
                   value="<?=htmlspecialchars($team['verify_name'] ?? '')?>" placeholder="填写希望认证的组织 / 团队正式名称"></div>
        <div class="form-group"><label>申请理由 *</label>
            <textarea name="verify_reason" class="form-input" style="min-height:100px" required placeholder="介绍团队情况、成员构成、代表成绩等，便于管理员审核"></textarea></div>
        <button type="submit" class="btn btn-primary">提交认证申请</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2><i class="fa-solid fa-users"></i> 成员管理 (<?=count($members)?>)</h2>
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px">
    <?php foreach ($members as $m): ?>
    <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--bg-secondary);border-radius:var(--radius);font-size:13px">
        <?= user_role_html($m['user_id']) ?>
        <span style="color:var(--text-muted);font-size:12px">(<?=$m['role'] === 'owner' ? '所有者' : '成员'?>)</span>
        <?php if ($is_owner && $m['user_id'] !== $uid): ?>
        <form method="post" style="margin-left:auto;display:inline"><input type="hidden" name="action" value="transfer"><input type="hidden" name="target" value="<?=$m['user_id']?>"><button type="submit" class="btn btn-outline btn-xs" onclick="return confirm('确定把团队转让给该成员？')">转让</button></form>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="kick"><input type="hidden" name="target" value="<?=$m['user_id']?>"><button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('确定踢出该成员？')">踢出</button></form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <form method="post" class="form-row"><input type="hidden" name="action" value="invite">
        <div class="form-group" style="flex:1"><input type="text" name="target" class="form-input" placeholder="输入用户名邀请成员"></div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> 邀请</button>
    </form>
</div>

<?php if ($apps): ?>
<div class="card">
    <h2><i class="fa-solid fa-envelope"></i> 待处理的加入申请 (<?=count($apps)?>)</h2>
    <?php foreach ($apps as $a): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:8px 12px;background:var(--bg-secondary);border-radius:var(--radius);margin-bottom:6px">
        <?= user_role_html($a['user_id']) ?>
        <span style="color:var(--text-muted);font-size:12px;flex:1"><?=htmlspecialchars($a['reason'] ?: '无留言')?></span>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="approve"><input type="hidden" name="target" value="<?=$a['user_id']?>"><button type="submit" class="btn btn-success btn-xs">通过</button></form>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="reject"><input type="hidden" name="target" value="<?=$a['user_id']?>"><button type="submit" class="btn btn-danger btn-xs">拒绝</button></form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($is_owner || $is_admin): ?>
<div class="card">
    <h2><i class="fa-solid fa-triangle-exclamation"></i> 危险操作</h2>
    <form method="post" style="display:inline-block">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('解散团队将删除所有成员与申请数据，确定？')">解散团队</button>
    </form>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card"><p style="color:var(--text-muted)">只有团队所有者可以管理团队。</p></div>
<?php endif; ?>

<?php if ($is_admin): ?>
<div class="card">
    <h2><i class="fa-solid fa-key"></i> 管理员操作</h2>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:10px">
        认证申请：<strong><?=$status_text?></strong>
        <?php if ($verify_status === 'pending'): ?><br>理由：<?=htmlspecialchars($team['verify_reason'] ?? '')?><?php endif; ?>
    </p>
    <form method="post" style="display:inline-block;margin-right:8px">
        <input type="hidden" name="action" value="<?=$team['verified']?'unverify':'verify'?>">
        <button type="submit" class="btn btn-<?=$team['verified']?'danger':'success'?> btn-sm"><?=$team['verified']?'取消认证':'通过认证'?></button>
    </form>
    <?php if ($verify_status === 'pending'): ?>
    <form method="post" style="display:inline-block">
        <input type="hidden" name="action" value="reject_verify">
        <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('驳回认证申请？')">驳回申请</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>


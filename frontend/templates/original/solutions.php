<div class="page-header">
    <h1><i class="fa-solid fa-pen-to-square"></i> 题解 — <?= $problem ? htmlspecialchars($problem['title']) : 'Problem #'.$pid ?></h1>
    <a href="/problem.php?id=<?= $pid ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回题目</a>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<?php if ($user): ?>
<div class="card" style="max-width:700px">
    <h2><i class="fa-solid fa-pen-to-square"></i> 撰写题解</h2>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">支持 Markdown 格式</p>
    <form method="post">
        <div class="form-group"><label>标题</label><input type="text" name="title" class="form-input" required placeholder="题解标题"></div>
        <div class="form-group"><label>内容</label><textarea name="content" class="form-input" style="min-height:250px" required placeholder="用 Markdown 编写题解..."></textarea></div>
        <button type="submit" name="submit_solution" class="btn btn-primary">发布题解</button>
    </form>
</div>
<?php endif; ?>

<?php if ($solutions): ?>
<div style="display:flex;flex-direction:column;gap:16px;margin-top:16px">
<?php foreach ($solutions as $s): ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h2 style="border:none;padding:0;margin:0;font-size:16px"><?= htmlspecialchars($s['title']) ?></h2>
        <div style="font-size:12px;color:var(--text-muted)"><?= user_role_html($s['user_id'], $s['nick']?:$s['user_id']) ?> · <?= $s['created_at'] ?></div>
    </div>
    <div class="mdui-prose" style="font-size:14px;line-height:1.7;overflow-x:auto"><?= $pd->text($s['content']) ?></div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty" style="margin-top:16px"><p>暂无题解。快来撰写第一篇题解吧！</p></div>
<?php endif; ?>


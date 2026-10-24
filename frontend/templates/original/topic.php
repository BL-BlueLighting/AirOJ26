<div class="page-header">
    <h1><i class="fa-solid fa-comments"></i> <?= htmlspecialchars($topic['title']) ?></h1>
    <a href="bbs.php<?= $topic['problem_id'] ? '?pid=' . (int)$topic['problem_id'] : '' ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回讨论区</a>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card" style="<?= htmlspecialchars($topic_style, ENT_COMPAT) ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
        <div style="font-size:13px;color:var(--text-secondary)">
            <?= user_role_html($topic['user_id']) ?> · <?= htmlspecialchars($topic['created_at']) ?>
            <?php if ($topic['problem_id']): ?>
            · 题目: <a href="problem.php?id=<?=$topic['problem_id']?>">#<?=$topic['problem_id']?> <?=htmlspecialchars($problem['title'] ?? '')?></a>
            <?php endif; ?>
        </div>
        <?php if ($user && ($uid === $topic['user_id'] || $is_admin)): ?>
        <form method="post" onsubmit="return confirm('确定删除该帖子？')">
            <button type="submit" name="delete_topic" value="1" class="btn btn-danger btn-xs">删除帖子</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="mdui-prose" style="font-size:14px;line-height:1.8;overflow-x:auto"><?= $pd->text((string)$topic['content']) ?></div>
</div>

<div class="section">
    <h2 class="section-title">回复（<?=count($replies)?>）</h2>
    <?php if ($replies): ?>
    <?php foreach ($replies as $i => $r): $reply_style = collectible_type_css($r['user_id'], 'bbs_style'); ?>
    <div class="card" id="r<?=$r['reply_id']?>" style="<?= htmlspecialchars($reply_style, ENT_COMPAT) ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px">
            <div style="font-size:13px;color:var(--text-secondary)">
                <strong style="color:var(--text-muted)">#<?= $i + 1 ?></strong>
                <?= user_role_html($r['user_id']) ?> · <?= htmlspecialchars($r['created_at']) ?>
            </div>
            <?php if ($user && ($uid === $r['user_id'] || $is_admin)): ?>
            <form method="post" onsubmit="return confirm('确定删除该回复？')">
                <button type="submit" name="delete_reply" value="<?=$r['reply_id']?>" class="btn btn-danger btn-xs">删除</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="mdui-prose" style="font-size:14px;line-height:1.8;overflow-x:auto"><?= $pd->text((string)$r['content']) ?></div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="empty"><p>还没有回复</p></div>
    <?php endif; ?>
</div>

<?php if ($user): ?>
<div class="card">
    <h2><i class="fa-solid fa-pen-to-square"></i> 发表回复</h2>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">支持 Markdown 格式</p>
    <form method="post">
        <div class="form-group"><textarea name="reply_content" class="form-input md-editor" style="min-height:140px" required placeholder="写下你的回复..."></textarea></div>
        <button type="submit" class="btn btn-primary">回复</button>
    </form>
</div>
<?php else: ?>
<div class="card" style="text-align:center">
    <a href="/login.php" class="btn btn-primary btn-sm">登录后回复</a>
</div>
<?php endif; ?>

<script src="<?= theme_asset('scripts', 'md-toolbar.js') ?>"></script>

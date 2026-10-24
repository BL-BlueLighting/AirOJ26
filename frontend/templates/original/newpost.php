<div class="page-header">
    <h1><i class="fa-solid fa-pen-to-square"></i> 发布新帖</h1>
    <a href="bbs.php<?= $pid ? '?pid=' . $pid : '' ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回讨论区</a>
</div>
<div class="card" style="max-width:700px">
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
        <?php if ($pid): ?>
        <input type="hidden" name="pid" value="<?=$pid?>">
        <div class="alert alert-info">本帖将发布在题目 <a href="problem.php?id=<?=$pid?>">#<?=$pid?> <?=htmlspecialchars($problem['title'] ?? '')?></a> 的讨论区</div>
        <?php endif; ?>
        <div class="form-group"><label>标题</label><input type="text" name="title" class="form-input" required></div>
        <div class="form-group"><label>内容（支持 Markdown）</label><textarea name="content" class="form-input md-editor" style="min-height:200px" required></textarea></div>
        <button type="submit" class="btn btn-primary">发布</button>
    </form>
</div>
<script src="<?= theme_asset('scripts', 'md-toolbar.js') ?>"></script>

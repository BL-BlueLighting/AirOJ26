<?php if (!$id): ?>
<div class="page-header"><h1><i class="fa-solid fa-bullhorn"></i> 公告</h1></div>

<?php if ($news_list): ?>
<div class="card" style="padding:0">
    <table class="news-list">
        <tbody>
        <?php foreach ($news_list as $i => $n): ?>
        <tr>
            <td class="news-idx"><?= $i + 1 ?></td>
            <td>
                <?php if (!empty($n['pinned'])): ?><span class="tag tag-gold">置顶</span> <?php endif; ?>
                <a href="/news.php?id=<?= $n['news_id'] ?>"><?= htmlspecialchars($n['title']) ?></a>
            </td>
            <td class="news-date"><?= htmlspecialchars(substr((string)$n['time'], 0, 10)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty"><div class="icon"><i class="fa-solid fa-bullhorn"></i></div><p>暂无公告</p></div>
<?php endif; ?>

<?php else: ?>
<?php if (!$news): ?>
<div class="empty"><div class="icon"><i class="fa-solid fa-bullhorn"></i></div><p>公告不存在</p></div>
<?php else: ?>
<div class="page-header">
    <h1><i class="fa-solid fa-bullhorn"></i> <?= htmlspecialchars($news['title']) ?></h1>
    <a href="/news.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 全部公告</a>
</div>

<div class="card">
    <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px">
        <?= htmlspecialchars($news['time']) ?>
        <?php if (!empty($news['pinned'])): ?> · <span class="tag tag-gold">置顶</span><?php endif; ?>
    </div>
    <div class="mdui-prose" style="font-size:14px;line-height:1.8;overflow-x:auto"><?= $pd->text((string)$news['content']) ?></div>
</div>

<div class="flex" style="justify-content:space-between">
    <?php if ($prev): ?><a href="/news.php?id=<?= $prev['news_id'] ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars($prev['title']) ?></a><?php else: ?><span></span><?php endif; ?>
    <?php if ($next): ?><a href="/news.php?id=<?= $next['news_id'] ?>" class="btn btn-outline btn-sm"><?= htmlspecialchars($next['title']) ?> <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

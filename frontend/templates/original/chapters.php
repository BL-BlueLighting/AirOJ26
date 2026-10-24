<div class="page-header"><h1><i class="fa-solid fa-book-open"></i> 章节</h1></div>

<?php if ($chapters): ?>
<div class="chapter-grid">
    <?php foreach ($chapters as $c):
        $cover = background_css((string)($c['background'] ?? ''));
        $desc  = trim(strip_tags($pd->text((string)($c['description'] ?? ''))));
    ?>
    <a class="chapter-card" href="/chapter.php?id=<?= (int)$c['chapter_id'] ?>">
        <div class="chapter-cover<?= $cover === '' ? ' chapter-cover-empty' : '' ?>" style="<?= htmlspecialchars($cover, ENT_COMPAT) ?>">
            <?php if ($cover === ''): ?><i class="fa-solid fa-book-open chapter-cover-icon"></i><?php endif; ?>
            <span class="chapter-cover-title"><?= htmlspecialchars($c['title']) ?></span>
        </div>
        <div class="chapter-body">
            <p class="chapter-desc"><?= htmlspecialchars(mb_substr($desc, 0, 90)) ?><?= mb_strlen($desc) > 90 ? '…' : '' ?></p>
            <div class="chapter-meta">
                <span><i class="fa-solid fa-user"></i> <?= user_role_html($c['creator']) ?></span>
                <span><i class="fa-solid fa-list-check"></i> <?= (int)$c['problem_count'] ?> 道题</span>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty"><div class="icon"><i class="fa-solid fa-book-open"></i></div><p>暂无章节</p></div>
<?php endif; ?>


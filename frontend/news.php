<?php
$title = '公告';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';

$id = intval($_GET['id'] ?? 0);
$news = Database::fetchOne("SELECT * FROM news WHERE news_id=?", [$id]);
?>
<div class="page-header"><h1>📢 公告</h1></div>
<?php if ($news): ?>
<div class="card">
    <h2><?= htmlspecialchars($news['title']) ?></h2>
    <p><?= nl2br(htmlspecialchars($news['content'] ?? '')) ?></p>
    <div style="margin-top:12px;font-size:12px;color:var(--text-muted)"><?= $news['time'] ?></div>
</div>
<?php else: ?><div class="empty"><p>公告不存在</p></div><?php endif; ?>
<?php require 'inc/footer.php'; ?>

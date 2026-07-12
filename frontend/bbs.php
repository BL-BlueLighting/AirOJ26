<?php
$title = '讨论区';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$total = Database::fetchOne("SELECT COUNT(*) AS c FROM news")['c'] ?? 0;
$posts = Database::fetchAll("SELECT * FROM news ORDER BY news_id DESC LIMIT ? OFFSET ?", [$limit, ($page-1)*$limit]);
$maxP = max(1, ceil($total/$limit));
$user = $_SESSION['airoj_user'] ?? null;
?>
<div class="page-header">
    <h1>💬 讨论区</h1>
    <?php if ($user): ?><a href="newpost.php" class="btn btn-primary btn-sm">发帖</a><?php endif; ?>
</div>

<?php if ($posts): ?>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th>标题</th><th>时间</th></tr></thead>
<tbody><?php foreach ($posts as $p): ?>
<tr><td><a href="news.php?id=<?=$p['news_id']?>"><?=htmlspecialchars($p['title'])?></a></td>
<td style="color:var(--text-muted)"><?=$p['time']?></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<?php else: ?><div class="empty"><p>暂无帖子</p></div><?php endif; ?>

<?php if ($maxP > 1): ?><div class="pagination"><?php for($i=1;$i<=$maxP;$i++): ?><a href="?page=<?=$i?>" class="page-link <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
<?php require 'inc/footer.php'; ?>

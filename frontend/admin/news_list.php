<?php
$title = 'news_list';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

// 置顶 / 取消置顶
if (isset($_GET['pin'])) {
    Database::exec("UPDATE news SET pinned=1 WHERE news_id=?", [intval($_GET['pin'])]);
    header('Location: news_list.php'); exit;
}
if (isset($_GET['unpin'])) {
    Database::exec("UPDATE news SET pinned=0 WHERE news_id=?", [intval($_GET['unpin'])]);
    header('Location: news_list.php'); exit;
}

$news = Database::fetchAll("SELECT * FROM news ORDER BY pinned DESC, news_id DESC");
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1><i class="fa-solid fa-bullhorn"></i> 公告管理</h1><a href="news_add.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> 添加公告</a></div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th style="width:60px">ID</th><th>标题</th><th style="width:170px">时间</th><th style="width:90px">置顶</th><th style="width:160px">操作</th></tr></thead>
<tbody><?php foreach ($news as $n): ?>
<tr>
<td><?=$n['news_id']?></td>
<td><a href="../news.php?id=<?=$n['news_id']?>" target="_blank"><?=htmlspecialchars($n['title'])?></a></td>
<td><?=$n['time']?></td>
<td><?= !empty($n['pinned']) ? '<span class="tag tag-gold"><i class="fa-solid fa-thumbtack"></i> 已置顶</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
<td>
    <?php if (!empty($n['pinned'])): ?>
    <a href="?unpin=<?=$n['news_id']?>" class="btn btn-outline btn-xs">取消置顶</a>
    <?php else: ?>
    <a href="?pin=<?=$n['news_id']?>" class="btn btn-outline btn-xs">置顶</a>
    <?php endif; ?>
    <a href="news_del.php?id=<?=$n['news_id']?>" class="btn btn-danger btn-xs" onclick="return confirm('确定删除？')">删除</a>
</td>
</tr>
<?php endforeach; ?></tbody></table></div></div>
<?php require __DIR__ . '/../inc/footer.php'; ?>

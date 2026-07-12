<?php
$title = 'news_list';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$news = Database::fetchAll("SELECT * FROM news ORDER BY news_id DESC");
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1>📢 公告管理</h1><a href="news_add.php" class="btn btn-primary btn-sm">➕ 添加公告</a></div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th>ID</th><th>标题</th><th>时间</th><th>操作</th></tr></thead>
<tbody><?php foreach ($news as $n): ?>
<tr><td><?=$n['news_id']?></td><td><?=htmlspecialchars($n['title'])?></td><td><?=$n['time']?></td><td><a href="news_del.php?id=<?=$n['news_id']?>" class="btn btn-danger btn-xs" onclick="return confirm('确定删除？')">删除</a></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<?php require __DIR__ . '/../inc/footer.php'; ?>


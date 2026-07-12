<?php
$title = '比赛排名';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';

$cid = intval($_GET['cid'] ?? 0);
$contest = Database::fetchOne("SELECT * FROM contest WHERE contest_id=?", [$cid]);
if (!$contest) { echo '<div class="empty"><p>比赛不存在</p></div>'; require 'inc/footer.php'; exit; }
?>
<div class="page-header"><h1>🏁 <?= htmlspecialchars($contest['title']) ?></h1></div>
<div class="card">
    <p>开始: <?= $contest['start_time'] ?> &nbsp;|&nbsp; 结束: <?= $contest['end_time'] ?></p>
</div>
<div class="empty"><p>比赛排名尚未实现，敬请期待</p></div>
<?php require 'inc/footer.php'; ?>

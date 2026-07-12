<?php
$title = 'judge_list';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$total = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions")['c'] ?? 0;
$rows = Database::fetchAll("SELECT * FROM submissions ORDER BY id DESC LIMIT ? OFFSET ?", [$limit, ($page-1)*$limit]);
$maxP = max(1, ceil($total/$limit));
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1>⚡ 评测队列</h1></div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th>#</th><th>题目</th><th>用户</th><th>语言</th><th>状态</th><th>分数</th><th>操作</th></tr></thead>
<tbody><?php foreach ($rows as $r): ?>
<tr><td><?=$r['id']?></td><td><a href="../problem.php?id=<?=$r['problem_id']?>"><?=$r['problem_id']?></a></td><td><?=htmlspecialchars($r['user_id']?:'')?></td><td><?=$r['judge_lang']?></td><td><span class="badge badge-<?=$r['status']?>"><?=$r['status']?></span></td><td style="font-weight:600;color:<?=($r['score']??0)>=100?'var(--green)':'var(--red)'?>"><?=$r['score']??'-'?></td><td><a href="../result.php?id=<?=$r['id']?>" class="btn btn-primary btn-xs">查看</a></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<?php if($maxP>1):?><div class="pagination"><?php for($i=1;$i<=$maxP;$i++):?><a href="?page=<?=$i?>" class="page-link <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor;?></div><?php endif;?>
<?php require __DIR__ . '/../inc/footer.php'; ?>


<?php
$title = '源代码';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';

$id = intval($_GET['id'] ?? 0);
$row = Database::fetchOne("SELECT * FROM submissions WHERE id=?", [$id]);
if (!$row) { echo '<div class="empty"><p>提交记录不存在</p></div>'; require 'inc/footer.php'; exit; }
?>
<div class="page-header"><h1>📄 查看源代码 #<?= $id ?></h1></div>
<div class="card">
    <div style="margin-bottom:12px;color:var(--text-secondary);font-size:13px">
        题目: <a href="problem.php?id=<?= $row['problem_id'] ?>"><?= $row['problem_id'] ?></a>
        · 语言: <?= $row['judge_lang'] ?>
        · 状态: <span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] ?></span>
    </div>
    <div class="code-block"><?= htmlspecialchars($row['code']) ?></div>
</div>
<?php require 'inc/footer.php'; ?>

<div class="page-header"><h1><i class="fa-solid fa-file-lines"></i> 查看源代码 #<?= $id ?></h1></div>
<div class="card">
    <div style="margin-bottom:12px;color:var(--text-secondary);font-size:13px">
        题目: <a href="/problem.php?id=<?= $row['problem_id'] ?>"><?= $row['problem_id'] ?></a>
        · 语言: <?= $row['judge_lang'] ?>
        · 状态: <span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] ?></span>
        · 提交者: <?= user_role_html($row['user_id']) ?>
    </div>
    <div class="code-block"><?= htmlspecialchars($row['code']) ?></div>
</div>

<?php
$title = '源代码';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();

$id = intval($_GET['id'] ?? 0);
$row = Database::fetchOne("SELECT * FROM submissions WHERE id=?", [$id]);
$user = $_SESSION['airoj_user'] ?? null;

if (!$row) {
    require 'inc/header.php';
    echo '<div class="empty"><p>提交记录不存在</p></div>';
    require 'inc/footer.php';
    exit;
}

// 仅提交者本人、管理员或拥有 source_viewer 权限的用户可查看
$allowed = $user && (
    (string)$user['user_id'] === (string)$row['user_id']
    || user_can_view_all_sources($user)
);

if (!$allowed) {
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    http_response_code(403);
    require 'inc/header.php'; ?>
    <div class="page-header"><h1><i class="fa-solid fa-lock"></i> 无权查看</h1></div>
    <div class="card">
        <div class="empty">
            <div class="icon"><i class="fa-solid fa-lock"></i></div>
            <p>你没有权限查看该提交的源代码</p>
            <p style="margin-top:8px;color:var(--text-muted);font-size:13px">只有提交者本人、管理员或拥有 <code>source_viewer</code> 权限的用户可以查看。</p>
        </div>
    </div>
    <?php require 'inc/footer.php';
    exit;
}

require 'inc/header.php'; ?>
<div class="page-header"><h1><i class="fa-solid fa-file-lines"></i> 查看源代码 #<?= $id ?></h1></div>
<div class="card">
    <div style="margin-bottom:12px;color:var(--text-secondary);font-size:13px">
        题目: <a href="problem.php?id=<?= $row['problem_id'] ?>"><?= $row['problem_id'] ?></a>
        · 语言: <?= $row['judge_lang'] ?>
        · 状态: <span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] ?></span>
        · 提交者: <?= user_role_html($row['user_id']) ?>
    </div>
    <div class="code-block"><?= htmlspecialchars($row['code']) ?></div>
</div>
<?php require 'inc/footer.php'; ?>

<?php
$title = '题解';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
require_once 'inc/Parsedown.php';

$pid = intval($_GET['id'] ?? 0);
$user = $_SESSION['airoj_user'] ?? null;
$pd = new Parsedown();
$err = ''; $ok = '';

// 提交题解
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_solution'])) {
    if (!$user) { header('Location: /login.php'); exit; }
    $title_s = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title_s && $content) {
        Database::insert("INSERT INTO solutions (problem_id, user_id, title, content) VALUES (?,?,?,?)",
            [$pid, $user['user_id'], $title_s, $content]);
        $ok = '题解已发布';
    } else $err = '请填写标题和内容';
}

// 获取题解列表
$solutions = Database::fetchAll("SELECT s.*, u.nick FROM solutions s LEFT JOIN users u ON s.user_id=u.user_id WHERE s.problem_id=? ORDER BY s.created_at DESC", [$pid]);
$problem = Database::fetchOne("SELECT title FROM problem WHERE problem_id=?", [$pid]);
?>
<div class="page-header">
    <h1>📝 题解 — <?= $problem ? htmlspecialchars($problem['title']) : 'Problem #'.$pid ?></h1>
    <a href="/problem.php?id=<?= $pid ?>" class="btn btn-outline btn-sm">← 返回题目</a>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<?php if ($user): ?>
<div class="card" style="max-width:700px">
    <h2>✏️ 撰写题解</h2>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">支持 Markdown 格式</p>
    <form method="post">
        <div class="form-group"><label>标题</label><input type="text" name="title" class="form-input" required placeholder="题解标题"></div>
        <div class="form-group"><label>内容</label><textarea name="content" class="form-input" style="min-height:250px" required placeholder="用 Markdown 编写题解..."></textarea></div>
        <button type="submit" name="submit_solution" class="btn btn-primary">发布题解</button>
    </form>
</div>
<?php endif; ?>

<?php if ($solutions): ?>
<div style="display:flex;flex-direction:column;gap:16px;margin-top:16px">
<?php foreach ($solutions as $s): ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h2 style="border:none;padding:0;margin:0;font-size:16px"><?= htmlspecialchars($s['title']) ?></h2>
        <div style="font-size:12px;color:var(--text-muted)"><?= user_role_html($s['user_id'], $s['nick']?:$s['user_id']) ?> · <?= $s['created_at'] ?></div>
    </div>
    <div style="font-size:14px;color:var(--text-secondary);line-height:1.7;overflow-x:auto"><?= $pd->text($s['content']) ?></div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty" style="margin-top:16px"><p>暂无题解。快来撰写第一篇题解吧！</p></div>
<?php endif; ?>

<?php require 'inc/footer.php'; ?>

<?php
$title = '发帖';
require_once 'inc/config.php';
require_once 'inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (!isset($_SESSION['airoj_user'])) { header('Location: login.php'); exit; }

$pid = intval($_GET['pid'] ?? $_POST['pid'] ?? 0);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_t = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title_t !== '' && $content !== '') {
        $tid = Database::insert(
            "INSERT INTO bbs_topic (problem_id, user_id, title, content, reply_count) VALUES (?,?,?,?,0)",
            [$pid, $_SESSION['airoj_user']['user_id'], $title_t, $content]
        );
        header('Location: topic.php?id=' . $tid);
        exit;
    }
    $err = '请填写标题和内容';
}

$problem = $pid ? Database::fetchOne("SELECT title FROM problem WHERE problem_id=?", [$pid]) : null;
require 'inc/header.php';
?>
<div class="page-header">
    <h1><i class="fa-solid fa-pen-to-square"></i> 发布新帖</h1>
    <a href="bbs.php<?= $pid ? '?pid=' . $pid : '' ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回讨论区</a>
</div>
<div class="card" style="max-width:700px">
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
        <?php if ($pid): ?>
        <input type="hidden" name="pid" value="<?=$pid?>">
        <div class="alert alert-info">本帖将发布在题目 <a href="problem.php?id=<?=$pid?>">#<?=$pid?> <?=htmlspecialchars($problem['title'] ?? '')?></a> 的讨论区</div>
        <?php endif; ?>
        <div class="form-group"><label>标题</label><input type="text" name="title" class="form-input" required></div>
        <div class="form-group"><label>内容（支持 Markdown）</label><textarea name="content" class="form-input md-editor" style="min-height:200px" required></textarea></div>
        <button type="submit" class="btn btn-primary">发布</button>
    </form>
</div>
<script src="/js/md-toolbar.js?v=1"></script>
<?php require 'inc/footer.php'; ?>

<?php
$title = '发帖';
require_once 'inc/config.php';
require_once 'inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (!isset($_SESSION['airoj_user'])) { header('Location: login.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title && $content) {
        Database::insert("INSERT INTO news (title,content) VALUES (?,?)", [$title, $content]);
        header('Location: bbs.php');
        exit;
    }
    $err = '请填写标题和内容';
}
require 'inc/header.php';
?>
<div class="page-header"><h1>📝 发布新帖</h1></div>
<div class="card" style="max-width:700px">
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>标题</label><input type="text" name="title" class="form-input" required></div>
        <div class="form-group"><label>内容</label><textarea name="content" class="form-input" style="min-height:200px" required></textarea></div>
        <button type="submit" class="btn btn-primary">发布</button>
    </form>
</div>
<?php require 'inc/footer.php'; ?>

<?php
$title = 'news_add';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title && $content) {
        Database::insert("INSERT INTO news (title,content,pinned) VALUES (?,?,?)",
            [$title, $content, isset($_POST['pinned']) ? 1 : 0]);
        header('Location: news_list.php'); exit;
    }
}
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1><i class="fa-solid fa-pen-to-square"></i> 添加公告</h1><a href="news_list.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回</a></div>
<div class="card" style="max-width:700px">
    <form method="post">
        <div class="form-group"><label>标题</label><input type="text" name="title" class="form-input" required></div>
        <div class="form-group"><label>内容（支持 Markdown）</label><textarea name="content" class="form-input" style="min-height:200px" required></textarea></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="pinned" value="1"> <i class="fa-solid fa-thumbtack"></i> 置顶显示</label></div>
        <button type="submit" class="btn btn-primary">发布</button>
    </form>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>


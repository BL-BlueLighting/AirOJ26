<?php
$title = '帖子';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
require_once 'inc/Parsedown.php';

$id   = intval($_GET['id'] ?? 0);
$user = $_SESSION['airoj_user'] ?? null;
$pd   = new Parsedown();
$pd->setSafeMode(true);

$topic = Database::fetchOne("SELECT * FROM bbs_topic WHERE topic_id=?", [$id]);
if (!$topic) { echo '<div class="empty"><p>帖子不存在或已被删除</p></div>'; require 'inc/footer.php'; exit; }

$is_admin = $user ? (bool)Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$user['user_id']]) : false;
$uid      = $user['user_id'] ?? '';
$err      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 删除帖子
    if (isset($_POST['delete_topic'])) {
        if ($user && ($uid === $topic['user_id'] || $is_admin)) {
            Database::exec("DELETE FROM bbs_reply WHERE topic_id=?", [$id]);
            Database::exec("DELETE FROM bbs_topic WHERE topic_id=?", [$id]);
            header('Location: bbs.php');
            exit;
        }
        $err = '没有权限删除该帖子';
    }
    // 删除回复
    if (isset($_POST['delete_reply'])) {
        $rid = intval($_POST['delete_reply']);
        $reply = Database::fetchOne("SELECT * FROM bbs_reply WHERE reply_id=? AND topic_id=?", [$rid, $id]);
        if ($reply && $user && ($uid === $reply['user_id'] || $is_admin)) {
            Database::exec("DELETE FROM bbs_reply WHERE reply_id=?", [$rid]);
            Database::exec("UPDATE bbs_topic SET reply_count = MAX(reply_count - 1, 0) WHERE topic_id=?", [$id]);
        } else {
            $err = '没有权限删除该回复';
        }
    }
    // 回复
    if (isset($_POST['reply_content'])) {
        if (!$user) {
            header('Location: /login.php');
            exit;
        }
        $content = trim($_POST['reply_content']);
        if ($content !== '') {
            Database::insert("INSERT INTO bbs_reply (topic_id, user_id, content) VALUES (?,?,?)", [$id, $uid, $content]);
            Database::exec("UPDATE bbs_topic SET reply_count = reply_count + 1, last_reply = ? WHERE topic_id=?", [date('Y-m-d H:i:s'), $id]);
            header('Location: topic.php?id=' . $id);
            exit;
        }
        $err = '回复内容不能为空';
    }
    $topic = Database::fetchOne("SELECT * FROM bbs_topic WHERE topic_id=?", [$id]);
}

$replies = Database::fetchAll("SELECT * FROM bbs_reply WHERE topic_id=? ORDER BY reply_id ASC", [$id]);
$problem = $topic['problem_id'] ? Database::fetchOne("SELECT title FROM problem WHERE problem_id=?", [(int)$topic['problem_id']]) : null;
?>
<div class="page-header">
    <h1><i class="fa-solid fa-comments"></i> <?= htmlspecialchars($topic['title']) ?></h1>
    <a href="bbs.php<?= $topic['problem_id'] ? '?pid=' . (int)$topic['problem_id'] : '' ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回讨论区</a>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
        <div style="font-size:13px;color:var(--text-secondary)">
            <?= user_role_html($topic['user_id']) ?> · <?= htmlspecialchars($topic['created_at']) ?>
            <?php if ($topic['problem_id']): ?>
            · 题目: <a href="problem.php?id=<?=$topic['problem_id']?>">#<?=$topic['problem_id']?> <?=htmlspecialchars($problem['title'] ?? '')?></a>
            <?php endif; ?>
        </div>
        <?php if ($user && ($uid === $topic['user_id'] || $is_admin)): ?>
        <form method="post" onsubmit="return confirm('确定删除该帖子？')">
            <button type="submit" name="delete_topic" value="1" class="btn btn-danger btn-xs">删除帖子</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="mdui-prose" style="font-size:14px;line-height:1.8;overflow-x:auto"><?= $pd->text((string)$topic['content']) ?></div>
</div>

<div class="section">
    <h2 class="section-title">回复（<?=count($replies)?>）</h2>
    <?php if ($replies): ?>
    <?php foreach ($replies as $i => $r): ?>
    <div class="card" id="r<?=$r['reply_id']?>">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px">
            <div style="font-size:13px;color:var(--text-secondary)">
                <strong style="color:var(--text-muted)">#<?= $i + 1 ?></strong>
                <?= user_role_html($r['user_id']) ?> · <?= htmlspecialchars($r['created_at']) ?>
            </div>
            <?php if ($user && ($uid === $r['user_id'] || $is_admin)): ?>
            <form method="post" onsubmit="return confirm('确定删除该回复？')">
                <button type="submit" name="delete_reply" value="<?=$r['reply_id']?>" class="btn btn-danger btn-xs">删除</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="mdui-prose" style="font-size:14px;line-height:1.8;overflow-x:auto"><?= $pd->text((string)$r['content']) ?></div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="empty"><p>还没有回复</p></div>
    <?php endif; ?>
</div>

<?php if ($user): ?>
<div class="card">
    <h2><i class="fa-solid fa-pen-to-square"></i> 发表回复</h2>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">支持 Markdown 格式</p>
    <form method="post">
        <div class="form-group"><textarea name="reply_content" class="form-input md-editor" style="min-height:140px" required placeholder="写下你的回复..."></textarea></div>
        <button type="submit" class="btn btn-primary">回复</button>
    </form>
</div>
<?php else: ?>
<div class="card" style="text-align:center">
    <a href="/login.php" class="btn btn-primary btn-sm">登录后回复</a>
</div>
<?php endif; ?>

<script src="/js/md-toolbar.js?v=1"></script>
<?php require 'inc/footer.php'; ?>

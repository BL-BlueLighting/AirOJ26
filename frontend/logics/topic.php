<?php
$title = '帖子';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/Parsedown.php';

$id   = intval($_GET['id'] ?? 0);
$user = $_SESSION['airoj_user'] ?? null;
$pd   = new Parsedown();
$pd->setSafeMode(true);

$topic = Database::fetchOne("SELECT * FROM bbs_topic WHERE topic_id=?", [$id]);
if (!$topic) render_error('帖子不存在或已被删除');

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
            $rid = Database::insert("INSERT INTO bbs_reply (topic_id, user_id, content) VALUES (?,?,?)", [$id, $uid, $content]);
            Database::exec("UPDATE bbs_topic SET reply_count = reply_count + 1, last_reply = ? WHERE topic_id=?", [date('Y-m-d H:i:s'), $id]);
            plugin_do('Hook:Discuss/Reply.Send', [
                'reply_id' => $rid, 'topic_id' => $id, 'user_id' => $uid, 'content' => $content,
            ]);
            header('Location: topic.php?id=' . $id);
            exit;
        }
        $err = '回复内容不能为空';
    }
    $topic = Database::fetchOne("SELECT * FROM bbs_topic WHERE topic_id=?", [$id]);
}

$replies = Database::fetchAll("SELECT * FROM bbs_reply WHERE topic_id=? ORDER BY reply_id ASC", [$id]);
$problem = $topic['problem_id'] ? Database::fetchOne("SELECT title FROM problem WHERE problem_id=?", [(int)$topic['problem_id']]) : null;
$topic_style = collectible_type_css($topic['user_id'], 'bbs_style');

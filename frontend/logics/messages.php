<?php
/**
 * AirOJ — 站内信
 * 收件箱 / 发件箱 / 写站内信 / 查看与回复
 */
$title = '站内信';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/Parsedown.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (!isset($_SESSION['airoj_user'])) { header('Location: /login.php'); exit; }

$uid = $_SESSION['airoj_user']['user_id'];
$err = '';

/** POST 后重定向，避免刷新重复提交 */
function msg_redirect(string $url, string $flash = ''): void
{
    if ($flash !== '') $_SESSION['msg_flash'] = $flash;
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $to      = trim($_POST['to'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($to === '' || $subject === '' || $content === '') {
            $err = '收件人、标题和内容都不能为空';
        } elseif ($to === $uid) {
            $err = '不能给自己发站内信';
        } elseif (!Database::fetchOne("SELECT user_id FROM users WHERE user_id=?", [$to])) {
            $err = '收件人不存在';
        } elseif (mb_strlen($subject) > 100) {
            $err = '标题不能超过 100 个字符';
        } else {
            Database::insert("INSERT INTO messages (from_user,to_user,subject,content) VALUES (?,?,?,?)",
                [$uid, $to, $subject, $content]);
            msg_redirect('/messages.php?box=sent', '站内信已发送');
        }
    }

    if ($action === 'delete') {
        $mid = intval($_POST['id'] ?? 0);
        $m = Database::fetchOne("SELECT * FROM messages WHERE id=?", [$mid]);
        if ($m) {
            if ($m['to_user'] === $uid) {
                Database::exec("UPDATE messages SET to_deleted=1 WHERE id=?", [$mid]);
            } elseif ($m['from_user'] === $uid) {
                Database::exec("UPDATE messages SET from_deleted=1 WHERE id=?", [$mid]);
            }
        }
        msg_redirect('/messages.php' . (($_POST['box'] ?? '') === 'sent' ? '?box=sent' : ''), '已删除');
    }

    if ($action === 'mark_all') {
        Database::exec("UPDATE messages SET is_read=1 WHERE to_user=? AND to_deleted=0", [$uid]);
        msg_redirect('/messages.php', '已全部标记为已读');
    }
}

$ok = $_SESSION['msg_flash'] ?? '';
unset($_SESSION['msg_flash']);

$box     = ($_GET['box'] ?? 'inbox') === 'sent' ? 'sent' : 'inbox';
$id      = intval($_GET['id'] ?? 0);
$compose = isset($_GET['compose']);
$to_prefill = trim($_GET['to'] ?? '');
$sub_prefill = trim($_GET['subject'] ?? '');

// 查看单条
$view = null;
if ($id) {
    $m = Database::fetchOne("SELECT * FROM messages WHERE id=?", [$id]);
    $isRecipient = $m && $m['to_user'] === $uid && !$m['to_deleted'];
    $isSender    = $m && $m['from_user'] === $uid && !$m['from_deleted'];
    if (!$isRecipient && !$isSender) {
        header('Location: /messages.php');
        exit;
    }
    $view = $m;
    if ($isRecipient && !(int)$m['is_read']) {
        Database::exec("UPDATE messages SET is_read=1 WHERE id=?", [$id]);
        $view['is_read'] = 1;
    }
}
$bubble_style = $view ? collectible_type_css($view['from_user'], 'bubble') : '';

$page  = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
if (!$id && !$compose) {
    if ($box === 'sent') {
        $where  = "m.from_user=? AND m.from_deleted=0";
        $params = [$uid];
        $joins  = "LEFT JOIN users u ON u.user_id=m.to_user";
        $userCol = 'to_user';
    } else {
        $where  = "m.to_user=? AND m.to_deleted=0";
        $params = [$uid];
        $joins  = "LEFT JOIN users u ON u.user_id=m.from_user";
        $userCol = 'from_user';
    }
    $total  = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM messages m WHERE $where", $params)['c'] ?? 0);
    $msgs   = Database::fetchAll(
        "SELECT m.*, u.nick FROM messages m $joins WHERE $where ORDER BY m.id DESC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, ($page - 1) * $limit])
    );
    $maxP = max(1, ceil($total / $limit));
}


$pd = new Parsedown();
$pd->setSafeMode(true);

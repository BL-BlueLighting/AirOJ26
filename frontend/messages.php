<?php
/**
 * AirOJ — 站内信
 * 收件箱 / 发件箱 / 写站内信 / 查看与回复
 */
$title = '站内信';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
require_once 'inc/Parsedown.php';
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

require 'inc/header.php';

if ($view):
    $pd = new Parsedown();
    $pd->setSafeMode(true);
?>
<div class="page-header">
    <h1><i class="fa-solid fa-envelope-open-text"></i> <?= htmlspecialchars($view['subject']) ?></h1>
    <a href="/messages.php<?= $box === 'sent' ? '?box=sent' : '' ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回</a>
</div>

<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:13px;color:var(--text-secondary);margin-bottom:12px">
        <div>
            <?= user_role_html($view['from_user']) ?>
            <i class="fa-solid fa-arrow-right" style="margin:0 6px;color:var(--text-muted)"></i>
            <?= user_role_html($view['to_user']) ?>
        </div>
        <div style="color:var(--text-muted)"><?= htmlspecialchars($view['created_at']) ?></div>
    </div>
    <div class="mdui-prose" style="font-size:14px;line-height:1.8"><?= $pd->text((string)$view['content']) ?></div>
</div>

<div class="flex">
    <?php if ($view['from_user'] === $uid): ?>
    <span style="font-size:12px;color:var(--text-muted)">这是你发出的站内信</span>
    <?php else: ?>
    <a href="/messages.php?compose=1&to=<?= urlencode($view['from_user']) ?>&subject=<?= urlencode('Re: ' . $view['subject']) ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-reply"></i> 回复</a>
    <?php endif; ?>
    <form method="post" style="margin-left:auto" onsubmit="return confirm('确定删除这条站内信？')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
        <input type="hidden" name="box" value="<?= $box ?>">
        <button type="submit" class="btn btn-danger btn-sm">删除</button>
    </form>
</div>

<?php elseif ($compose): ?>
<div class="page-header">
    <h1><i class="fa-solid fa-pen-to-square"></i> 写站内信</h1>
    <a href="/messages.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回收件箱</a>
</div>
<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<div class="card" style="max-width:760px">
    <form method="post">
        <input type="hidden" name="action" value="send">
        <div class="form-group"><label>收件人（用户名）*</label>
            <input type="text" name="to" class="form-input" value="<?= htmlspecialchars($to_prefill) ?>" required></div>
        <div class="form-group"><label>标题 *</label>
            <input type="text" name="subject" class="form-input" value="<?= htmlspecialchars($sub_prefill) ?>" maxlength="100" required></div>
        <div class="form-group"><label>内容（支持 Markdown）*</label>
            <textarea name="content" class="form-input md-editor" style="min-height:180px" required></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> 发送</button>
    </form>
</div>
<script src="/js/md-toolbar.js?v=1"></script>

<?php else: ?>
<div class="page-header">
    <h1><i class="fa-solid fa-envelope"></i> 站内信</h1>
    <a href="/messages.php?compose=1" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen-to-square"></i> 写站内信</a>
</div>

<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="card" style="padding:0">
    <div class="msg-tabs">
        <a href="/messages.php" class="<?= $box === 'inbox' ? 'active' : '' ?>">收件箱</a>
        <a href="/messages.php?box=sent" class="<?= $box === 'sent' ? 'active' : '' ?>">发件箱</a>
        <?php if ($box === 'inbox' && $msgs): ?>
        <form method="post" style="margin-left:auto">
            <input type="hidden" name="action" value="mark_all">
            <button type="submit" class="btn btn-outline btn-xs">全部标为已读</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if ($msgs): ?>
    <div class="table-wrap"><table>
        <thead><tr>
            <th style="width:60px">状态</th>
            <th><?= $box === 'sent' ? '收件人' : '发件人' ?></th>
            <th>标题</th>
            <th style="width:160px">时间</th>
            <th style="width:80px">操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($msgs as $m): $unread = ($box === 'inbox' && !(int)$m['is_read']); ?>
        <tr<?= $unread ? ' style="font-weight:600"' : '' ?>>
            <td><?= $unread ? '<span class="tag tag-gold">未读</span>' : '<span style="color:var(--text-muted)">已读</span>' ?></td>
            <td><?= user_role_html($m[$userCol], $m['nick'] ?: $m[$userCol]) ?></td>
            <td><a href="/messages.php?id=<?= (int)$m['id'] ?>&box=<?= $box ?>"><?= htmlspecialchars($m['subject']) ?></a></td>
            <td style="color:var(--text-muted);font-size:12px"><?= htmlspecialchars($m['created_at']) ?></td>
            <td>
                <form method="post" onsubmit="return confirm('确定删除？')" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <input type="hidden" name="box" value="<?= $box ?>">
                    <button type="submit" class="btn btn-danger btn-xs">删除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?>
    <div class="empty"><div class="icon"><i class="fa-solid fa-envelope-open"></i></div>
        <p><?= $box === 'sent' ? '还没有发送过站内信' : '收件箱是空的' ?></p>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($maxP) && $maxP > 1): ?>
<div class="pagination"><?php for ($i = 1; $i <= $maxP; $i++): ?>
    <a href="?<?= $box === 'sent' ? 'box=sent&' : '' ?>page=<?=$i?>" class="page-link <?=$i === $page ? 'active' : ''?>"><?=$i?></a>
<?php endfor; ?></div>
<?php endif; ?>

<?php endif; ?>

<?php require 'inc/footer.php'; ?>

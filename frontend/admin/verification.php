<?php
$title = '个人认证审核';
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';
require_once __DIR__ . '/../include/mailer.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['act'] ?? '');
    $targetId = trim((string)($_POST['user_id'] ?? ''));
    $target = $targetId !== '' ? Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$targetId]) : null;

    if (!in_array($act, ['approve', 'reject', 'revoke'], true)) {
        $err = '无效的操作';
    } elseif (!$target) {
        $err = '用户不存在';
    } else {
        $content = trim((string)($target['personal_verify_content'] ?? ''));
        if ($act === 'approve') {
            if ($content === '') {
                $err = '该用户没有填写认证内容，无法通过';
            } else {
                Database::exec("UPDATE users SET personal_verify_status='approved' WHERE user_id=?", [$targetId]);
                [$sent, $mailErr] = send_admin_action_email($target, '个人认证已通过', ['认证内容：' . $content]);
                if ($mailErr !== '') error_log('[AirOJ mail] admin action notify failed: ' . $mailErr);
                $ok = '已通过该用户的个人认证';
            }
        } elseif ($act === 'reject') {
            Database::exec("UPDATE users SET personal_verify_status='rejected' WHERE user_id=?", [$targetId]);
            [$sent, $mailErr] = send_admin_action_email($target, '个人认证未通过', ['认证内容：' . ($content ?: '—')]);
            if ($mailErr !== '') error_log('[AirOJ mail] admin action notify failed: ' . $mailErr);
            $ok = '已驳回该用户的个人认证申请';
        } else {
            Database::exec("UPDATE users SET personal_verify_status='none' WHERE user_id=?", [$targetId]);
            [$sent, $mailErr] = send_admin_action_email($target, '个人认证已取消', ['认证内容：' . ($content ?: '—')]);
            if ($mailErr !== '') error_log('[AirOJ mail] admin action notify failed: ' . $mailErr);
            $ok = '已取消该用户的个人认证';
        }
    }
}

$rows = Database::fetchAll(
    "SELECT user_id, nick, personal_verify_status, personal_verify_content, personal_verify_reason, personal_verify_applied_at
     FROM users
     WHERE personal_verify_status IN ('pending','approved','rejected')
     ORDER BY CASE personal_verify_status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
              personal_verify_applied_at DESC, user_id ASC
     LIMIT 200"
);

$admin_current_group = 'user';
$admin_current_item  = 'user_verify';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1><i class="fa-solid fa-id-badge"></i> 个人认证审核</h1>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="alert alert-info" style="font-size:13px">
    通过后，用户名字旁会显示蓝标；其他用户将鼠标悬停在蓝标上即可看到认证内容。个人认证与称号、收藏品徽章互相独立。
</div>

<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr>
    <th style="width:160px">用户</th>
    <th>认证内容</th>
    <th>申请理由 / 证明材料</th>
    <th style="width:90px">状态</th>
    <th style="width:150px">申请时间</th>
    <th style="width:180px">操作</th>
</tr></thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">暂无个人认证申请</td></tr>
<?php else: foreach ($rows as $r):
    $st = (string)$r['personal_verify_status'];
    $statusText = ['pending' => '审核中', 'approved' => '已认证', 'rejected' => '未通过'][$st] ?? $st;
    $statusCls  = ['pending' => 'tag-gold', 'approved' => 'tag-green', 'rejected' => 'tag-red'][$st] ?? '';
?>
<tr>
    <td><?= user_role_html((string)$r['user_id'], $r['nick'] ?: null) ?></td>
    <td style="white-space:pre-wrap;word-break:break-word"><?= $r['personal_verify_content'] !== '' ? htmlspecialchars((string)$r['personal_verify_content']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
    <td style="font-size:12px;color:var(--text-secondary);white-space:pre-wrap;word-break:break-word"><?= htmlspecialchars((string)($r['personal_verify_reason'] ?? '')) ?: '<span style="color:var(--text-muted)">—</span>' ?></td>
    <td><span class="tag <?= $statusCls ?>"><?= $statusText ?></span></td>
    <td style="color:var(--text-muted);font-size:12px"><?= htmlspecialchars((string)($r['personal_verify_applied_at'] ?? '')) ?: '—' ?></td>
    <td>
        <?php if ($st === 'pending'): ?>
        <form method="post" style="display:inline"><input type="hidden" name="act" value="approve"><input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>"><button class="btn btn-success btn-xs">通过</button></form>
        <form method="post" style="display:inline"><input type="hidden" name="act" value="reject"><input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>"><button class="btn btn-danger btn-xs">驳回</button></form>
        <?php elseif ($st === 'approved'): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('取消该用户的个人认证？')"><input type="hidden" name="act" value="revoke"><input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>"><button class="btn btn-danger btn-xs">取消认证</button></form>
        <?php else: ?>
        <form method="post" style="display:inline"><input type="hidden" name="act" value="approve"><input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>"><button class="btn btn-success btn-xs">通过</button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('清除该用户的申请记录？')"><input type="hidden" name="act" value="revoke"><input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>"><button class="btn btn-outline btn-xs">清除</button></form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>

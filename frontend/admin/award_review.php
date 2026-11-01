<?php
$title = '奖项认证审核';
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';
require_once __DIR__ . '/../include/mailer.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$ok = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['act'] ?? '');
    $targetId = trim((string)($_POST['user_id'] ?? ''));
    $target = $targetId !== '' ? Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$targetId]) : null;
    if (!in_array($act, ['approve', 'reject', 'revoke'], true)) {
        $err = '无效的操作';
    } elseif (!$target) {
        $err = '用户不存在';
    } elseif ($act === 'approve') {
        $award = trim((string)($target['award_verify_award'] ?? ''));
        if (($target['frv_status'] ?? 'none') !== 'verified') {
            $err = '该用户尚未通过实人认证，不能通过奖项认证';
        } elseif ($award === '') {
            $err = '该用户没有奖项内容，无法通过';
        } else {
            Database::exec(
                "UPDATE users SET award_verify_status='approved', award_verify_approved_at=? WHERE user_id=?",
                [date('Y-m-d H:i:s'), $targetId]
            );
            [$sent, $mailErr] = send_admin_action_email($target, '奖项认证已通过', ['奖项：' . $award]);
            if ($mailErr !== '') error_log('[AirOJ mail] admin action notify failed: ' . $mailErr);
            $ok = '已通过该用户的奖项认证';
        }
    } elseif ($act === 'reject') {
        Database::exec("UPDATE users SET award_verify_status='rejected' WHERE user_id=?", [$targetId]);
        [$sent, $mailErr] = send_admin_action_email($target, '奖项认证未通过', ['奖项：' . ((string)($target['award_verify_award'] ?? '') ?: '—')]);
        if ($mailErr !== '') error_log('[AirOJ mail] admin action notify failed: ' . $mailErr);
        $ok = '已驳回该用户的奖项认证申请';
    } else {
        Database::exec(
            "UPDATE users SET award_verify_status='none', award_verify_source='', award_verify_award='',
                award_verify_oier_id=0, award_verify_reason='', award_verify_payload='',
                award_verify_applied_at='', award_verify_approved_at=''
             WHERE user_id=?",
            [$targetId]
        );
        [$sent, $mailErr] = send_admin_action_email($target, '奖项认证已取消');
        if ($mailErr !== '') error_log('[AirOJ mail] admin action notify failed: ' . $mailErr);
        $ok = '已取消该用户的奖项认证';
    }
}

$rows = Database::fetchAll(
    "SELECT user_id, nick, frv_status, award_verify_status, award_verify_source,
            award_verify_award, award_verify_reason, award_verify_payload,
            award_verify_applied_at, award_verify_approved_at
     FROM users
     WHERE award_verify_status <> 'none'
     ORDER BY CASE award_verify_status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
              award_verify_applied_at DESC, user_id ASC
     LIMIT 200"
);

$admin_current_group = 'verify';
$admin_current_item  = 'award_review';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1><i class="fa-solid fa-medal"></i> 奖项认证审核</h1>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="alert alert-info" style="font-size:13px">
    通过后用户名字旁会出现黄色奖项徽章。自动认证来自 OIerDb，人工认证由管理员审核；取消认证会移除黄标。
</div>

<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr>
    <th style="width:160px">用户</th>
    <th style="width:90px">来源</th>
    <th>奖项</th>
    <th>OIerDb / 申请材料</th>
    <th style="width:90px">状态</th>
    <th style="width:150px">时间</th>
    <th style="width:170px">操作</th>
</tr></thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">暂无奖项认证记录</td></tr>
<?php else: foreach ($rows as $r):
    $st = (string)$r['award_verify_status'];
    $stText = ['pending' => '审核中', 'approved' => '已认证', 'rejected' => '未通过'][$st] ?? $st;
    $stCls = ['pending' => 'tag-gold', 'approved' => 'tag-green', 'rejected' => 'tag-red'][$st] ?? '';
    $source = (string)($r['award_verify_source'] ?? '');
    $payload = json_decode((string)($r['award_verify_payload'] ?? ''), true);
    $recordText = '';
    if (is_array($payload)) {
        $parts = [];
        foreach (['contest', 'award', 'real_name', 'grade', 'school', 'province'] as $k) {
            $v = trim((string)($payload[$k] ?? ''));
            if ($v !== '') $parts[] = $v;
        }
        $recordText = implode(' · ', $parts);
    }
    $reason = trim((string)($r['award_verify_reason'] ?? ''));
?>
<tr>
    <td><?= user_role_html((string)$r['user_id'], $r['nick'] ?: null) ?></td>
    <td><span class="tag"><?= $source === 'auto' ? 'OIerDb' : ($source === 'manual' ? '人工' : '—') ?></span></td>
    <td style="word-break:break-word"><?= htmlspecialchars((string)($r['award_verify_award'] ?? '')) ?: '<span style="color:var(--text-muted)">—</span>' ?></td>
    <td style="font-size:12px;color:var(--text-secondary);white-space:pre-wrap;word-break:break-word">
        <?= $recordText !== '' ? htmlspecialchars($recordText) : '' ?>
        <?php if ($reason !== ''): ?><?= $recordText !== '' ? '<br>' : '' ?>申请材料：<?= htmlspecialchars($reason) ?><?php endif; ?>
    </td>
    <td><span class="tag <?= $stCls ?>"><?= $stText ?></span></td>
    <td style="font-size:12px;color:var(--text-muted)">
        申请：<?= htmlspecialchars((string)($r['award_verify_applied_at'] ?? '')) ?: '—' ?><br>
        通过：<?= htmlspecialchars((string)($r['award_verify_approved_at'] ?? '')) ?: '—' ?>
    </td>
    <td>
        <?php if ($st === 'pending'): ?>
        <form method="post" style="display:inline"><input type="hidden" name="act" value="approve"><input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>"><button class="btn btn-success btn-xs">通过</button></form>
        <form method="post" style="display:inline"><input type="hidden" name="act" value="reject"><input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>"><button class="btn btn-danger btn-xs">驳回</button></form>
        <?php elseif ($st === 'approved'): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('取消该用户的奖项认证？')"><input type="hidden" name="act" value="revoke"><input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>"><button class="btn btn-danger btn-xs">取消认证</button></form>
        <?php else: ?>
        <form method="post" style="display:inline"><input type="hidden" name="act" value="approve"><input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>"><button class="btn btn-success btn-xs">通过</button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('清除该用户的奖项申请？')"><input type="hidden" name="act" value="revoke"><input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>"><button class="btn btn-outline btn-xs">清除</button></form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>

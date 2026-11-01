<?php
$title = '实人认证记录';
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';
require_once __DIR__ . '/../include/mailer.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$ok = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'reset') {
    $targetId = trim((string)($_POST['user_id'] ?? ''));
    $target = $targetId !== '' ? Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$targetId]) : null;
    if (!$target) {
        $err = '用户不存在';
    } else {
        Database::exec(
            "UPDATE users SET frv_status='none', frv_certify_id='', frv_real_name='', frv_verified_at='',
                frv_last_error='', frv_start_date='', frv_start_count=0,
                award_verify_status='none', award_verify_source='', award_verify_award='',
                award_verify_oier_id=0, award_verify_reason='', award_verify_payload='',
                award_verify_applied_at='', award_verify_approved_at=''
             WHERE user_id=?",
            [$targetId]
        );
        Database::exec("DELETE FROM frv_award_candidates WHERE user_id=?", [$targetId]);
        [$sent, $mailErr] = send_admin_action_email($target, '实人认证已重置', ['奖项认证同时被取消']);
        if ($mailErr !== '') error_log('[AirOJ mail] admin action notify failed: ' . $mailErr);
        $ok = '已重置该用户的实人认证与奖项认证';
    }
}

$rows = Database::fetchAll(
    "SELECT user_id, nick, frv_status, frv_real_name, frv_verified_at, frv_last_error,
            award_verify_status, award_verify_award
     FROM users
     WHERE frv_status <> 'none' OR award_verify_status <> 'none'
     ORDER BY CASE frv_status WHEN 'pending' THEN 0 WHEN 'failed' THEN 1 WHEN 'verified' THEN 2 ELSE 3 END,
              user_id ASC
     LIMIT 200"
);

$admin_current_group = 'verify';
$admin_current_item  = 'frv_list';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1><i class="fa-solid fa-id-card"></i> 实人认证记录</h1>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="alert alert-info" style="font-size:13px">
    服务器不保存身份证号；认证通过后会保存认证姓名和认证状态，用于后续奖项认证。重置实人认证会同时取消该用户的奖项认证。
</div>

<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr>
    <th style="width:170px">用户</th>
    <th style="width:100px">实人状态</th>
    <th>认证姓名</th>
    <th style="width:150px">认证时间</th>
    <th>最近错误</th>
    <th style="width:130px">奖项认证</th>
    <th style="width:100px">操作</th>
</tr></thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">暂无实人认证记录</td></tr>
<?php else: foreach ($rows as $r):
    $st = (string)$r['frv_status'];
    $stText = ['none' => '未认证', 'pending' => '认证中', 'verified' => '已认证', 'failed' => '未通过'][$st] ?? $st;
    $stCls = ['none' => '', 'pending' => 'tag-gold', 'verified' => 'tag-green', 'failed' => 'tag-red'][$st] ?? '';
    $aSt = (string)($r['award_verify_status'] ?? 'none');
    $aText = ['none' => '—', 'pending' => '审核中', 'approved' => '已认证', 'rejected' => '未通过'][$aSt] ?? $aSt;
?>
<tr>
    <td><?= user_role_html((string)$r['user_id'], $r['nick'] ?: null) ?></td>
    <td><span class="tag <?= $stCls ?>"><?= $stText ?></span></td>
    <td><?= $r['frv_real_name'] !== '' ? htmlspecialchars((string)$r['frv_real_name']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
    <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars((string)($r['frv_verified_at'] ?? '')) ?: '—' ?></td>
    <td style="font-size:12px;color:var(--text-muted);word-break:break-word"><?= htmlspecialchars((string)($r['frv_last_error'] ?? '')) ?: '—' ?></td>
    <td><span class="tag <?= $aSt === 'approved' ? 'tag-green' : ($aSt === 'pending' ? 'tag-gold' : ($aSt === 'rejected' ? 'tag-red' : '')) ?>"><?= $aText ?></span>
        <?php if ($r['award_verify_award'] !== ''): ?><br><span style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars((string)$r['award_verify_award']) ?></span><?php endif; ?>
    </td>
    <td>
        <?php if ($st !== 'none' || $aSt !== 'none'): ?>
        <form method="post" onsubmit="return confirm('重置该用户的实人认证并取消奖项认证？')">
            <input type="hidden" name="act" value="reset">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$r['user_id']) ?>">
            <button class="btn btn-danger btn-xs">重置</button>
        </form>
        <?php else: ?><span style="color:var(--text-muted);font-size:12px">—</span><?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>

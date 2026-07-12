<?php
$title = '用户管理';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$users = Database::fetchAll("SELECT u.*, (SELECT COUNT(*) FROM privilege p WHERE p.user_id=u.user_id AND p.right_str='administrator') AS is_admin FROM users u ORDER BY u.solved_count DESC");
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1>👤 用户管理</h1></div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th>用户</th><th>昵称</th><th>邮箱</th><th>已解决</th><th>提交</th><th>Rating</th><th>管理员</th><th>操作</th></tr></thead>
<tbody>
<?php foreach ($users as $u): ?>
<tr>
<td><?=htmlspecialchars($u['user_id'])?></td>
<td><?=htmlspecialchars($u['nick']??'')?></td>
<td><?=htmlspecialchars($u['email']??'')?></td>
<td style="font-weight:600;color:var(--green)"><?=$u['solved_count']?:0?></td>
<td><?=$u['submit_count']?:0?></td>
<td><?=round($u['rating']?:1500)?></td>
<td><?=$u['is_admin']?'<span style="color:var(--accent-gold)">✅</span>':'❌'?></td>
<td>
<?php if (!$u['is_admin']): ?><a href="privilege_add.php?uid=<?=urlencode($u['user_id'])?>" class="btn btn-primary btn-xs">设为管理员</a><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../inc/footer.php'; ?>

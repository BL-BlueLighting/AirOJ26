<?php
$title = '权限管理';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$privileges = Database::fetchAll("SELECT p.*, u.nick FROM privilege p LEFT JOIN users u ON p.user_id=u.user_id ORDER BY p.right_str, p.user_id");
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1>🔑 权限管理</h1><a href="privilege_add.php" class="btn btn-primary btn-sm">➕ 添加权限</a></div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th>用户</th><th>权限</th><th>操作</th></tr></thead>
<tbody>
<?php if (!$privileges): ?><tr><td colspan="3" style="text-align:center;padding:32px;color:var(--text-muted)">暂无权限记录</td></tr>
<?php else: foreach ($privileges as $p): ?>
<tr>
<td><?=htmlspecialchars($p['user_id'])?><?=$p['nick']?' ('.htmlspecialchars($p['nick']).')':''?></td>
<td><span class="badge badge-ac"><?=htmlspecialchars($p['right_str'])?></span></td>
<td><a href="privilege_del.php?uid=<?=urlencode($p['user_id'])?>&right=<?=urlencode($p['right_str'])?>" class="btn btn-danger btn-xs" onclick="return confirm('确定移除？')">移除</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../inc/footer.php'; ?>

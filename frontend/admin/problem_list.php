<?php
$title = '题目管理';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$problems = Database::fetchAll("SELECT * FROM problem ORDER BY problem_id DESC");
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1>📋 题目管理</h1><a href="problem_add.php" class="btn btn-primary btn-sm">➕ 添加题目</a></div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th>ID</th><th>标题</th><th>状态</th><th>测试数据</th><th>操作</th></tr></thead>
<tbody>
<?php if (!$problems): ?><tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">暂无题目</td></tr>
<?php else: foreach ($problems as $p):
$pid = $p['problem_id'];
$dir = INPUTS_DIR . '/' . $pid;
$has_data = is_dir($dir) && count(array_filter(scandir($dir) ?: [], fn($f)=>str_ends_with($f,'.in'))) > 0;
?>
<tr>
<td><?=$pid?></td>
<td><a href="../problem.php?id=<?=$pid?>"><?=htmlspecialchars($p['title'])?></a></td>
<td><?=$p['defunct']==='Y'?'<span style="color:var(--red)">隐藏</span>':'<span style="color:var(--green)">正常</span>'?></td>
<td><?=$has_data?'<span style="color:var(--green)">✅ 有</span>':'<span style="color:var(--red)">❌ 无</span>'?></td>
<td>
<a href="problem_edit.php?id=<?=$pid?>" class="btn btn-primary btn-xs">编辑</a>
<a href="problem_del.php?id=<?=$pid?>" class="btn btn-danger btn-xs" onclick="return confirm('确定删除？')">删除</a>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../inc/footer.php'; ?>

<?php
$title = '章节管理';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$chapters = Database::fetchAll(
    "SELECT c.*, (SELECT COUNT(*) FROM chapter_problem cp WHERE cp.chapter_id=c.chapter_id) AS problem_count
     FROM chapters c ORDER BY c.chapter_id DESC"
);

$admin_current_group = 'chapter';
$admin_current_item  = 'chapter_list';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1><i class="fa-solid fa-book-open"></i> 章节管理</h1>
    <a href="chapter_edit.php?id=0" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> 添加章节</a>
</div>

<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr>
    <th style="width:50px">#</th><th>章节名称</th><th style="width:80px">题目数</th>
    <th style="width:150px">创建人</th><th style="width:170px">创建时间</th><th style="width:220px">操作</th>
</tr></thead>
<tbody>
<?php if (!$chapters): ?>
<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">暂无章节</td></tr>
<?php else: foreach ($chapters as $c): ?>
<tr>
    <td><?= (int)$c['chapter_id'] ?></td>
    <td>
        <strong><?= htmlspecialchars($c['title']) ?></strong>
        <?php if (!empty($c['background'])): ?><span class="tag tag-blue">有封面</span><?php endif; ?>
    </td>
    <td><?= (int)$c['problem_count'] ?></td>
    <td><?= user_role_html($c['creator']) ?></td>
    <td style="color:var(--text-muted);font-size:12px"><?= htmlspecialchars($c['created_at']) ?></td>
    <td>
        <a href="../chapter.php?id=<?= (int)$c['chapter_id'] ?>" class="btn btn-outline btn-xs" target="_blank">查看</a>
        <a href="chapter_edit.php?id=<?= (int)$c['chapter_id'] ?>" class="btn btn-primary btn-xs">编辑</a>
        <a href="chapter_del.php?id=<?= (int)$c['chapter_id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('确定删除该章节？（不会删除题目本身）')">删除</a>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>

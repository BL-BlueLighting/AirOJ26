<?php
$title = '编辑章节';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$uid = $_SESSION['airoj_user']['user_id'];
$id  = intval($_GET['id'] ?? 0);
$err = '';
$ok  = isset($_GET['saved']) ? '章节已保存' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $c_title = trim($_POST['title'] ?? '');
    $desc    = (string)($_POST['description'] ?? '');
    $bg      = trim((string)($_POST['background'] ?? ''));

    if ($c_title === '') {
        $err = '请填写章节名称';
    } else {
        if ($id) {
            Database::exec("UPDATE chapters SET title=?, description=?, background=? WHERE chapter_id=?", [$c_title, $desc, $bg, $id]);
        } else {
            $id = Database::insert("INSERT INTO chapters (title,description,background,creator) VALUES (?,?,?,?)",
                [$c_title, $desc, $bg, $uid]);
        }

        // 封面上传（优先于文本框里的地址）
        if (!empty($_FILES['cover']['name'])) {
            foreach (glob(__DIR__ . '/../data/uploads/chapter-' . $id . '.*') ?: [] as $f) @unlink($f);
            $up = save_background_upload($_FILES['cover'], 'chapter-' . $id);
            if (is_array($up) && $up[0] === null) {
                $err = $up[1];
            } elseif (is_array($up)) {
                Database::exec("UPDATE chapters SET background=? WHERE chapter_id=?", [$up[0], $id]);
            }
        } elseif ($bg !== '' && !is_valid_background($bg)) {
            $err = '背景值无效（支持图片地址 / 站内路径 / #颜色 / 渐变）';
            Database::exec("UPDATE chapters SET background='' WHERE chapter_id=?", [$id]);
        }

        // 题目列表：按顺序保存
        Database::exec("DELETE FROM chapter_problem WHERE chapter_id=?", [$id]);
        $raw = preg_split('/[\s,，]+/', trim((string)($_POST['problems'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $seen = [];
        $pos = 1;
        foreach ($raw as $item) {
            $pid = (int)$item;
            if ($pid <= 0 || isset($seen[$pid])) continue;
            $seen[$pid] = true;
            Database::exec("INSERT INTO chapter_problem (chapter_id,problem_id,position) VALUES (?,?,?)", [$id, $pid, $pos++]);
        }

        if ($err === '') {
            header('Location: chapter_edit.php?id=' . $id . '&saved=1');
            exit;
        }
    }
}

$row = $id ? Database::fetchOne("SELECT * FROM chapters WHERE chapter_id=?", [$id]) : null;

// 已关联题目
$linked = [];
if ($id) {
    $linked = Database::fetchAll(
        "SELECT cp.problem_id, cp.position, p.title FROM chapter_problem cp
         LEFT JOIN problem p ON p.problem_id = cp.problem_id
         WHERE cp.chapter_id=? ORDER BY cp.position ASC", [$id]);
}
$linkedText = implode("\n", array_map(fn($l) => $l['problem_id'], $linked));
$previewCss = $row ? background_css((string)($row['background'] ?? '')) : '';

$admin_current_group = 'chapter';
$admin_current_item  = $id ? 'chapter_list' : 'chapter_add';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1><i class="fa-solid fa-<?= $id ? 'pen-to-square' : 'plus' ?>"></i> <?= $id ? '编辑章节 #' . $id : '添加章节' ?></h1>
    <div>
        <?php if ($id): ?><a href="../chapter.php?id=<?= $id ?>" class="btn btn-outline btn-sm" target="_blank">查看章节</a><?php endif; ?>
        <a href="chapter_list.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回列表</a>
    </div>
</div>

<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="card" style="max-width:820px">
    <form method="post" enctype="multipart/form-data">
        <div class="form-group"><label>章节名称 *</label>
            <input type="text" name="title" class="form-input" value="<?=htmlspecialchars($row['title'] ?? '')?>" required></div>

        <div class="form-group"><label>章节介绍（支持 Markdown）</label>
            <textarea name="description" class="form-input md-editor" style="min-height:150px"><?=htmlspecialchars($row['description'] ?? '')?></textarea></div>

        <div class="form-group">
            <label>背景图片</label>
            <?php if ($previewCss !== ''): ?>
            <div style="height:110px;border-radius:var(--radius);overflow:hidden;position:relative;margin-bottom:8px;background:var(--bg-primary)">
                <div style="position:absolute;inset:0;<?= htmlspecialchars($previewCss, ENT_COMPAT) ?>"></div>
            </div>
            <?php endif; ?>
            <input type="file" name="cover" class="form-input" accept="image/png,image/jpeg,image/gif,image/webp" style="margin-bottom:8px">
            <input type="text" name="background" class="form-input" value="<?=htmlspecialchars($row['background'] ?? '')?>"
                   placeholder="或填写图片地址 / data/uploads/xxx / #颜色 / 渐变（上传优先）">
        </div>

        <div class="form-group">
            <label>题目列表 <span class="pe-hint">填写题目 ID，按顺序每行一个（逗号 / 空格分隔也可以）</span></label>
            <textarea name="problems" class="form-input pe-md" style="min-height:160px" placeholder="1000&#10;1001&#10;1002"><?=htmlspecialchars($linkedText)?></textarea>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> 保存章节</button>
    </form>
</div>

<?php if ($id && $linked): ?>
<div class="card" style="max-width:820px">
    <h2><i class="fa-solid fa-list-check"></i> 已关联题目（<?= count($linked) ?>）</h2>
    <div class="table-wrap"><table>
        <thead><tr><th style="width:60px">#</th><th style="width:100px">题目 ID</th><th>标题</th><th style="width:100px">操作</th></tr></thead>
        <tbody>
        <?php foreach ($linked as $i => $l): ?>
        <tr>
            <td style="font-weight:700;color:var(--accent)"><?= $i + 1 ?></td>
            <td><?= (int)$l['problem_id'] ?></td>
            <td><?= htmlspecialchars($l['title'] ?? '（题目不存在）') ?></td>
            <td><a href="../problem.php?id=<?= (int)$l['problem_id'] ?>" class="btn btn-outline btn-xs" target="_blank">查看</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<script src="/js/md-toolbar.js?v=1"></script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

<?php
$title = '添加题目';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$uid = $_SESSION['airoj_user']['user_id'];
$err = '';

// ?parent=N：作为该题目（题目组）的一个难度版本添加
$parentId = (int)($_GET['parent'] ?? $_POST['parent'] ?? 0);
$parent = $parentId ? Database::fetchOne("SELECT * FROM problem WHERE problem_id=?", [$parentId]) : null;
if ($parentId && !$parent) $err = '题目组不存在';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $err === '') {
    $isVariant = $parent !== null;
    $p_title = $isVariant ? (string)$parent['title'] : trim($_POST['title'] ?? '');
    $spj     = isset($_POST['spj']) ? '1' : '0';

    // 至少一个有效难度
    $hasDiff = false;
    foreach (($_POST['diff_cat'] ?? []) as $i => $cat) {
        if (difficulty_valid((string)$cat, (int)($_POST['diff_level'][$i] ?? 0))) { $hasDiff = true; break; }
    }

    if ($p_title === '') {
        $err = '请填写标题';
    } elseif ($isVariant && !$hasDiff) {
        $err = '难度版本必须至少选择一个难度';
    } elseif ($spj === '1' && empty($_FILES['spj_file']['name'])) {
        $err = '启用 Special Judge 时必须上传特判程序';
    } else {
        $gid = $isVariant ? problem_ensure_group((int)$parent['problem_id']) : 0;

        $new_id = Database::insert(
            "INSERT INTO problem (title,description,input,output,sample_input,sample_output,hint,tags,author,group_id,spj,defunct)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $p_title,
                $_POST['description'] ?? '',
                $_POST['input'] ?? '',
                $_POST['output'] ?? '',
                $_POST['sample_input'] ?? '',
                $_POST['sample_output'] ?? '',
                $_POST['hint'] ?? '',
                $isVariant ? (string)$parent['tags'] : normalize_tags($_POST['tags'] ?? ''),
                $isVariant ? (string)$parent['author'] : (trim($_POST['author'] ?? '') ?: $uid),
                $gid,
                $spj,
                ($_POST['defunct'] ?? 'N') === 'Y' ? 'Y' : 'N',
            ]
        );

        $data_dir = INPUTS_DIR . '/' . $new_id;
        if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);

        // 测试数据 + 特判程序 + 难度标签
        save_test_data_uploads($_FILES['test_data'] ?? [], (string)$new_id);
        if ($spj === '1') {
            [$spjOk, $spjErr] = save_spj_upload($_FILES['spj_file'] ?? [], (string)$new_id);
            if (!$spjOk) {
                // 特判程序保存失败：回滚题目，避免出现启用特判却没有特判程序的题目
                Database::exec("DELETE FROM problem WHERE problem_id=?", [$new_id]);
                Database::exec("DELETE FROM problem_difficulty WHERE problem_id=?", [$new_id]);
                $err = $spjErr;
            }
        }
        if ($err === '') {
            save_problem_difficulties((string)$new_id, $_POST);
            header('Location: problem_edit.php?id=' . $new_id . '&created=1');
            exit;
        }
    }
}

$pe_action    = 'problem_add.php' . ($parent ? '?parent=' . (int)$parent['problem_id'] : '');
$pe_values    = $parent
    ? ['title' => $parent['title'], 'tags' => $parent['tags'], 'author' => $parent['author'], 'defunct' => 'N']
    : [];
$pe_submit    = $parent ? '创建难度版本' : '创建题目';
$pe_is_edit   = false;
$pe_show_test = true;
$pe_diffs     = [];
$pe_variant   = (bool)$parent;

$admin_current_group = 'problem';
$admin_current_item  = 'problem_add';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1>
        <i class="fa-solid fa-<?= $parent ? 'layer-group' : 'plus' ?>"></i>
        <?= $parent ? '添加难度版本 — ' . htmlspecialchars($parent['title']) : '添加题目' ?>
    </h1>
    <a href="<?= $parent ? 'problem_edit.php?id=' . (int)$parent['problem_id'] : 'problem_list.php' ?>" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-arrow-left"></i> 返回
    </a>
</div>

<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>

<?php require __DIR__ . '/_problem_form.php'; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>

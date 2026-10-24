<?php
$title = '编辑题目';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$id = intval($_GET['id'] ?? 0);
$err = ''; $ok = '';
if (isset($_GET['created'])) $ok = '题目创建成功，可以继续完善题面并上传测试数据。';

$row = $id ? Database::fetchOne("SELECT * FROM problem WHERE problem_id=?", [$id]) : null;
$isMember = $row ? problem_is_group_member($row) : false;
$groupMainId = $row ? problem_group_main_id($row) : 0;
$isGroupMain = $row && (int)$row['group_id'] > 0 && (int)$row['group_id'] === (int)$row['problem_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
    // 测试数据上传（独立表单，不影响题面字段）
    if (isset($_POST['upload_test_data'])) {
        $data_dir = INPUTS_DIR . '/' . $id;
        if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
        $n = 0;
        if (!empty($_FILES['test_data']['name'][0])) {
            foreach ($_FILES['test_data']['tmp_name'] as $i => $tmp) {
                if ($tmp && $_FILES['test_data']['name'][$i]) {
                    if (move_uploaded_file($tmp, $data_dir . '/' . basename($_FILES['test_data']['name'][$i]))) $n++;
                }
            }
        }
        $ok = $n > 0 ? "已上传 {$n} 个测试数据文件" : '没有选择文件';
    } else {
        // 保存题面
        $p_title = $isMember ? (string)$row['title'] : trim($_POST['title'] ?? '');
        $spj = isset($_POST['spj']) ? '1' : '0';
        $spjExists = spj_source_path((string)$id) !== null;

        if ($p_title === '') {
            $err = '请填写标题';
        } elseif ($spj === '1' && !$spjExists && empty($_FILES['spj_file']['name'])) {
            $err = '启用 Special Judge 时必须上传特判程序';
        } else {
            if ($spj === '1' && !empty($_FILES['spj_file']['name'])) {
                [$spjOk, $spjErr] = save_spj_upload($_FILES['spj_file'], (string)$id);
                if (!$spjOk) $err = $spjErr;
            }
            if ($err === '') {
                if ($isMember) {
                    // 难度版本：只更新自己的题面内容，共享字段保持不变
                    Database::exec(
                        "UPDATE problem SET description=?,input=?,output=?,sample_input=?,sample_output=?,hint=?,spj=?,defunct=? WHERE problem_id=?",
                        [
                            $_POST['description'] ?? '',
                            $_POST['input'] ?? '',
                            $_POST['output'] ?? '',
                            $_POST['sample_input'] ?? '',
                            $_POST['sample_output'] ?? '',
                            $_POST['hint'] ?? '',
                            $spj,
                            ($_POST['defunct'] ?? 'N') === 'Y' ? 'Y' : 'N',
                            $id,
                        ]
                    );
                    $ok = '难度版本已保存';
                } else {
                    $tags = normalize_tags($_POST['tags'] ?? '');
                    $author = trim($_POST['author'] ?? '');
                    Database::exec(
                        "UPDATE problem SET title=?,description=?,input=?,output=?,sample_input=?,sample_output=?,hint=?,tags=?,author=?,spj=?,defunct=? WHERE problem_id=?",
                        [
                            $p_title,
                            $_POST['description'] ?? '',
                            $_POST['input'] ?? '',
                            $_POST['output'] ?? '',
                            $_POST['sample_input'] ?? '',
                            $_POST['sample_output'] ?? '',
                            $_POST['hint'] ?? '',
                            $tags,
                            $author,
                            $spj,
                            ($_POST['defunct'] ?? 'N') === 'Y' ? 'Y' : 'N',
                            $id,
                        ]
                    );
                    // 题目组：标题 / 标签 / 出题人同步到所有版本
                    if ($isGroupMain) problem_sync_group_meta($groupMainId, $p_title, $tags, $author);
                    $ok = '题目已保存';
                }
                $diffErr = save_problem_difficulties((string)$id, $_POST);
                if ($diffErr !== null) {
                    $err = $diffErr;
                    $ok = '';
                }
            }
        }
    }
}

$row = Database::fetchOne("SELECT * FROM problem WHERE problem_id=?", [$id]);
if (!$row) {
    require __DIR__ . '/../inc/header.php';
    echo '<div class="empty"><div class="icon"><i class="fa-solid fa-file-circle-question"></i></div><p>题目不存在</p></div>';
    require __DIR__ . '/../inc/footer.php';
    exit;
}
$isMember = problem_is_group_member($row);
$groupMainId = problem_group_main_id($row);
$groupMembers = problem_group_members($groupMainId);

// 测试数据文件
$data_dir = INPUTS_DIR . '/' . $id;
$data_files = is_dir($data_dir) ? scandir($data_dir) : [];
$in_files  = array_filter($data_files ?: [], fn($f) => str_ends_with($f, '.in'));
$out_files = array_filter($data_files ?: [], fn($f) => str_ends_with($f, '.out'));
$stems = [];
foreach ($in_files as $f)  $stems[substr($f, 0, -3)] = true;
foreach ($out_files as $f) $stems[substr($f, 0, -4)] = true;
ksort($stems, SORT_NATURAL);

$pe_action    = 'problem_edit.php?id=' . $id;
$pe_values    = $row;
$pe_submit    = '保存修改';
$pe_is_edit   = true;
$pe_diffs     = problem_difficulties((string)$id);
$pe_spj_exists = spj_source_path((string)$id) !== null;
$pe_variant   = $isMember;

$admin_current_group = 'problem';
$admin_current_item  = 'problem_list';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1><i class="fa-solid fa-pen-to-square"></i> 编辑题目 #<?= $id ?></h1>
    <div>
        <a href="../problem.php?id=<?= $id ?>" class="btn btn-outline btn-sm" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> 查看题目</a>
        <a href="problem_list.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 返回列表</a>
    </div>
</div>

<?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<?php require __DIR__ . '/_problem_form.php'; ?>

<div class="card" style="margin-top:16px">
    <h2><i class="fa-solid fa-layer-group"></i> 难度版本（<?= count($groupMembers) ?>）</h2>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
        同一道题可以挂多个难度版本：每个版本拥有独立的题面、测试数据与 Special Judge，共享标题 / 标签 / 出题人。
        题目页会显示难度切换标签。
    </p>
    <div class="table-wrap"><table>
        <thead><tr><th style="width:90px">题号</th><th>难度</th><th style="width:90px">测试点</th><th style="width:90px">状态</th><th style="width:150px">操作</th></tr></thead>
        <tbody>
        <?php foreach ($groupMembers as $m):
            $mid = (int)$m['problem_id'];
            $mdiff = problem_difficulties((string)$mid);
            $mlabel = $mdiff ? difficulty_label($mdiff[0]['category'], (int)$mdiff[0]['level'], $mdiff[0]['position']) : '未设置';
        ?>
        <tr>
            <td>
                <strong>#<?= $mid ?></strong>
                <?php if ($mid === $groupMainId): ?><span class="tag tag-blue">主记录</span><?php endif; ?>
                <?php if ($mid === $id): ?><span class="diff-tag diff-basic">当前</span><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($mlabel) ?></td>
            <td><?= count_test_cases((string)$mid) ?></td>
            <td><?= $m['defunct'] === 'Y' ? '<span class="tag tag-red">隐藏</span>' : '<span class="tag tag-green">正常</span>' ?></td>
            <td>
                <a href="problem_edit.php?id=<?= $mid ?>" class="btn btn-outline btn-xs">编辑</a>
                <a href="../problem.php?id=<?= $groupMainId ?>&v=<?= $mid ?>" class="btn btn-primary btn-xs" target="_blank">查看</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div style="margin-top:12px">
        <a href="problem_add.php?parent=<?= $groupMainId ?>" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> 添加难度版本
        </a>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2><i class="fa-solid fa-folder-open"></i> 测试数据</h2>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
        目录：<code><?= htmlspecialchars($data_dir) ?></code><br>
        文件名格式：<code>1.in</code> / <code>1.out</code>、<code>2.in</code> / <code>2.out</code> …，未配对的文件不会参与评测。
    </p>

    <?php if ($stems): ?>
    <div class="table-wrap"><table>
        <thead><tr><th>测试点</th><th>输入文件</th><th>输出文件</th></tr></thead>
        <tbody>
        <?php foreach ($stems as $stem => $__): $inf = $stem . '.in'; $outf = $stem . '.out'; ?>
        <tr>
            <td style="font-weight:700;color:var(--accent)">#<?= htmlspecialchars((string)$stem) ?></td>
            <td><?= in_array($inf, $in_files, true) ? '<i class="fa-solid fa-circle-check" style="color:var(--green)"></i> ' . htmlspecialchars($inf) : '<i class="fa-solid fa-circle-xmark" style="color:var(--red)"></i> ' . htmlspecialchars($inf) . '（缺失）' ?></td>
            <td><?= in_array($outf, $out_files, true) ? '<i class="fa-solid fa-circle-check" style="color:var(--green)"></i> ' . htmlspecialchars($outf) : '<i class="fa-solid fa-circle-xmark" style="color:var(--red)"></i> ' . htmlspecialchars($outf) . '（缺失）' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?>
    <p style="color:var(--text-muted)">暂无测试数据，未上传测试数据的题目无法提交评测。</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-top:16px">
        <input type="hidden" name="upload_test_data" value="1">
        <div class="form-row">
            <div class="form-group" style="flex:3"><label>批量上传（可多选）</label>
                <input type="file" name="test_data[]" class="form-input" multiple></div>
            <div class="form-group" style="flex:0 0 auto">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> 上传</button></div>
        </div>
    </form>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>

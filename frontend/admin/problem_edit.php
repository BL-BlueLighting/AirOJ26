<?php
$title = '编辑题目';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$id = intval($_GET['id'] ?? 0);
$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Database::exec("UPDATE problem SET title=?,description=?,input=?,output=?,sample_input=?,sample_output=?,hint=?,source=?,spj=? WHERE problem_id=?",
        [$_POST['title'],$_POST['description'],$_POST['input'],$_POST['output'],$_POST['sample_input'],$_POST['sample_output'],$_POST['hint'],$_POST['source'],$_POST['spj'],$id]);
    // Handle test data upload
    if (!empty($_FILES['test_data']['name'][0])) {
        $data_dir = INPUTS_DIR . '/' . $id;
        if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
        foreach ($_FILES['test_data']['tmp_name'] as $i => $tmp) {
            if ($tmp && $_FILES['test_data']['name'][$i]) {
                move_uploaded_file($tmp, $data_dir . '/' . basename($_FILES['test_data']['name'][$i]));
            }
        }
    }
    $ok = '题目已更新';
}

$row = Database::fetchOne("SELECT * FROM problem WHERE problem_id=?", [$id]);
if (!$row) { echo '<div class="empty"><p>题目不存在</p></div>'; require __DIR__ . '/../inc/footer.php'; exit; }

// List existing test data
$data_dir = INPUTS_DIR . '/' . $id;
$data_files = is_dir($data_dir) ? scandir($data_dir) : [];
$in_files = array_filter($data_files ?: [], fn($f) => str_ends_with($f, '.in'));
$out_files = array_filter($data_files ?: [], fn($f) => str_ends_with($f, '.out'));

require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1>✏️ 编辑题目 #<?=$id?></h1><a href="problem_list.php" class="btn btn-outline btn-sm">← 返回</a></div>
<div class="card" style="max-width:800px">
    <?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="form-group"><label>标题 *</label><input type="text" name="title" class="form-input" value="<?=htmlspecialchars($row['title'])?>" required></div>
        <div class="form-group"><label>题目描述</label><textarea name="description" class="form-input" style="min-height:150px"><?=htmlspecialchars($row['description']??'')?></textarea></div>
        <div class="form-group"><label>输入格式</label><textarea name="input" class="form-input"><?=htmlspecialchars($row['input']??'')?></textarea></div>
        <div class="form-group"><label>输出格式</label><textarea name="output" class="form-input"><?=htmlspecialchars($row['output']??'')?></textarea></div>
        <div class="form-row">
            <div class="form-group"><label>样例输入</label><textarea name="sample_input" class="form-input" style="min-height:80px"><?=htmlspecialchars($row['sample_input']??'')?></textarea></div>
            <div class="form-group"><label>样例输出</label><textarea name="sample_output" class="form-input" style="min-height:80px"><?=htmlspecialchars($row['sample_output']??'')?></textarea></div>
        </div>
        <div class="form-group"><label>提示</label><textarea name="hint" class="form-input"><?=htmlspecialchars($row['hint']??'')?></textarea></div>
        <div class="form-group"><label>来源</label><input type="text" name="source" class="form-input" value="<?=htmlspecialchars($row['source']??'')?>"></div>
        <div class="form-row">
            <div class="form-group"><label>Special Judge</label><select name="spj" class="form-input"><option value="0" <?=$row['spj']==='0'?'selected':''?>>否</option><option value="1" <?=$row['spj']==='1'?'selected':''?>>是</option></select></div>
            <div class="form-group"><label>状态</label><select name="defunct" class="form-input"><option value="N" <?=$row['defunct']==='N'?'selected':''?>>正常</option><option value="Y" <?=$row['defunct']==='Y'?'selected':''?>>隐藏</option></select></div>
        </div>
        <button type="submit" class="btn btn-primary">保存修改</button>
    </form>
</div>

<div class="card" style="max-width:800px">
    <h2>📂 测试数据</h2>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
        测试数据目录: <code><?=htmlspecialchars($data_dir)?></code><br>
        文件名格式: <code>1.in</code> / <code>1.out</code>、<code>2.in</code> / <code>2.out</code> ...
    </p>
    <?php if ($in_files || $out_files): ?>
    <table><thead><tr><th>输入文件</th><th>输出文件</th></tr></thead>
    <tbody><?php
    $stems = [];
    foreach ($in_files as $f) $stems[substr($f,0,-3)] = 1;
    foreach ($out_files as $f) $stems[substr($f,0,-4)] = 1;
    ksort($stems);
    foreach ($stems as $stem => $_):
        $inf = $stem.'.in'; $outf = $stem.'.out';
        $has_in = in_array($inf, $in_files ?? []); $has_out = in_array($outf, $out_files ?? []);
    ?><tr><td><?=$has_in?'✅ '.htmlspecialchars($inf):'❌ '.htmlspecialchars($inf)?></td><td><?=$has_out?'✅ '.htmlspecialchars($outf):'❌ '.htmlspecialchars($outf)?></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php else: ?><p style="color:var(--text-muted)">暂无测试数据</p><?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-top:16px">
        <div class="form-group"><label>上传测试数据文件（批量）</label><input type="file" name="test_data[]" class="form-input" multiple></div>
        <button type="submit" class="btn btn-primary btn-sm">上传</button>
    </form>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>

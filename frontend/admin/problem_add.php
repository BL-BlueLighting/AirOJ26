<?php
$title = '添加题目';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$err = ''; $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $input = trim($_POST['input'] ?? '');
    $output = trim($_POST['output'] ?? '');
    $sample_in = trim($_POST['sample_input'] ?? '');
    $sample_out = trim($_POST['sample_output'] ?? '');
    $hint = trim($_POST['hint'] ?? '');
    $source = trim($_POST['source'] ?? '');
    $spj = $_POST['spj'] ?? '0';

    if ($title) {
        $new_id = Database::insert("INSERT INTO problem (title,description,input,output,sample_input,sample_output,hint,source,spj) VALUES (?,?,?,?,?,?,?,?,?)",
            [$title, $desc, $input, $output, $sample_in, $sample_out, $hint, $source, $spj]);
        // Create test data directory
        $data_dir = INPUTS_DIR . '/' . $new_id;
        if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
        $ok = "题目创建成功！ID: $new_id，测试数据目录: inputs_outputs/$new_id/";
    } else $err = '请填写标题';
}
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1>📝 添加题目</h1><a href="problem_list.php" class="btn btn-outline btn-sm">← 返回</a></div>
<div class="card" style="max-width:800px">
    <?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>标题 *</label><input type="text" name="title" class="form-input" required></div>
        <div class="form-group"><label>题目描述</label><textarea name="description" class="form-input" style="min-height:150px"></textarea></div>
        <div class="form-group"><label>输入格式</label><textarea name="input" class="form-input"></textarea></div>
        <div class="form-group"><label>输出格式</label><textarea name="output" class="form-input"></textarea></div>
        <div class="form-row">
            <div class="form-group"><label>样例输入</label><textarea name="sample_input" class="form-input" style="min-height:80px"></textarea></div>
            <div class="form-group"><label>样例输出</label><textarea name="sample_output" class="form-input" style="min-height:80px"></textarea></div>
        </div>
        <div class="form-group"><label>提示</label><textarea name="hint" class="form-input"></textarea></div>
        <div class="form-group"><label>来源</label><input type="text" name="source" class="form-input"></div>
        <div class="form-row">
            <div class="form-group"><label>Special Judge</label><select name="spj" class="form-input"><option value="0">否</option><option value="1">是</option></select></div>
        </div>
        <button type="submit" class="btn btn-primary">创建题目</button>
    </form>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>

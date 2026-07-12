<?php
$title = '题目';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/functions.php';
require_once 'inc/Parsedown.php';

$pid = $_GET['id'] ?? '1001';
$cases = load_test_cases($pid);
$caseCount = $cases ? count($cases) : 0;
$langs = get_language_configs();
$err = $_GET['err'] ?? '';
$ok = $_GET['ok'] ?? '';

// 从数据库读取题目信息
require_once 'inc/db.php';
$problem = Database::fetchOne("SELECT * FROM problem WHERE problem_id=?", [(int)$pid]);

$pd = new Parsedown();
$pd->setSafeMode(true);

function md(?string $text, Parsedown $pd): string {
    return $text ? $pd->text($text) : '<p style="color:var(--text-muted)">（暂无内容）</p>';
}
?>
<div class="page-header">
    <h1>📄 Problem <?= htmlspecialchars($pid) ?></h1>
    <a href="/solutions.php?id=<?= $pid ?>" class="btn btn-outline btn-sm">📝 题解</a>
</div>

<?php if ($problem): ?>
<div class="card" style="overflow-x:auto">
    <div class="problem-section">
        <h3>📝 题目描述</h3>
        <?= md($problem['description'] ?? '', $pd) ?>
    </div>
    <div class="problem-section">
        <h3>📥 输入格式</h3>
        <?= md($problem['input'] ?? '', $pd) ?>
    </div>
    <div class="problem-section">
        <h3>📤 输出格式</h3>
        <?= md($problem['output'] ?? '', $pd) ?>
    </div>
    <?php if (!empty($problem['sample_input']) || !empty($problem['sample_output'])): ?>
    <div class="problem-section">
        <h3>📋 样例</h3>
        <?php if (!empty($problem['sample_input'])): ?>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">输入</div>
        <div class="sample-box"><?= htmlspecialchars($problem['sample_input']) ?></div>
        <?php endif; ?>
        <?php if (!empty($problem['sample_output'])): ?>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">输出</div>
        <div class="sample-box"><?= htmlspecialchars($problem['sample_output']) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($problem['hint'])): ?>
    <div class="problem-section">
        <h3>💡 提示</h3>
        <?= md($problem['hint'], $pd) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($problem['source'])): ?>
    <div style="font-size:12px;color:var(--text-muted);margin-top:12px">来源: <?= htmlspecialchars($problem['source']) ?></div>
    <?php endif; ?>
    <div style="font-size:12px;color:var(--text-muted);margin-top:8px">测试点数量: <strong><?= $caseCount ?></strong></div>
</div>
<?php else: ?>
<div class="card">
    <div class="problem-section"><h3>题目描述</h3><p style="color:var(--text-muted)">请编写程序解决本题。评测系统将使用存储的测试数据对你的代码进行测试。</p></div>
    <div style="color:var(--text-muted);font-size:13px">测试点数量: <strong><?= $caseCount ?></strong></div>
</div>
<?php endif; ?>

<div class="card">
    <h2>✏️ 提交代码</h2>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <form method="post" action="/submit.php">
        <input type="hidden" name="pid" value="<?= $pid ?>">
        <div class="form-row" style="margin-bottom:16px">
            <div class="form-group"><label>编程语言</label>
                <select name="language" class="form-input">
                    <option value="">— 选择语言 —</option>
                    <?php foreach ($langs as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $v['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>评测类型</label>
                <select name="judge_type" class="form-input">
                    <option value="standard">Standard IO</option>
                    <option value="spj">Special Judge</option>
                </select>
            </div>
            <div style="display:flex;align-items:flex-end">
                <button type="submit" class="btn btn-primary">⚡ 提交</button>
            </div>
        </div>
        <div class="form-group"><label>源代码</label>
            <textarea name="source" class="form-input" required placeholder="在此编写你的代码..."></textarea>
        </div>
    </form>
</div>

<?php require 'inc/footer.php'; ?>

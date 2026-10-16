<?php
$title = '编辑比赛';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

// 'YYYY-MM-DDTHH:MM' -> 'YYYY-MM-DD HH:MM:SS'
function normalize_dt(string $s): string
{
    $s = trim(str_replace('T', ' ', $s));
    if ($s === '') return '2000-01-01 00:00:00';
    if (strlen($s) === 16) $s .= ':00';
    return $s;
}
function dt_input(?string $s): string
{
    return str_replace(' ', 'T', substr((string)$s, 0, 16));
}

$id  = intval($_GET['id'] ?? 0);
$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_t = trim($_POST['title'] ?? '');
    $start   = normalize_dt($_POST['start_time'] ?? '');
    $end     = normalize_dt($_POST['end_time'] ?? '');
    $type    = intval($_POST['contest_type'] ?? 0) === 1 ? 1 : 0;
    $private = ($_POST['private'] ?? '0') === '1' ? '1' : '0';
    $defunct = ($_POST['defunct'] ?? 'N') === 'Y' ? 'Y' : 'N';
    $desc    = $_POST['description'] ?? '';

    if ($title_t === '') {
        $err = '请填写比赛名称';
    } elseif (strtotime($end) <= strtotime($start)) {
        $err = '结束时间必须晚于开始时间';
    } else {
        if ($id) {
            Database::exec("UPDATE contest SET title=?,start_time=?,end_time=?,contest_type=?,private=?,defunct=?,description=? WHERE contest_id=?",
                [$title_t, $start, $end, $type, $private, $defunct, $desc, $id]);
        } else {
            $id = Database::insert("INSERT INTO contest (title,start_time,end_time,contest_type,private,defunct,description) VALUES (?,?,?,?,?,?,?)",
                [$title_t, $start, $end, $type, $private, $defunct, $desc]);
        }

        // 重新关联题目：支持逗号 / 空格 / 换行分隔
        Database::exec("DELETE FROM contest_problem WHERE contest_id=?", [$id]);
        $raw = preg_split('/[\s,，]+/', trim($_POST['problems'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $num = 1;
        foreach ($raw as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;
            Database::exec("INSERT INTO contest_problem (problem_id, contest_id, num) VALUES (?,?,?)", [$pid, $id, $num++]);
        }
        $ok = '比赛已保存';
    }
}

$row = $id ? Database::fetchOne("SELECT * FROM contest WHERE contest_id=?", [$id]) : null;
$linked = [];
if ($id) {
    foreach (Database::fetchAll("SELECT problem_id FROM contest_problem WHERE contest_id=? ORDER BY num ASC", [$id]) as $r) {
        $linked[] = (int)$r['problem_id'];
    }
}
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header">
    <h1><?= $id ? '✏️ 编辑比赛 #' . $id : '➕ 添加比赛' ?></h1>
    <a href="contest_list.php" class="btn btn-outline btn-sm">← 返回列表</a>
</div>
<div class="card" style="max-width:800px">
    <?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>比赛名称 *</label>
            <input type="text" name="title" class="form-input" value="<?=htmlspecialchars($row['title'] ?? '')?>" required></div>

        <div class="form-row">
            <div class="form-group"><label>开始时间 *</label>
                <input type="datetime-local" name="start_time" class="form-input" value="<?=htmlspecialchars(dt_input($row['start_time'] ?? ''))?>" required></div>
            <div class="form-group"><label>结束时间 *</label>
                <input type="datetime-local" name="end_time" class="form-input" value="<?=htmlspecialchars(dt_input($row['end_time'] ?? ''))?>" required></div>
        </div>

        <div class="form-row">
            <div class="form-group"><label>赛制</label>
                <select name="contest_type" class="form-input">
                    <option value="0" <?= (int)($row['contest_type'] ?? 0) === 0 ? 'selected' : '' ?>>ICPC（通过题数 + 罚时）</option>
                    <option value="1" <?= (int)($row['contest_type'] ?? 0) === 1 ? 'selected' : '' ?>>OI（按总分）</option>
                </select></div>
            <div class="form-group"><label>可见性</label>
                <select name="private" class="form-input">
                    <option value="0" <?= ($row['private'] ?? '0') === '0' ? 'selected' : '' ?>>公开</option>
                    <option value="1" <?= ($row['private'] ?? '0') === '1' ? 'selected' : '' ?>>私有（仅标记）</option>
                </select></div>
            <div class="form-group"><label>状态</label>
                <select name="defunct" class="form-input">
                    <option value="N" <?= ($row['defunct'] ?? 'N') === 'N' ? 'selected' : '' ?>>正常</option>
                    <option value="Y" <?= ($row['defunct'] ?? 'N') === 'Y' ? 'selected' : '' ?>>隐藏</option>
                </select></div>
        </div>

        <div class="form-group">
            <label>比赛题目（按顺序填写题目 ID，用逗号 / 空格 / 换行分隔，依次对应 A、B、C…）</label>
            <textarea name="problems" class="form-input" style="min-height:80px" placeholder="1001, 1002, 1003"><?=htmlspecialchars(implode(', ', $linked))?></textarea>
        </div>

        <div class="form-group"><label>比赛说明（支持 Markdown）</label>
            <textarea name="description" class="form-input" style="min-height:120px"><?=htmlspecialchars($row['description'] ?? '')?></textarea></div>

        <button type="submit" class="btn btn-primary">保存</button>
    </form>
</div>

<?php if ($id): ?>
<div class="card" style="max-width:800px">
    <h2>📋 已关联题目</h2>
    <?php if ($linked): ?>
    <table><thead><tr><th style="width:60px">题号</th><th>题目 ID</th><th>标题</th></tr></thead><tbody>
    <?php foreach ($linked as $i => $pid): $p = Database::fetchOne("SELECT title FROM problem WHERE problem_id=?", [$pid]); ?>
    <tr><td style="font-weight:700;color:var(--accent)"><?=chr(65 + ($i % 26))?></td><td><?=$pid?></td>
        <td><?=htmlspecialchars($p['title'] ?? '（题目不存在）')?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p style="color:var(--text-muted)">尚未关联题目</p><?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../inc/footer.php'; ?>

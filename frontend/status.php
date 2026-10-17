<?php
$title = '评测状态';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';

$pid    = trim($_GET['pid'] ?? '');
$uid    = trim($_GET['uid'] ?? '');
$lang   = trim($_GET['lang'] ?? '');
$result = $_GET['result'] ?? '';
$mine   = isset($_GET['mine']);
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 30;

if ($mine && !empty($_SESSION['airoj_user']['user_id'])) {
    $uid = $_SESSION['airoj_user']['user_id'];
}

// 组装筛选条件
$where = [];
$params = [];
if ($pid !== '')  { $where[] = "problem_id = ?"; $params[] = $pid; }
if ($uid !== '')  { $where[] = "user_id = ?";    $params[] = $uid; }
if ($lang !== '') { $where[] = "judge_lang = ?"; $params[] = $lang; }
if ($result === 'ac')  $where[] = "status = 'done' AND score >= 100";
if ($result === 'wa')  $where[] = "status = 'done' AND score < 100";
if ($result === 'err') $where[] = "status IN ('failed','pending','judging')";
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions $whereSql", $params)['c'] ?? 0;
$rows  = Database::fetchAll(
    "SELECT id, problem_id, judge_lang, status, score, passed_cases, total_cases, user_id, created_at
     FROM submissions $whereSql ORDER BY id DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, ($page - 1) * $limit])
);
$maxP = max(1, ceil($total / $limit));
$langs = get_language_configs();

// 保留筛选条件的分页链接
$qs = $_GET; unset($qs['page']);
function page_url(array $qs, int $p): string { return '?' . http_build_query(array_merge($qs, ['page' => $p])); }
?>
<div class="page-header">
    <h1><i class="fa-solid fa-chart-simple"></i> 评测状态</h1>
    <?php if (!empty($_SESSION['airoj_user'])): ?>
    <a href="?mine=1" class="btn btn-<?= $mine ? 'primary' : 'outline' ?> btn-sm"><i class="fa-solid fa-user"></i> 我的提交</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" class="form-row" style="align-items:flex-end">
        <?php if ($mine): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
        <div class="form-group" style="max-width:140px"><label>题目 ID</label>
            <input type="text" name="pid" class="form-input" value="<?=htmlspecialchars($pid)?>" placeholder="如 1001"></div>
        <div class="form-group" style="max-width:180px"><label>用户</label>
            <input type="text" name="uid" class="form-input" value="<?=htmlspecialchars($uid)?>" placeholder="用户名"></div>
        <div class="form-group" style="max-width:170px"><label>语言</label>
            <select name="lang" class="form-input">
                <option value="">全部</option>
                <?php foreach ($langs as $k => $v): ?>
                <option value="<?=$k?>" <?=$lang === $k ? 'selected' : ''?>><?=htmlspecialchars($v['name'])?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-group" style="max-width:150px"><label>结果</label>
            <select name="result" class="form-input">
                <option value="">全部</option>
                <option value="ac"  <?=$result === 'ac'  ? 'selected' : ''?>>通过 (AC)</option>
                <option value="wa"  <?=$result === 'wa'  ? 'selected' : ''?>>未通过</option>
                <option value="err" <?=$result === 'err' ? 'selected' : ''?>>等待 / 失败</option>
            </select></div>
        <div class="form-group" style="max-width:120px">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> 筛选</button></div>
        <div class="form-group" style="max-width:100px">
            <a href="status.php" class="btn btn-outline">重置</a></div>
    </form>
</div>

<div class="card" style="padding:0">
<div class="table-wrap">
<table>
<thead><tr><th style="width:70px">#</th><th>题目</th><th style="width:140px">用户</th><th style="width:120px">语言</th><th style="width:90px">状态</th><th style="width:80px">分数</th><th style="width:90px">通过</th><th style="width:160px">提交时间</th><th style="width:130px">操作</th></tr></thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text-muted)">暂无提交记录</td></tr>
<?php else: foreach ($rows as $r): $sc = $r['score']; ?>
<tr>
<td style="font-weight:600"><?= $r['id'] ?></td>
<td><a href="problem.php?id=<?= urlencode($r['problem_id']) ?>"><?= htmlspecialchars($r['problem_id']) ?></a></td>
<td><?= user_role_html($r['user_id']) ?></td>
<td><?= htmlspecialchars($r['judge_lang']) ?></td>
<td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
<td style="font-weight:600;color:<?= $sc >= 100 ? 'var(--green)' : ($sc !== null ? 'var(--red)' : 'var(--text-muted)') ?>"><?= $sc !== null ? $sc : '-' ?></td>
<td><?= $r['passed_cases'] ?> / <?= $r['total_cases'] ?></td>
<td style="color:var(--text-muted);font-size:12px"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
<td>
    <a href="result.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-xs">查看</a>
    <a href="source.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-xs">源码</a>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>
<?php if ($maxP > 1): ?>
<div class="pagination"><?php for ($i = 1; $i <= $maxP; $i++): ?><a href="<?= htmlspecialchars(page_url($qs, $i)) ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?=$i?></a><?php endfor; ?></div>
<?php endif;
require 'inc/footer.php'; ?>

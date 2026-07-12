<?php
$title = '用户信息';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';

$uid = $_GET['uid'] ?? ($_SESSION['airoj_user']['user_id'] ?? '');
if (!$uid) { echo '<div class="empty"><p>请指定用户</p></div>'; require 'inc/footer.php'; exit; }
$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
if (!$user) { echo '<div class="empty"><p>用户不存在</p></div>'; require 'inc/footer.php'; exit; }

$total = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions")['c'] ?? 0;
$ac = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions WHERE score>=100")['c'] ?? 0;
$subs = Database::fetchAll("SELECT id,problem_id,status,score FROM submissions ORDER BY id DESC LIMIT 10");
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$uid]);
$rating = round($user['rating'] ?? 1500);
?>
<?php
require_once 'inc/functions.php';
$role_info = user_role_html($uid);
?><div class="page-header"><h1>👤 <?= $role_info ?></h1></div>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">昵称</div><div class="stat-value" style="font-size:18px;color:var(--accent)"><?= htmlspecialchars($user['nick'] ?: $uid) ?></div></div>
    <div class="stat-card"><div class="stat-label">通过</div><div class="stat-value" style="color:var(--green)"><?= $ac ?></div></div>
    <div class="stat-card"><div class="stat-label">提交</div><div class="stat-value" style="color:var(--yellow)"><?= $total ?></div></div>
    <div class="stat-card"><div class="stat-label">已解决</div><div class="stat-value" style="color:var(--green);font-size:20px"><?= $user['solved_count'] ?? 0 ?></div></div>
    <div class="stat-card"><div class="stat-label">Rating</div><div class="stat-value" style="color:var(--accent-gold)"><?= $rating ?></div></div>
    <div class="stat-card"><div class="stat-label">注册时间</div><div class="stat-value" style="font-size:14px"><?= $user['reg_time'] ?? '-' ?></div></div>
    <?php if ($is_admin): ?><div class="stat-card"><div class="stat-label">身份</div><div class="stat-value" style="color:var(--accent-gold);font-size:16px">🔑 管理员</div></div><?php endif; ?>
</div>

<?php if (!empty($user['bio'])): ?>
<div class="card">
    <h2>📝 个人简介</h2>
    <div style="font-size:14px;color:var(--text-secondary);line-height:1.7"><?php
        require_once 'inc/Parsedown.php';
        $pd = new Parsedown();
        echo $pd->text($user['bio']);
    ?></div>
</div>
<?php endif; ?>

<?php if ($subs): ?>
<div class="section"><h2 class="section-title">📊 提交记录</h2>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr><th>#</th><th>题目</th><th>状态</th><th>分数</th></tr></thead>
<tbody><?php foreach ($subs as $s): ?>
<tr><td><?=$s['id']?></td><td><a href="problem.php?id=<?=$s['problem_id']?>"><?=$s['problem_id']?></a></td>
<td><span class="badge badge-<?=$s['status']?>"><?=$s['status']?></span></td>
<td style="font-weight:600;color:<?=($s['score']??0)>=100?'var(--green)':'var(--red)'?>"><?=$s['score']??'-'?></td></tr>
<?php endforeach; ?></tbody></table></div></div></div>
<?php endif;
require 'inc/footer.php'; ?>

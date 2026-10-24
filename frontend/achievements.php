<?php
$title = '成就';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';

$view_uid = trim((string)($_GET['uid'] ?? ''));
$me = $_SESSION['airoj_user']['user_id'] ?? null;
if ($view_uid === '') $view_uid = $me;
$view_user = $view_uid ? Database::fetchOne("SELECT user_id, nick FROM users WHERE user_id=?", [$view_uid]) : null;

$achievements = Database::fetchAll(
    "SELECT a.*, ua.unlocked_at FROM achievements a
     LEFT JOIN user_achievements ua ON ua.achievement_id=a.achievement_id AND ua.user_id=?
     ORDER BY a.achievement_id",
    [$view_uid ?: '']
);
$unlocked = 0;
foreach ($achievements as $a) if (!empty($a['unlocked_at'])) $unlocked++;
?>
<div class="page-header">
    <h1><i class="fa-solid fa-trophy"></i> 成就</h1>
    <?php if ($view_user): ?>
    <span style="color:var(--text-secondary);font-size:13px">
        <?= user_role_html($view_user['user_id'], $view_user['nick'] ?: $view_user['user_id']) ?>
        · 已解锁 <?= $unlocked ?> / <?= count($achievements) ?>
    </span>
    <?php else: ?>
    <span style="color:var(--text-muted);font-size:13px">登录后可查看自己的成就进度</span>
    <?php endif; ?>
</div>

<?php if (!$achievements): ?>
<div class="empty"><div class="icon"><i class="fa-solid fa-trophy"></i></div><p>暂无成就</p></div>
<?php else: ?>
<div class="achievement-grid">
    <?php foreach ($achievements as $a): $got = !empty($a['unlocked_at']); ?>
    <div class="achievement-card<?= $got ? '' : ' locked' ?>" style="<?= $got ? '--ach-color:' . htmlspecialchars($a['color']) : '' ?>">
        <div class="achievement-icon"><i class="fa-solid <?= htmlspecialchars($a['icon']) ?>"></i></div>
        <div class="achievement-info">
            <strong><?= htmlspecialchars($a['name']) ?></strong>
            <small><?= htmlspecialchars($a['description'] ?? '') ?></small>
            <?php if ($got): ?>
            <span class="achievement-time"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($a['unlocked_at']) ?> 解锁</span>
            <?php else: ?>
            <span class="achievement-time"><i class="fa-solid fa-lock"></i> 未解锁</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require 'inc/footer.php'; ?>

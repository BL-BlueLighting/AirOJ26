<?php
$title = '个人中心';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
require_once 'inc/Parsedown.php';
if (session_status() === PHP_SESSION_NONE) @session_start();

$viewer = $_SESSION['airoj_user'] ?? null;
$profile_uid = trim((string)($_GET['uid'] ?? ''));
if ($profile_uid === '') {
    if (!$viewer) { header('Location: /login.php'); exit; }
    $profile_uid = (string)$viewer['user_id'];
}

$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$profile_uid]);
if (!$user) {
    $title = '用户不存在';
    require 'inc/header.php';
    echo '<div class="empty"><div class="icon"><i class="fa-solid fa-user-slash"></i></div><p>用户不存在</p></div>';
    require 'inc/footer.php';
    exit;
}

$is_self = $viewer && (string)$viewer['user_id'] === $profile_uid;
$title = $is_self ? '个人中心' : '用户主页';
$uid = $profile_uid;

$rating = round($user['rating'] ?? 1500);
$solved_count = $user['solved_count'] ?? 0;
$submit_count = $user['submit_count'] ?? 0;
$total_sub = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions WHERE user_id=?", [$uid])['c'] ?? 0;
$ac_sub = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions WHERE user_id=? AND score>=100", [$uid])['c'] ?? 0;

// 已通过题目列表（按ID排序）
$ac_problems = Database::fetchAll(
    "SELECT DISTINCT s.problem_id FROM submissions s WHERE s.user_id=? AND s.score>=100 AND s.status='done' ORDER BY CAST(s.problem_id AS INTEGER) ASC",
    [$uid]
);
$ac_count = count($ac_problems);

// 最近提交
$subs = Database::fetchAll("SELECT id,problem_id,judge_lang,status,score FROM submissions WHERE user_id=? ORDER BY id DESC LIMIT 15", [$uid]);

// 成就与收藏品
$achievements = Database::fetchAll(
    "SELECT a.*, ua.unlocked_at FROM achievements a
     LEFT JOIN user_achievements ua ON ua.achievement_id=a.achievement_id AND ua.user_id=?
     ORDER BY a.achievement_id", [$uid]);
$equipped = user_equipped_collectibles($uid);
$ownedCount = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM user_collectibles WHERE user_id=?", [$uid])['c'] ?? 0);

$pd = new Parsedown();
$pd->setSafeMode(true);
$userBio = trim((string)($user['bio'] ?? ''));

require 'inc/header.php';
// header.php 会用 session 覆盖 $user，这里重新读一次数据库，保证资料/头像/背景是最新的
$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
$userBio = trim((string)($user['bio'] ?? ''));
?>

<div class="page-header"><h1><i class="fa-solid fa-user"></i> <?= $is_self ? '个人中心' : '用户主页' ?></h1></div>

<div class="profile-wrap">
    <div class="profile-side">
        <!-- 头像 -->
        <div class="profile-avatar">
            <img src="<?= htmlspecialchars(user_avatar_url($user, 200)) ?>" alt="avatar">
            <div class="pname"><?= user_role_html($uid, $user['nick'] ?: $uid) ?></div>
            <div class="pid"><?= htmlspecialchars($uid) ?></div>
        </div>

        <!-- 统计数据 -->
        <div class="profile-stats">
            <div class="row"><span class="lbl">Rating</span><span class="val" style="color:var(--accent-gold)"><?= $rating ?></span></div>
            <div class="row"><span class="lbl">已通过</span><span class="val" style="color:var(--green)"><?= $ac_count ?></span></div>
            <div class="row"><span class="lbl">总提交</span><span class="val" style="color:var(--accent)"><?= $total_sub ?></span></div>
            <div class="row"><span class="lbl">通过率</span><span class="val" style="color:var(--yellow)"><?= $total_sub > 0 ? round($ac_sub / $total_sub * 100, 1) : 0 ?>%</span></div>
            <div class="row"><span class="lbl">AC 提交</span><span class="val" style="color:var(--green)"><?= $ac_sub ?></span></div>
        </div>

        <!-- 个人信息 -->
        <div class="profile-info">
            <div class="row"><span class="lbl">邮箱</span><span class="val"><?= htmlspecialchars($user['email'] ?? '-') ?></span></div>
            <div class="row"><span class="lbl">学校</span><span class="val"><?= htmlspecialchars($user['school'] ?? '-') ?></span></div>
            <div class="row"><span class="lbl">注册时间</span><span class="val"><?= $user['reg_time'] ?? '-' ?></span></div>
        </div>

        <?php if ($is_self || $viewer): ?>
        <div style="margin-top:12px;text-align:center;display:flex;gap:8px;justify-content:center">
            <?php if ($is_self): ?>
            <a href="/settings.php?tab=profile" class="btn btn-outline btn-sm"><i class="fa-solid fa-gear"></i> 修改资料</a>
            <?php endif; ?>
            <a href="/messages.php?compose=1&amp;to=<?= urlencode($uid) ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-envelope"></i> 发站内信</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="profile-main">
        <?php if ($userBio !== ''): ?>
        <!-- 个人简介 / 签名 -->
        <div class="card">
            <h2><i class="fa-solid fa-signature"></i> 个人简介</h2>
            <div class="mdui-prose" style="font-size:14px;line-height:1.8"><?= $pd->text($userBio) ?></div>
        </div>
        <?php endif; ?>

        <!-- 成就 -->
        <div class="card">
            <h2><i class="fa-solid fa-trophy"></i> 成就</h2>
            <?php if ($achievements): ?>
            <div class="achievement-grid">
                <?php foreach ($achievements as $a): $unlocked = !empty($a['unlocked_at']); ?>
                <div class="achievement-card<?= $unlocked ? '' : ' locked' ?>" style="<?= $unlocked ? '--ach-color:' . htmlspecialchars($a['color']) : '' ?>">
                    <div class="achievement-icon"><i class="fa-solid <?= htmlspecialchars($a['icon']) ?>"></i></div>
                    <div class="achievement-info">
                        <strong><?= htmlspecialchars($a['name']) ?></strong>
                        <small><?= htmlspecialchars($a['description'] ?? '') ?></small>
                        <?php if ($unlocked): ?><span class="achievement-time"><?= htmlspecialchars($a['unlocked_at']) ?> 解锁</span>
                        <?php else: ?><span class="achievement-time">未解锁</span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted)">暂无成就。</p>
            <?php endif; ?>
            <p style="margin-top:12px"><a href="/achievements.php?uid=<?= urlencode($uid) ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-trophy"></i> 查看全部成就</a></p>
        </div>

        <!-- 收藏品 -->
        <div class="card">
            <h2><i class="fa-solid fa-gem"></i> 收藏品</h2>
            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">已拥有 <?= $ownedCount ?> 件收藏品。</p>
            <div class="equip-summary">
                <?php foreach (AIR_OJ_COLLECTIBLE_TYPES as $type => $meta): ?>
                <div class="equip-item">
                    <span class="equip-label"><i class="fa-solid <?= $meta['icon'] ?>"></i> <?= htmlspecialchars($meta['label']) ?></span>
                    <span class="equip-value">
                    <?php if (!empty($equipped[$type])): ?>
                        <?= htmlspecialchars($equipped[$type]['name']) ?>
                    <?php else: ?>
                        <span style="color:var(--text-muted)">未装备</span>
                    <?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($is_self): ?>
            <a href="/settings.php?tab=collectibles" class="btn btn-outline btn-sm" style="margin-top:12px"><i class="fa-solid fa-gem"></i> 管理收藏品</a>
            <?php endif; ?>
        </div>

        <!-- 已通过题目 -->
        <div class="card">
            <h2><i class="fa-solid fa-circle-check"></i> 已通过题目 (<?= $ac_count ?>)</h2>
            <?php if ($ac_problems): ?>
            <div style="line-height:2">
                <?php foreach ($ac_problems as $p): ?>
                <a href="/problem.php?id=<?= $p['problem_id'] ?>" class="solved-tag">#<?= $p['problem_id'] ?></a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted)">暂时还没有通过的题目。</p>
            <?php endif; ?>
        </div>

        <!-- 最近提交 -->
        <div class="card" style="padding:0">
            <div style="padding:16px 20px 0;font-weight:700;font-size:14px"><i class="fa-solid fa-chart-simple"></i> 最近提交</div>
            <?php if ($subs): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>题目</th><th>语言</th><th>状态</th><th>分数</th><th>操作</th></tr></thead>
                    <tbody>
                    <?php foreach ($subs as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><a href="/problem.php?id=<?= $s['problem_id'] ?>"><?= $s['problem_id'] ?></a></td>
                        <td><?= $s['judge_lang'] ?></td>
                        <td><span class="badge badge-<?= $s['status'] ?>"><?= $s['status'] ?></span></td>
                        <td style="font-weight:600;color:<?= ($s['score']??0)>=100 ? 'var(--green)' : 'var(--red)' ?>"><?= $s['score'] ?? '-' ?></td>
                        <td><a href="/result.php?id=<?= $s['id'] ?>" class="btn btn-primary btn-xs">查看</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="padding:20px;text-align:center;color:var(--text-muted)">暂无提交记录。</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'inc/footer.php'; ?>

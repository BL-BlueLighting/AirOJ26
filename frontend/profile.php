<?php
$title = '个人中心';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (!isset($_SESSION['airoj_user'])) { header('Location: /login.php'); exit; }

$uid = $_SESSION['airoj_user']['user_id'];
$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
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

// 邮箱 MD5 用于 Gravatar
$email_md5 = md5(strtolower(trim($user['email'] ?? '')));
require 'inc/header.php'; ?>
<style>
.profile-wrap { display: flex; gap: 24px; align-items: flex-start; }
.profile-side { width: 260px; flex-shrink: 0; }
.profile-main { flex: 1; min-width: 0; }
@media (max-width: 768px) { .profile-wrap { flex-direction: column; } .profile-side { width: 100%; } }

.profile-avatar { text-align: center; padding: 24px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); margin-bottom: 16px; }
.profile-avatar img { width: 100px; height: 100px; border-radius: 50%; border: 3px solid var(--border); margin-bottom: 12px; }
.profile-avatar .pname { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
.profile-avatar .pid { font-size: 13px; color: var(--text-muted); }

.profile-stats { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 16px; margin-bottom: 16px; }
.profile-stats .row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.profile-stats .row:last-child { border-bottom: none; }
.profile-stats .row .lbl { color: var(--text-muted); }
.profile-stats .row .val { font-weight: 600; }

.profile-info { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 16px; }
.profile-info .row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; }
.profile-info .row .lbl { color: var(--text-muted); }
.profile-info .row .val { text-align: right; color: var(--text-secondary); word-break: break-all; }

.solved-tag { display: inline-block; padding: 2px 10px; margin: 3px; border-radius: 4px; font-size: 13px; background: var(--bg-secondary); border: 1px solid var(--border); color: var(--green); text-decoration: none; transition: all .2s; }
.solved-tag:hover { border-color: var(--green); background: var(--green-bg); }
</style>

<div class="page-header"><h1>👤 个人中心</h1></div>

<div class="profile-wrap">
    <div class="profile-side">
        <!-- 头像 -->
        <div class="profile-avatar">
            <img src="https://www.gravatar.com/avatar/<?= $email_md5 ?>?s=200&d=identicon" alt="avatar">
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

        <div style="margin-top:12px;text-align:center">
            <a href="/modify.php" class="btn btn-outline btn-sm">⚙️ 修改资料</a>
        </div>
    </div>

    <div class="profile-main">
        <!-- 已通过题目 -->
        <div class="card">
            <h2>✅ 已通过题目 (<?= $ac_count ?>)</h2>
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
            <div style="padding:16px 20px 0;font-weight:700;font-size:14px">📊 最近提交</div>
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

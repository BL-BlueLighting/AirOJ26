<?php
$title = '首页';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();

// 抽取今日运势（登录用户写入数据库，换设备也保持已抽取状态）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['draw_fortune'])) {
    $today = date('Y-m-d');
    $uid = $_SESSION['airoj_user']['user_id'] ?? null;
    if ($uid) {
        Database::exec("UPDATE users SET fortune_drawn=? WHERE user_id=?", [$today, $uid]);
        $_SESSION['airoj_user']['fortune_drawn'] = $today;
    } else {
        $_SESSION['guest_fortune_drawn'] = $today;
    }
    header('Location: /index.php');  // PRG：避免刷新重复提交
    exit;
}

require 'inc/header.php';

$user = $_SESSION['airoj_user'] ?? null;
$total  = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions")['c'] ?? 0;
$ac     = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions WHERE score>=100")['c'] ?? 0;
$pcount = count(array_filter(glob(INPUTS_DIR . '/*'), 'is_dir'));
$ucount = Database::fetchOne("SELECT COUNT(*) AS c FROM users")['c'] ?? 0;
$news   = Database::fetchAll("SELECT * FROM news ORDER BY pinned DESC, news_id DESC LIMIT 50");
$rank   = Database::fetchAll("SELECT user_id, nick, solved_count, submit_count, rating FROM users ORDER BY solved_count DESC, rating DESC LIMIT 10");

// 今日运势：按「日期 + 用户」确定，同一天固定；并保证不与前一天重复
$fortune_uid = $user['user_id'] ?? '';
$fortunes = ['大吉', '吉', '中吉', '小吉', '末吉', '凶', '大凶'];
$fortune_colors = ['#ff2d78', '#4ade80', '#4da6ff', '#f59e0b', '#a855f7', '#f87171', '#ff2d78'];
$fortune_msgs = [
    '今天是个好日子，AC 如喝水！',
    '今天运势不错，适合挑战难题！',
    '稳住心态，慢慢来～',
    '写代码前先喝杯咖啡 <i class="fa-solid fa-mug-hot"></i>',
    'Debug 之路漫漫，加油！',
    '今天可能 WA 几次，别灰心！',
    '建议今天先休息，明天再战 <i class="fa-solid fa-bed"></i>'
];
$fcount = count($fortunes);
$fortune_idx = crc32(date('Y-m-d') . $fortune_uid) % $fcount;
$yesterday_idx = crc32(date('Y-m-d', strtotime('-1 day')) . $fortune_uid) % $fcount;
if ($fortune_idx === $yesterday_idx) {
    // 与昨天相同则顺移一位（结果仍然每天固定）
    $fortune_idx = ($fortune_idx + 1) % $fcount;
}
$fortune = $fortunes[$fortune_idx];
$fc = $fortune_colors[$fortune_idx];
$fmsg = $fortune_msgs[$fortune_idx];

// 今日是否已抽取：登录用户看数据库（云端保存），游客看 session
$today = date('Y-m-d');
$fortune_drawn = false;
if ($user) {
    $fr = Database::fetchOne("SELECT fortune_drawn FROM users WHERE user_id=?", [$user['user_id']]);
    $fortune_drawn = $fr && (string)($fr['fortune_drawn'] ?? '') === $today;
} else {
    $fortune_drawn = ($_SESSION['guest_fortune_drawn'] ?? '') === $today;
}
?>
<style>
.home-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; }
@media (max-width: 900px) { .home-layout { grid-template-columns: 1fr; } }
.home-main { min-width: 0; }
.home-side { min-width: 0; }

/* Fortune */
.fortune-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px; text-align: center; margin-bottom: 16px; }
.fortune-box .label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
.fortune-box .result { font-size: 36px; font-weight: 800; line-height: 1; margin-bottom: 8px; }
.fortune-box .msg { font-size: 14px; color: var(--text-secondary); }

/* User mini card */
.user-mini { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 16px; }
.user-mini .uname { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
.user-mini .ustats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.user-mini .ustat { text-align: center; padding: 8px; background: var(--bg-secondary); border-radius: var(--radius); }
.user-mini .ustat .num { font-size: 18px; font-weight: 700; }
.user-mini .ustat .lbl { font-size: 11px; color: var(--text-muted); }

/* Search */
.search-box { display: flex; gap: 8px; margin-bottom: 16px; }
.search-box input { flex: 1; }
.search-box .btn { flex-shrink: 0; }

/* Ranklist sidebar */
.rank-mini { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; }
.rank-mini h3 { font-size: 14px; font-weight: 700; margin-bottom: 12px; color: var(--accent-gold); }
.rank-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.rank-item:last-child { border-bottom: none; }
.rank-item .pos { font-weight: 700; color: var(--text-muted); width: 24px; text-align: center; }
.rank-item .pos.top3 { color: var(--accent-gold); }
.rank-item .ruser { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rank-item .rsolved { font-weight: 600; color: var(--green); }
.rank-item .rrating { font-weight: 600; color: var(--accent-gold); font-size: 12px; }
</style>

<div class="home-layout">
    <div class="home-main">

        <?php if ($news): ?>
        <div class="card" style="margin-bottom:16px;padding:0">
            <div style="padding:16px 20px 10px;font-weight:700;font-size:14px;display:flex;align-items:center">
                <i class="fa-solid fa-bullhorn"></i> 公告
                <a href="/news.php" style="margin-left:auto;font-size:12px;font-weight:400">全部公告 <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <table class="news-list">
                <tbody>
                <?php foreach ($news as $i => $n): ?>
                <tr>
                    <td>
                        <?php if (!empty($n['pinned'])): ?><span class="tag tag-gold">置顶</span> <?php endif; ?>
                        <a href="/news.php?id=<?= $n['news_id'] ?>"><?= htmlspecialchars($n['title']) ?></a>
                    </td>
                    <td class="news-date"><?= htmlspecialchars(substr((string)$n['time'], 0, 10)) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card" style="margin-bottom:16px;text-align:center;padding:32px">
            <div style="font-size:32px;margin-bottom:8px"><i class="fa-solid fa-bullhorn"></i></div>
            <p style="color:var(--text-muted)">暂无公告</p>
        </div>
        <?php endif; ?>

        <!-- 排行榜 -->
        <?php if ($rank): ?>
        <div class="card" style="padding:0">
            <div style="padding:16px 20px 8px;font-weight:700;font-size:14px;color:var(--accent-gold)"><i class="fa-solid fa-trophy"></i> 排行榜</div>
            <div class="table-wrap">
            <table>
                <thead><tr><th style="width:36px">#</th><th>用户</th><th>已解决</th><th>Rating</th></tr></thead>
                <tbody>
                <?php $i=1; foreach ($rank as $u): ?>
                <tr>
                    <td style="font-weight:700;color:<?=$i<=3?'var(--accent-gold)':'var(--text-muted)'?>"><?=$i++?></td>
                    <td><a href="/userinfo.php?uid=<?=urlencode($u['user_id'])?>"><?=user_role_html($u['user_id'], $u['nick']?:$u['user_id'])?></a></td>
                    <td style="font-weight:600;color:var(--green)"><?=$u['solved_count']??0?></td>
                    <td style="font-weight:600;color:var(--accent-gold)"><?=round($u['rating']??1500)?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div style="padding:8px 20px 12px;text-align:right"><a href="/ranklist.php" style="font-size:12px">完整排行 <i class="fa-solid fa-arrow-right"></i></a></div>
        </div>
        <?php endif; ?>

    </div>

    <div class="home-side">

        <!-- 搜索 -->
        <form method="get" action="/problem.php" class="search-box">
            <input type="text" name="id" class="form-input" placeholder="搜索题号 (PID)..." required>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>

        <!-- 今日运势（抽取状态保存在服务端，换设备也保持一致） -->
        <div class="fortune-box">
            <div class="label"><i class="fa-solid fa-clover"></i> 今日运势</div>
            <?php if ($fortune_drawn): ?>
            <div class="result" style="color:<?=$fc?>">§ <?= htmlspecialchars($fortune) ?> §</div>
            <div class="msg"><?= $fmsg ?></div>
            <?php else: ?>
            <div class="result" style="color:var(--text-muted)">§ 未抽 §</div>
            <form method="post" action="/index.php" style="margin-top:12px">
                <button type="submit" name="draw_fortune" value="1" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-dice"></i> 抽取
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- 我的信息 -->
        <div class="user-mini">
            <?php if ($user): ?>
            <div class="uname"><?= user_role_html($user['user_id'], $user['nick'] ?: $user['user_id']) ?></div>
            <div class="ustats">
                <div class="ustat"><div class="num" style="color:var(--green)"><?= $user['solved_count'] ?? 0 ?></div><div class="lbl">已解决</div></div>
                <div class="ustat"><div class="num" style="color:var(--accent-gold)"><?= round($user['rating'] ?? 1500) ?></div><div class="lbl">Rating</div></div>
                <div class="ustat"><div class="num" style="color:var(--accent)"><?= Database::fetchOne("SELECT COUNT(*) AS c FROM submissions WHERE user_id=?", [$user['user_id']])['c'] ?? 0 ?></div><div class="lbl">提交</div></div>
                <div class="ustat"><div class="num" style="color:var(--yellow)"><?= $ac ?></div><div class="lbl">总通过</div></div>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:12px 0">
                <p style="color:var(--text-muted);margin-bottom:12px">登录后查看个人信息</p>
                <a href="/login.php" class="btn btn-primary btn-sm">登录</a>
                <a href="/register.php" class="btn btn-outline btn-sm">注册</a>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require 'inc/footer.php'; ?>

<?php
$title = '首页';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
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

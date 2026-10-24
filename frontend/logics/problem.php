<?php
$title = '题目';
// 布局偏好存在 cookie 里，服务端渲染时直接决定是否全屏，避免闪烁
// 也支持 ?layout=focus / ?layout=dual 临时覆盖
$pw_focus = ($_GET['layout'] ?? $_COOKIE['pw_layout'] ?? 'dual') === 'focus';
$body_class = 'pw-page' . ($pw_focus ? ' pw-focus' : '');
require_once 'include/config.php';
require_once 'include/functions.php';
require_once 'include/Parsedown.php';

$requestedId = (int)($_GET['id'] ?? 1000);
$cid = intval($_GET['cid'] ?? 0);
$langs = get_language_configs();
$err = $_GET['err'] ?? '';
$ok = $_GET['ok'] ?? '';

// 从数据库读取题目信息，并解析题目组（同一道题的多个难度版本）
require_once 'include/db.php';
$row = Database::fetchOne("SELECT * FROM problem WHERE problem_id=?", [$requestedId]);
$contest = $cid ? Database::fetchOne("SELECT * FROM contest WHERE contest_id=?", [$cid]) : null;
if (!$contest) $cid = 0;

$groupMembers = [];
$isGroup = false;
$mainId = $requestedId;
if ($row) {
    $gid = problem_group_main_id($row);
    $mainId = $gid;
    $groupMembers = problem_group_members($gid);
    $isGroup = count($groupMembers) > 1;
    $main = problem_group_main($row);

    // 直接访问某个难度版本时，跳到主记录并带上 v
    if ($isGroup && (int)$main['problem_id'] !== $requestedId) {
        header('Location: /problem.php?id=' . $mainId . '&v=' . $requestedId . ($cid ? '&cid=' . $cid : ''));
        exit;
    }

    if ($isGroup) {
        $v = (int)($_GET['v'] ?? 0);
        $problem = null;
        foreach ($groupMembers as $m) {
            if ((int)$m['problem_id'] === $v) { $problem = $m; break; }
        }
        if (!$problem) $problem = $groupMembers[0];   // 默认最低难度
    } else {
        $problem = $main;
    }
    $pid = (string)$problem['problem_id'];
} else {
    $problem = null;
    $pid = (string)$requestedId;
}

$caseCount = count_test_cases($pid);
$firstCase = load_first_test_input($pid);

// 标签、难度、通过统计、越级检查（难度按当前选中的版本计算）
$tags     = problem_tags($pid);
$diffs    = problem_difficulties($pid);
$acStats  = $isGroup ? problem_group_ac_stats($groupMembers) : problem_ac_stats($pid);
$tierInfo = problem_tier_check($pid, $user);

$pd = new Parsedown();
$pd->setSafeMode(true);

function md(?string $text, Parsedown $pd): string {
    // mdui-prose 只在 MDUI 主题（mdui3 / mdui_fake，加载了 mdui.css）下生效
    return $text
        ? '<div class="mdui-prose">' . $pd->text($text) . '</div>'
        : '<p style="color:var(--text-muted)">（暂无内容）</p>';
}

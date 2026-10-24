<?php
/**
 * 重算 Hydro 迁移用户的 Rating
 *
 * 用法：php maintain/recalc_rating.php [--dry-run]
 *
 * 对每个目标用户从 0 开始，按提交时间顺序处理：
 * 只在某个题目第一次 AC 时计算一次 Rating（与 submitJudge / apply_rating_for_ac 的规则一致）。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';

$dry = in_array('--dry-run', $argv, true);

// 目标：Hydro 迁移账号，以及有 Hydro 迁移提交的用户（例如已经改过密码的 BL）
$targets = Database::fetchAll(
    "SELECT user_id FROM users WHERE hydro_hash IS NOT NULL
     UNION
     SELECT DISTINCT user_id FROM submissions WHERE hydro_record_id IS NOT NULL"
);

foreach ($targets as $t) {
    $uid = (string)$t['user_id'];
    if ($uid === '') continue;

    $subs = Database::fetchAll(
        "SELECT id, problem_id, status, score, created_at
         FROM submissions WHERE user_id=?
         ORDER BY created_at ASC, id ASC",
        [$uid]
    );

    $old = (int)round((float)(Database::fetchOne("SELECT rating FROM users WHERE user_id=?", [$uid])['rating'] ?? 0));
    // AirOJ 初始 Rating 为 1500（install.php / register.php 的初始值）
    $rating = 1500;
    $acCount = 0;
    $solved = [];
    $attempts = [];

    foreach ($subs as $s) {
        $pid = (string)$s['problem_id'];
        $prevAttempts = $attempts[$pid] ?? 0;
        $attempts[$pid] = $prevAttempts + 1;

        $isAc = $s['status'] === 'done' && (float)$s['score'] >= 100;
        if (!$isAc || isset($solved[$pid])) continue;

        $solved[$pid] = true;
        $Q = problem_quality($pid);
        if ($Q > 0) {
            $delta = calculate_rating_delta($rating, $Q, $acCount, $prevAttempts, $prevAttempts);
            $new = $rating + $delta;

            // 越级限制：上限为题目所在颜色分级的最低分 - 500
            $pt = quality_tier($Q);
            $ut = rating_tier($rating);
            $levels = array_keys(AIR_OJ_RATING_TIERS);
            if (array_search($pt, $levels, true) > array_search($ut, $levels, true)) {
                $cap = (int)AIR_OJ_RATING_TIERS[$pt]['min'] - 500;
                $new = min($new, $cap);
            }
            $rating = (int)round(max(0, min(20000, $new)));
        }
        $acCount++;
    }

    if (!$dry) {
        Database::exec(
            "UPDATE users SET rating=?, solved_count=?, solved=? WHERE user_id=?",
            [$rating, $acCount, $acCount, $uid]
        );
    }

    printf(
        "%-20s old=%-6d new=%-6d AC=%-3d submissions=%d\n",
        $uid, $old, $rating, $acCount, count($subs)
    );
}

echo $dry ? "DRY RUN（未写入）\n" : "完成\n";

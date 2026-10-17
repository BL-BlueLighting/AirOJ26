<?php
/**
 * AirOJ — Helper Functions
 */

/**
 * 生成 Judge Token
 * token = md5( dayhour * SECRET )
 * dayhour = int(YYYYMMDDHH)
 */
function generate_judge_token(): string
{
    $dayhour = (int) date('YmdH');
    return md5((string) ($dayhour * JUDGE_SECRET));
}

/**
 * 调用 JudgeServer API
 */
function judge_server_api(string $path, array $data): array
{
    $url  = rtrim(JUDGE_SERVER_URL, '/') . '/' . ltrim($path, '/');
    $hash = hash('sha256', JUDGE_SERVER_TOKEN);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Judge-Server-Token: ' . $hash,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['err' => 'JudgeServer unreachable: ' . $err];
    }

    return json_decode($resp, true) ?: ['err' => 'Invalid response from JudgeServer'];
}

/**
 * 提交评测到 JudgeServer
 * 先插入本地 DB 获得 ID，再调 JudgeServer 同步评测，更新结果
 * 返回 ['judge_commitid' => int, 'error' => ?string]
 */
function submitJudge(string $token, string $judgeType, string $source, string $lang, string $pid, int $contestId = 0): array
{
    require_once __DIR__ . '/db.php';

    $langCfg = build_language_config($lang);
    if (!$langCfg) return ['error' => 'Unsupported language'];

    // 加载测试用例
    $cases = load_test_cases($pid);
    if (!$cases) return ['error' => 'No test cases found'];

    // 先插入本地 DB
    $uid = $_SESSION['airoj_user']['user_id'] ?? 'guest';
    $sid = Database::insert("INSERT INTO submissions (problem_id, judge_type, judge_lang, code, status, user_id, contest_id) VALUES (?,?,?,?,?,?,?)",
        [$pid, $judgeType, $lang, $source, 'judging', $uid, $contestId]);

    // 调 JudgeServer 同步评测
    $payload = [
        'language_config' => $langCfg,
        'src' => $source,
        'max_cpu_time' => 5000,
        'max_memory' => 268435456,
        'test_case' => [],
        'output' => false,
    ];
    foreach ($cases as $c) {
        $payload['test_case'][] = ['input' => $c['input'], 'output' => $c['output']];
    }

    // Special Judge：题目启用特判且存在特判源码时，交给 JudgeServer 编译执行
    $prow = Database::fetchOne("SELECT spj FROM problem WHERE problem_id=?", [$pid]);
    $spjPath = spj_source_path($pid);
    if ($prow && (string)($prow['spj'] ?? '0') === '1' && $spjPath) {
        $ver = substr(md5_file($spjPath), 0, 8);
        $payload['spj_version'] = $ver;
        $payload['spj_src'] = (string)file_get_contents($spjPath);
        $payload['spj_config'] = [
            'command'      => '{exe_path} {in_file_path} {user_out_file_path}',
            'seccomp_rule' => 'c_cpp',
            'exe_name'     => 'spj_{spj_version}',
        ];
        $payload['spj_compile_config'] = [
            'src_name'        => 'spj_{spj_version}.cpp',
            'exe_name'        => 'spj_{spj_version}',
            'max_cpu_time'    => 3000,
            'max_real_time'   => 10000,
            'max_memory'      => 268435456,
            'compile_command' => '/usr/bin/g++ -O2 -w -o {exe_path} {src_path}',
            'env'             => ['PATH=/usr/bin:/bin'],
        ];
    }

    $resp = judge_server_api('judge', $payload);

    if (isset($resp['err']) && $resp['err']) {
        $err_msg = $resp['err'];
        if (!empty($resp['data']) && is_string($resp['data'])) $err_msg .= ': ' . $resp['data'];
        Database::exec("UPDATE submissions SET status='failed', result_json=? WHERE id=?", [$err_msg, $sid]);
        return ['error' => $resp['err'], 'judge_commitid' => $sid];
    }

    $results = $resp['data'] ?? [];
    $passed = 0;
    $total = count($cases);
    $case_results = [];
    $result_map = [0=>'AC',-1=>'WA',1=>'TLE',2=>'TLE',3=>'MLE',4=>'RE',5=>'SE'];

    foreach ($results as $i => $r) {
        $verdict = $result_map[$r['result'] ?? 5] ?? 'UK';
        if ($verdict === 'AC') $passed++;
        $case_results[] = ['name' => ($cases[$i]['name'] ?? $i+1), 'verdict' => $verdict];
    }
    $score = $total > 0 ? round($passed / $total * 100, 1) : 0;

    Database::exec(
        "UPDATE submissions SET status='done', score=?, total_cases=?, passed_cases=?, result_json=? WHERE id=?",
        [$score, $total, $passed, json_encode($case_results, JSON_UNESCAPED_UNICODE), $sid]
    );

    // 更新用户提交统计
    Database::exec("UPDATE users SET submit_count = submit_count + 1 WHERE user_id=?", [$uid]);
    if ($score >= 100) {
        $already = Database::fetchOne("SELECT id FROM submissions WHERE problem_id=? AND score>=100 AND user_id=? AND id<? LIMIT 1", [$pid, $uid, $sid]);
        if (!$already && $uid !== 'guest') {
            // 首次 AC：先按公式更新 Rating（使用更新前的 solved_count），再累加解题数
            apply_rating_for_ac($uid, $pid, $sid);
            Database::exec("UPDATE users SET solved_count = solved_count + 1, solved = solved + 1 WHERE user_id=?", [$uid]);
        }
    }

    return ['judge_commitid' => $sid];
}

/**
 * 加载测试用例（仅加载配对 .in / .out）
 */
function load_test_cases(string $problem_id): ?array
{
    $dir = INPUTS_DIR . '/' . $problem_id;
    if (!is_dir($dir)) return null;

    $files = scandir($dir);
    $in_map  = [];
    $out_map = [];

    foreach ($files as $f) {
        if (str_ends_with($f, '.in'))  $in_map[substr($f, 0, -3)]  = $f;
        if (str_ends_with($f, '.out')) $out_map[substr($f, 0, -4)] = $f;
    }

    $cases = [];
    foreach ($in_map as $stem => $in) {
        if (!isset($out_map[$stem])) continue;
        $inp = file_get_contents($dir . '/' . $in);
        $out = file_get_contents($dir . '/' . $out_map[$stem]);
        if ($inp === false || $out === false) continue;
        $cases[] = [
            'name'   => $stem,
            'input'  => $inp,
            'output' => $out,
        ];
    }

    return $cases ?: null;
}

/**
 * 统计测试点数量（只读目录名，不加载文件内容）
 */
function count_test_cases(string $problem_id): int
{
    $dir = INPUTS_DIR . '/' . $problem_id;
    if (!is_dir($dir)) return 0;

    $in = $out = [];
    foreach (scandir($dir) as $f) {
        if (str_ends_with($f, '.in'))  $in[] = substr($f, 0, -3);
        if (str_ends_with($f, '.out')) $out[] = substr($f, 0, -4);
    }
    return count(array_intersect($in, $out));
}

/**
 * 读取第一组测试数据的输入（供题目页“测试”面板预填）
 * 返回 ['name' => 文件名主干, 'input' => 内容]，没有测试数据时返回 null
 */
function load_first_test_input(string $problem_id): ?array
{
    $dir = INPUTS_DIR . '/' . $problem_id;
    if (!is_dir($dir)) return null;

    $in_map = $out_map = [];
    foreach (scandir($dir) as $f) {
        if (str_ends_with($f, '.in'))  $in_map[substr($f, 0, -3)]  = $f;
        if (str_ends_with($f, '.out')) $out_map[substr($f, 0, -4)] = $f;
    }
    $stems = array_keys(array_intersect_key($in_map, $out_map));
    if (!$stems) return null;
    sort($stems, SORT_NATURAL);

    $stem = $stems[0];
    $content = @file_get_contents($dir . '/' . $in_map[$stem]);
    return $content === false ? null : ['name' => (string)$stem, 'input' => $content];
}

/**
 * 获取语言配置
 */
function get_language_configs(): array
{
    return [
        'c' => [
            'name' => 'C (GCC)',
            'compile' => true,
            'compile_command' => '/usr/bin/gcc -O2 -w -o {exe_path} {src_path} -lm',
            'run_command' => '{exe_path}',
            'seccomp_rule' => 'c_cpp',
            'src_name' => 'main.c',
            'exe_name' => 'main',
        ],
        'cpp' => [
            'name' => 'C++ (G++)',
            'compile' => true,
            'compile_command' => '/usr/bin/g++ -O2 -w -o {exe_path} {src_path} -lm',
            'run_command' => '{exe_path}',
            'seccomp_rule' => 'c_cpp',
            'src_name' => 'main.cpp',
            'exe_name' => 'main',
        ],
        'python3' => [
            'name' => 'Python 3',
            'compile' => false,
            'compile_command' => null,
            'run_command' => '/usr/bin/python3 {exe_path}',
            'seccomp_rule' => 'general',
            'src_name' => 'main.py',
            'exe_name' => 'main.py',
        ],
        'java' => [
            'name' => 'Java',
            'compile' => true,
            // JVM 默认会预留 1GB 以上的虚拟地址空间，编译沙箱里必须限制堆与元空间，
            // 运行时则改为按 RSS 检查（memory_limit_check_only）并用 -Xmx 限定堆大小
            'compile_command' => '/usr/bin/javac -J-Xmx256m -J-XX:CompressedClassSpaceSize=128m -d {exe_dir} {src_path}',
            'compile_memory_check_only' => true,
            'run_options' => ['memory_limit_check_only' => 1],
            'run_command' => '/usr/bin/java -Xmx256m -XX:CompressedClassSpaceSize=128m -cp {exe_dir} Main',
            'seccomp_rule' => 'general',
            'src_name' => 'Main.java',
            'exe_name' => 'Main.class',
        ],
        'go' => [
            'name' => 'Go',
            'compile' => true,
            'compile_command' => '/usr/bin/go build -o {exe_path} {src_path}',
            // go 编译需要可写的 GOCACHE（编译沙箱里没有 HOME）
            'compile_env' => ['GOCACHE=/tmp/airoj-gocache', 'HOME=/tmp'],
            // Go 运行时会预留大块虚拟内存，按 RSS 检查而不是限制地址空间
            'run_options' => ['memory_limit_check_only' => 1],
            'run_command' => '{exe_path}',
            'seccomp_rule' => 'golang',
            'src_name' => 'main.go',
            'exe_name' => 'main',
        ],
        'javascript' => [
            'name' => 'JavaScript (Node.js)',
            'compile' => false,
            'compile_command' => null,
            'run_command' => '/usr/bin/node {exe_path}',
            'seccomp_rule' => 'node',
            'src_name' => 'main.js',
            'exe_name' => 'main.js',
        ],
    ];
}

function get_judge_types(): array
{
    return [
        'standard' => 'Standard IO',
        'spj'      => 'Special Judge',
    ];
}

/**
 * 结果常量 转 文本
 */
/* ────────────────────────── 难度分级与 Rating ────────────────────────── */

/**
 * 难度分类：等级范围 + 对应的 Rating 分值区间
 * Q = q_lo + L / (max - min + 0.9) * (q_hi - q_lo)，L = (level - min) + s/10
 */
const AIR_OJ_DIFFICULTY = [
    'BASIC'     => ['label' => 'BASIC',     'min' => 1,  'max' => 7,  'q_lo' => 0,     'q_hi' => 1500],
    'ADVANCED'  => ['label' => 'ADVANCED',  'min' => 4,  'max' => 12, 'q_lo' => 1500,  'q_hi' => 3000],
    'EXPERT'    => ['label' => 'EXPERT',    'min' => 10, 'max' => 13, 'q_lo' => 3000,  'q_hi' => 9000],
    'MASTER'    => ['label' => 'MASTER',    'min' => 12, 'max' => 14, 'q_lo' => 9000,  'q_hi' => 12000],
    'Re:MASTER' => ['label' => 'Re:MASTER', 'min' => 14, 'max' => 15, 'q_lo' => 12000, 'q_hi' => 20000],
];

/** 上/中/下位对应的分数位（0-9） */
const AIR_OJ_POSITIONS = ['low' => 2, 'mid' => 5, 'high' => 7];

/** 彩虹名七色（红橙黄绿青蓝紫） */
const AIR_OJ_RAINBOW_COLORS = ['#ff4d4d', '#ff9f1a', '#ffd93d', '#4ade80', '#22d3ee', '#3b82f6', '#a855f7'];

/**
 * 生成彩虹渐变；$end 可把渐变压缩到前一部分（用于管理员平分），$tailColor 填充剩余部分
 */
function rainbow_gradient_css(float $end = 1.0, ?string $tailColor = null): string
{
    $n = count(AIR_OJ_RAINBOW_COLORS);
    $stops = [];
    foreach (AIR_OJ_RAINBOW_COLORS as $i => $c) {
        $stops[] = sprintf('%s %.1f%%', $c, $end * ($i / ($n - 1)) * 100);
    }
    if ($tailColor !== null && $end < 1) {
        $stops[] = sprintf('%s %.1f%%', $tailColor, $end * 100);
        $stops[] = $tailColor . ' 100%';
    }
    return 'linear-gradient(90deg,' . implode(',', $stops) . ')';
}

/** 管理员可指定的名字颜色（与 Rating 五色色相区分明显） */
const AIR_OJ_NAME_COLORS = [
    'purple' => ['color' => '#a855f7', 'label' => '紫色'],
    'pink'   => ['color' => '#ec4899', 'label' => '粉色'],
    'gray'   => ['color' => '#9ca3af', 'label' => '灰色'],
];

/** 管理专属的特殊四色渐变 */
const AIR_OJ_SPECIAL_COLORS = ['#a855f7', '#ec4899', '#06b6d4', '#14b8a6'];

function special_gradient_css(): string
{
    return 'linear-gradient(90deg,' . implode(',', AIR_OJ_SPECIAL_COLORS) . ')';
}

/** 校验名字颜色取值 */
function valid_name_color(string $c): bool
{
    return $c === '' || $c === 'auto' || $c === 'role' || $c === 'split'
        || $c === 'special' || isset(AIR_OJ_NAME_COLORS[$c]);
}

/** 名称颜色分级（按 Rating） */
const AIR_OJ_RATING_TIERS = [
    'green'   => ['min' => 0,     'max' => 3000,  'color' => '#22c55e', 'label' => '绿名'],
    'yellow'  => ['min' => 3000,  'max' => 9000,  'color' => '#eab308', 'label' => '黄名'],
    'blue'    => ['min' => 9000,  'max' => 12000, 'color' => '#3b82f6', 'label' => '蓝名'],
    'red'     => ['min' => 12000, 'max' => 15000, 'color' => '#ef4444', 'label' => '红名'],
    'rainbow' => ['min' => 15000, 'max' => 20000, 'color' => '#a855f7', 'label' => '彩虹名'],
];

function difficulty_valid(string $category, int $level): bool
{
    $cfg = AIR_OJ_DIFFICULTY[$category] ?? null;
    return $cfg !== null && $level >= $cfg['min'] && $level <= $cfg['max'];
}

/** 单个难度标签对应的 Rating 分值 */
function difficulty_quality(string $category, int $level, string $position = 'mid'): float
{
    $cfg = AIR_OJ_DIFFICULTY[$category] ?? null;
    if (!$cfg || !difficulty_valid($category, $level)) return 0.0;

    $s = AIR_OJ_POSITIONS[$position] ?? 5;

    // Re:MASTER：14 级（红名）与 15 级（彩虹名）分段
    if ($category === 'Re:MASTER') {
        $frac = $s / 9;
        return round($level === 14
            ? 12000 + $frac * 3000
            : 15000 + $frac * 5000, 1);
    }

    $span = ($cfg['max'] - $cfg['min']) + 0.9;
    $L    = ($level - $cfg['min']) + $s / 10;
    return round($cfg['q_lo'] + ($L / $span) * ($cfg['q_hi'] - $cfg['q_lo']), 1);
}

function difficulty_label(string $category, int $level, string $position = 'mid'): string
{
    $pos = ['low' => '下位', 'mid' => '中位', 'high' => '上位'][$position] ?? '中位';
    $cat = AIR_OJ_DIFFICULTY[$category]['label'] ?? $category;
    return "{$cat} {$level} · {$pos}";
}

/** 题目的全部难度标签 */
function problem_difficulties(string $problem_id): array
{
    return Database::fetchAll(
        "SELECT category, level, position FROM problem_difficulty WHERE problem_id=? ORDER BY level DESC",
        [$problem_id]
    );
}

/** 题目分值：取所有难度标签中的最大值 */
function problem_quality(string $problem_id): float
{
    $max = 0.0;
    foreach (problem_difficulties($problem_id) as $d) {
        $max = max($max, difficulty_quality($d['category'], (int)$d['level'], $d['position']));
    }
    return $max;
}

function quality_tier(float $q): string
{
    foreach (AIR_OJ_RATING_TIERS as $key => $t) {
        if ($q < $t['max']) return $key;
    }
    return 'rainbow';
}

function rating_tier(int $rating): string
{
    return quality_tier((float)$rating);
}

/** 某个颜色分级的下一级最低积分（没有下一级时返回 null） */
function tier_next_min(string $tier): ?int
{
    $keys = array_keys(AIR_OJ_RATING_TIERS);
    $i = array_search($tier, $keys, true);
    if ($i === false || $i + 1 >= count($keys)) return null;
    return AIR_OJ_RATING_TIERS[$keys[$i + 1]]['min'];
}

/** 用户名字颜色（彩虹名用渐变） */
function rating_name_html(string $disp, int $rating): string
{
    $tier = rating_tier($rating);
    if ($tier === 'rainbow') {
        return '<span style="background:' . rainbow_gradient_css() . ';'
             . '-webkit-background-clip:text;background-clip:text;color:transparent;font-weight:600">' . $disp . '</span>';
    }
    return '<span style="color:' . AIR_OJ_RATING_TIERS[$tier]['color'] . '">' . $disp . '</span>';
}

/**
 * 用户角色名称与颜色
 * 作弊者只显示棕色；管理员紫色、版主黄色；带 Rating 分级时可平分名字
 */
function user_role_html(string $user_id, ?string $nick = null): string
{
    static $cache = [];
    if (!isset($cache[$user_id])) {
        $u = Database::fetchOne("SELECT rating, name_color FROM users WHERE user_id=?", [$user_id]);
        $roles = [];
        foreach (Database::fetchAll("SELECT right_str FROM privilege WHERE user_id=?", [$user_id]) as $r) {
            $roles[$r['right_str']] = true;
        }
        $cache[$user_id] = [
            'rating'     => (int)round($u['rating'] ?? 0),
            'name_color' => (string)($u['name_color'] ?? ''),
            'roles'      => $roles,
        ];
    }
    $info = $cache[$user_id];
    $disp = htmlspecialchars($nick ?: $user_id);

    // 作弊者：只显示棕名
    if (isset($info['roles']['cheater'])) {
        return '<span style="color:#8B4513"><i class="fa-solid fa-poo"></i> ' . $disp . '</span>';
    }

    $roleColor = isset($info['roles']['administrator']) ? '#a855f7'
               : (isset($info['roles']['moderator']) ? '#eab308' : '');
    $choice = $info['name_color'];

    // 特殊四色渐变
    if ($choice === 'special') {
        return '<span style="background:' . special_gradient_css() . ';'
             . '-webkit-background-clip:text;background-clip:text;color:transparent;font-weight:600">' . $disp . '</span>';
    }

    // 管理员指定的安全单色
    if (isset(AIR_OJ_NAME_COLORS[$choice])) {
        return '<span style="color:' . AIR_OJ_NAME_COLORS[$choice]['color'] . ';font-weight:600">' . $disp . '</span>';
    }

    // 管理专属：整名管理色 / 平分
    if (($choice === 'role' || $choice === 'split') && $roleColor !== '') {
        if ($choice === 'role') {
            return '<span style="color:' . $roleColor . ';font-weight:600">' . $disp . '</span>';
        }
        $tier = rating_tier($info['rating']);
        $grad = $tier === 'rainbow'
            ? rainbow_gradient_css(0.5, $roleColor)
            : 'linear-gradient(90deg,' . AIR_OJ_RATING_TIERS[$tier]['color'] . ' 50%,' . $roleColor . ' 50%)';
        return '<span style="background:' . $grad . ';'
             . '-webkit-background-clip:text;background-clip:text;color:transparent;font-weight:600">' . $disp . '</span>';
    }

    // 默认：跟随 Rating 分级颜色
    return rating_name_html($disp, $info['rating']);
}

/**
 * 按给定公式计算一次 AC 的 Rating 变化
 */
function calculate_rating_delta(int $rating, float $Q, int $n_ac_total, int $n_attempt, int $n_wa): float
{
    if ($Q <= 0) return 0.0;

    $p      = 1 / ($n_attempt + 2);
    $q_eff  = $Q + max(-300, min(300, 300 * log10((1 - $p) / $p)));
    $E      = 1 / (1 + pow(10, ($q_eff - $rating) / 800));
    $k_user = $n_ac_total < 30 ? 40 : ($n_ac_total <= 100 ? 30 : 20);
    $k_eff  = $k_user * (1 + min(4, max(0, ($q_eff - $rating) / 4000)));
    $eta    = max(0.5, 1 - 0.03 * $n_wa);

    return $k_eff * (1 - $E) * $eta;
}

/**
 * 题目越级信息：['blocked'=>bool,'warn'=>bool,'message'=>string]
 */
function problem_tier_check(string $problem_id, ?array $user): array
{
    $res = ['blocked' => false, 'warn' => false, 'message' => ''];
    if (!$user) return $res;

    $Q = problem_quality($problem_id);
    if ($Q <= 0) return $res;

    $u = Database::fetchOne("SELECT rating FROM users WHERE user_id=?", [$user['user_id']]);
    $pt = quality_tier($Q);
    $ut = rating_tier((int)($u['rating'] ?? 0));
    $levels = array_keys(AIR_OJ_RATING_TIERS);
    if (array_search($pt, $levels, true) <= array_search($ut, $levels, true)) return $res;

    if ($pt === 'rainbow') {
        return ['blocked' => true, 'warn' => true, 'message' => '该题目属于 Re:MASTER 15 级，不允许越级作答。'];
    }

    $next = tier_next_min($ut);
    $target = $next !== null ? ($next - 500) : null;
    return [
        'warn'    => true,
        'blocked' => false,
        'message' => '该题目也许超出您能力范围，若您能力范围支持做此题，在您通过本题后您的 Rating 将会提升到 '
                   . ($target !== null ? $target : '更高等级') . '。',
    ];
}

/**
 * 首次 AC 时按公式更新用户 Rating
 * 返回 ['old','new','delta']，题目没有难度标签时返回 null
 */
function apply_rating_for_ac(string $uid, string $pid, int $sid): ?array
{
    $u = Database::fetchOne("SELECT rating, solved_count FROM users WHERE user_id=?", [$uid]);
    if (!$u) return null;
    $Q = problem_quality($pid);
    if ($Q <= 0) return null;

    // 本次之前的提交次数（首次 AC，所以之前的都是未通过）
    $attempts = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM submissions WHERE user_id=? AND problem_id=? AND id<>?",
        [$uid, $pid, $sid]
    )['c'] ?? 0);

    $rating     = (int)round($u['rating'] ?? 0);
    $n_ac_total = (int)($u['solved_count'] ?? 0);
    $delta      = calculate_rating_delta($rating, $Q, $n_ac_total, $attempts, $attempts);
    $new        = $rating + $delta;

    // 越级：不能直接跨档，最高到下一档最低分 - 500
    $pt = quality_tier($Q);
    $ut = rating_tier($rating);
    $levels = array_keys(AIR_OJ_RATING_TIERS);
    if (array_search($pt, $levels, true) > array_search($ut, $levels, true)) {
        $next = tier_next_min($ut);
        if ($next !== null) $new = min($new, $next - 500);
    }

    $new = (int)round(max(0, min(20000, $new)));
    if ($new !== $rating) {
        Database::exec("UPDATE users SET rating=? WHERE user_id=?", [$new, $uid]);
    }
    return ['old' => $rating, 'new' => $new, 'delta' => $new - $rating];
}

/* ── 题目组：同一道题的不同难度版本 ──
   每个版本都是一条完整的 problem 记录（自己的题面、测试数据、SPJ），
   共享标题/标签/出题人；主记录的 problem_id == group_id，其余成员 group_id = 主记录 ID。 */

function problem_group_main_id(array $row): int
{
    return (int)($row['group_id'] ?? 0) > 0 ? (int)$row['group_id'] : (int)$row['problem_id'];
}

/** 题目组的主记录 */
function problem_group_main(array $row): array
{
    $gid = problem_group_main_id($row);
    if ($gid === (int)$row['problem_id']) return $row;
    $main = Database::fetchOne("SELECT * FROM problem WHERE problem_id=?", [$gid]);
    return $main ?: $row;
}

/** 题目组全部成员（含主记录），按难度分值升序 */
function problem_group_members(int $gid): array
{
    $rows = Database::fetchAll(
        "SELECT * FROM problem WHERE problem_id=? OR group_id=? ORDER BY problem_id ASC",
        [$gid, $gid]
    );
    usort($rows, function ($a, $b) {
        $qa = problem_quality((string)$a['problem_id']);
        $qb = problem_quality((string)$b['problem_id']);
        if ($qa == $qb) return (int)$a['problem_id'] <=> (int)$b['problem_id'];
        return $qa <=> $qb;
    });
    return $rows;
}

function problem_is_group_member(array $row): bool
{
    return (int)($row['group_id'] ?? 0) > 0 && (int)$row['group_id'] !== (int)$row['problem_id'];
}

/** 聚合题目组的通过 / 未通过人数 */
function problem_group_ac_stats(array $members): array
{
    $ids = array_map(fn($m) => (string)$m['problem_id'], $members);
    if (!$ids) return ['ac' => 0, 'failed' => 0];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $ac = (int)(Database::fetchOne(
        "SELECT COUNT(DISTINCT user_id) AS c FROM submissions
         WHERE problem_id IN ($ph) AND status='done' AND score>=100 AND user_id<>'guest'", $ids)['c'] ?? 0);
    $all = (int)(Database::fetchOne(
        "SELECT COUNT(DISTINCT user_id) AS c FROM submissions
         WHERE problem_id IN ($ph) AND user_id<>'guest'", $ids)['c'] ?? 0);
    return ['ac' => $ac, 'failed' => max(0, $all - $ac)];
}

/** 把成员加入题目组（若主记录还没有 group_id 则补上） */
function problem_ensure_group(int $mainId): int
{
    $main = Database::fetchOne("SELECT problem_id, group_id FROM problem WHERE problem_id=?", [$mainId]);
    if (!$main) return 0;
    if ((int)$main['group_id'] === 0) {
        Database::exec("UPDATE problem SET group_id=? WHERE problem_id=?", [$mainId, $mainId]);
    }
    return (int)$main['group_id'] > 0 ? (int)$main['group_id'] : $mainId;
}

/** 同步题目组共享字段（标题 / 标签 / 出题人） */
function problem_sync_group_meta(int $gid, string $title, string $tags, string $author): void
{
    Database::exec("UPDATE problem SET title=?, tags=?, author=? WHERE problem_id=? OR group_id=?",
        [$title, $tags, $author, $gid, $gid]);
}

/** 题目的通过 / 未通过人数 */
function problem_ac_stats(string $problem_id): array
{
    $ac = (int)(Database::fetchOne(
        "SELECT COUNT(DISTINCT user_id) AS c FROM submissions WHERE problem_id=? AND status='done' AND score>=100 AND user_id<>'guest'",
        [$problem_id]
    )['c'] ?? 0);
    $all = (int)(Database::fetchOne(
        "SELECT COUNT(DISTINCT user_id) AS c FROM submissions WHERE problem_id=? AND user_id<>'guest'",
        [$problem_id]
    )['c'] ?? 0);
    return ['ac' => $ac, 'failed' => max(0, $all - $ac)];
}

function verdict_text(int $code): string
{
    return match ($code) {
        0  => 'AC',
        -1 => 'WA',
        1  => 'TLE',
        2  => 'TLE',
        3  => 'MLE',
        4  => 'RE',
        5  => 'SE',
        default => 'UK',
    };
}

/* ────────────────────────── 题目辅助 ────────────────────────── */

/** 标签规范化：逗号/空格/中文逗号分隔 → 去重后逗号分隔 */
function normalize_tags(string $raw): string
{
    $parts = preg_split('/[\s,，;；]+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && !in_array($p, $out, true)) $out[] = $p;
    }
    return implode(',', array_slice($out, 0, 20));
}

/** 题目标签数组 */
function problem_tags(string $problem_id): array
{
    $row = Database::fetchOne("SELECT tags FROM problem WHERE problem_id=?", [$problem_id]);
    $tags = trim((string)($row['tags'] ?? ''));
    return $tags === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $tags))));
}

/** 保存测试数据上传，返回成功数量 */
function save_test_data_uploads(array $files, string $pid): int
{
    $dir = INPUTS_DIR . '/' . $pid;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (empty($files['name'][0])) return 0;

    $n = 0;
    foreach ($files['tmp_name'] as $i => $tmp) {
        if (!$tmp || ($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
        $name = basename((string)$files['name'][$i]);
        if ($name === '' || str_contains($name, '..')) continue;
        if (move_uploaded_file($tmp, $dir . '/' . $name)) $n++;
    }
    return $n;
}

/** 保存特判程序（spj.c / spj.cpp），返回 [bool, 错误信息] */
function save_spj_upload(array $file, string $pid): array
{
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [false, '启用 Special Judge 时必须上传特判程序'];
    }
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return [false, '特判程序上传失败'];
    if (($file['size'] ?? 0) > 512 * 1024) return [false, '特判程序不能超过 512KB'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['c', 'cpp', 'cc', 'cxx'], true)) {
        return [false, '特判程序仅支持 C / C++ 源码（.c/.cpp/.cc/.cxx）'];
    }

    $dir = INPUTS_DIR . '/' . $pid;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    // 清掉旧的 spj 源码
    foreach (glob($dir . '/spj.*') ?: [] as $old) @unlink($old);
    if (!move_uploaded_file($file['tmp_name'], $dir . '/spj.' . $ext)) {
        return [false, '特判程序保存失败'];
    }
    return [true, ''];
}

/** 题目已有的特判程序路径 */
function spj_source_path(string $pid): ?string
{
    $dir = INPUTS_DIR . '/' . $pid;
    foreach (['cpp', 'cc', 'cxx', 'c'] as $ext) {
        $f = $dir . '/spj.' . $ext;
        if (is_file($f)) return $f;
    }
    return null;
}

/** 保存题目的难度标签（先删后插） */
function save_problem_difficulties(string $pid, array $post): void
{
    Database::exec("DELETE FROM problem_difficulty WHERE problem_id=?", [$pid]);
    $cats = $post['diff_cat'] ?? [];
    $levels = $post['diff_level'] ?? [];
    $positions = $post['diff_pos'] ?? [];
    $seen = [];
    foreach ($cats as $i => $cat) {
        $cat = (string)$cat;
        $level = (int)($levels[$i] ?? 0);
        $pos = in_array($positions[$i] ?? '', ['low', 'mid', 'high'], true) ? $positions[$i] : 'mid';
        if (!difficulty_valid($cat, $level)) continue;
        $key = "$cat:$level:$pos";
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        Database::exec("INSERT INTO problem_difficulty (problem_id,category,level,position) VALUES (?,?,?,?)",
            [$pid, $cat, $level, $pos]);
    }
}

/**
 * 题目预览 HTML（后台题目编辑页右栏实时预览）
 * 渲染规则与 problem.php 保持一致：描述/输入/输出/提示走 Markdown，样例与来源为纯文本。
 */
function problem_preview_html(array $f): string
{
    require_once __DIR__ . '/Parsedown.php';
    $pd = new Parsedown();
    $pd->setSafeMode(true);

    $md = function (string $text) use ($pd): string {
        return trim($text) === ''
            ? '<p style="color:var(--text-muted)">（暂无内容）</p>'
            : '<div class="mdui-prose">' . $pd->text($text) . '</div>';
    };

    $title  = trim((string)($f['title'] ?? ''));
    $desc   = (string)($f['description'] ?? '');
    $input  = (string)($f['input'] ?? '');
    $output = (string)($f['output'] ?? '');
    $sin    = (string)($f['sample_input'] ?? '');
    $sout   = (string)($f['sample_output'] ?? '');
    $hint   = (string)($f['hint'] ?? '');
    $author = trim((string)($f['author'] ?? ''));
    $tags   = normalize_tags((string)($f['tags'] ?? ''));

    // 预览里的难度标签（来自表单数组）
    $diffLabels = [];
    $cats = $f['diff_cat'] ?? [];
    $levels = $f['diff_level'] ?? [];
    $poss = $f['diff_pos'] ?? [];
    foreach ($cats as $i => $cat) {
        $lv = (int)($levels[$i] ?? 0);
        if (difficulty_valid((string)$cat, $lv)) {
            $diffLabels[] = difficulty_label((string)$cat, $lv, $poss[$i] ?? 'mid');
        }
    }

    ob_start(); ?>
<h1><?= $title !== '' ? htmlspecialchars($title) : '<span style="color:var(--text-muted)">（未填写标题）</span>' ?></h1>
<div class="pe-meta-line">
    <?php if ($author !== ''): ?><span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($author) ?></span><?php endif; ?>
    <?php foreach ($diffLabels as $dl): ?><span class="pe-diff-tag"><i class="fa-solid fa-gauge-high"></i> <?= htmlspecialchars($dl) ?></span><?php endforeach; ?>
    <?php if ($tags !== ''): ?><span><i class="fa-solid fa-tags"></i> <?= htmlspecialchars(str_replace(',', ' · ', $tags)) ?></span><?php endif; ?>
</div>
<div class="problem-section">
    <h3><i class="fa-solid fa-pen-to-square"></i> 题目描述</h3>
    <?= $md($desc) ?>
</div>
<div class="problem-section">
    <h3><i class="fa-solid fa-right-to-bracket"></i> 输入格式</h3>
    <?= $md($input) ?>
</div>
<div class="problem-section">
    <h3><i class="fa-solid fa-right-from-bracket"></i> 输出格式</h3>
    <?= $md($output) ?>
</div>
<?php if (trim($sin) !== '' || trim($sout) !== ''): ?>
<div class="problem-section">
    <h3><i class="fa-solid fa-clipboard-list"></i> 样例</h3>
    <?php if (trim($sin) !== ''): ?>
    <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">输入</div>
    <div class="sample-box"><?= htmlspecialchars($sin) ?></div>
    <?php endif; ?>
    <?php if (trim($sout) !== ''): ?>
    <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">输出</div>
    <div class="sample-box"><?= htmlspecialchars($sout) ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php if (trim($hint) !== ''): ?>
<div class="problem-section">
    <h3><i class="fa-solid fa-lightbulb"></i> 提示</h3>
    <?= $md($hint) ?>
</div>
<?php endif; ?>
<?php if ($source !== ''): ?>
<div class="pe-source">来源: <?= htmlspecialchars($source) ?></div>
<?php endif; ?>
<?php return (string)ob_get_clean();
}

/* ────────────────────────── 主题 ────────────────────────── */

/** 可选主题：key => 显示名 */
const AIR_OJ_THEMES = [
    'original' => 'Original',
    'mdui3'    => 'MDUI 3',
    'bshark'   => 'bshark',
];

function valid_theme(string $t): bool
{
    return isset(AIR_OJ_THEMES[$t]);
}

/**
 * 当前生效主题：URL 临时预览 > 登录用户的数据库设置 > cookie > original
 */
function current_theme(?array $user): string
{
    if (isset($_GET['theme']) && valid_theme($_GET['theme'])) {
        return $_GET['theme'];
    }
    if ($user) {
        try {
            $row = Database::fetchOne("SELECT theme FROM users WHERE user_id=?", [$user['user_id']]);
            if ($row && valid_theme((string)$row['theme'])) return (string)$row['theme'];
        } catch (Exception $e) {}
    }
    $cookie = (string)($_COOKIE['airoj_theme'] ?? 'original');
    return valid_theme($cookie) ? $cookie : 'original';
}

/**
 * 保存主题：写 cookie；登录用户同时写入数据库（换设备保持一致）
 */
function set_theme(string $theme, ?array $user): bool
{
    if (!valid_theme($theme)) return false;
    setcookie('airoj_theme', $theme, time() + 86400 * 365, '/');
    $_COOKIE['airoj_theme'] = $theme;
    if ($user) {
        Database::exec("UPDATE users SET theme=? WHERE user_id=?", [$theme, $user['user_id']]);
        $_SESSION['airoj_user']['theme'] = $theme;
    }
    return true;
}

/* ────────────────────────── 页面背景 ────────────────────────── */

/**
 * 校验背景值：允许 #颜色 / rgb()|hsl() / 渐变 / 站内路径 / http(s) / data:image
 */
function is_valid_background(string $bg): bool
{
    $bg = trim($bg);
    if ($bg === '') return true;               // 空 = 恢复默认
    if (strlen($bg) > 2000) return false;
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $bg)) return true;
    if (preg_match('/^(rgb|rgba|hsl|hsla)\([0-9.,%\s\/]+\)$/i', $bg)) return true;
    if (preg_match('/^(linear|radial|conic)-gradient\([#0-9a-zA-Z.,%\s\/()\-]+\)$/i', $bg)) return true;
    if (preg_match('~^data:image/(png|jpe?g|gif|webp|svg\+xml);base64,[A-Za-z0-9+/=]+$~i', $bg)) return true;
    // 站内相对路径（如 data/uploads/bg.png），不允许 .. 穿越
    if (preg_match('~^[A-Za-z0-9._/\-]+$~', $bg) && !str_contains($bg, '..')) return true;
    // http(s) 外链：禁止引号/尖括号/括号等可逃逸 CSS 的字符
    if (preg_match('~^https?://~i', $bg) && !preg_match('~[\'"<>()\\\\;{}\s|]~', $bg)) return true;
    return false;
}

/**
 * 生成背景对应的 CSS
 */
function background_css(string $bg, string $theme = 'original'): string
{
    $bg = trim($bg);
    if ($bg === '' || !is_valid_background($bg)) return '';

    // 纯色 / 渐变：直接作为 background
    if ($bg[0] === '#' || preg_match('/^(rgb|rgba|hsl|hsla)\(/i', $bg) || stripos($bg, 'gradient(') !== false) {
        return 'background:' . $bg . ';';
    }

    if (preg_match('~^data:image/~i', $bg)) {
        $url = $bg;
    } else {
        $url = preg_match('~^https?://~i', $bg) ? $bg : '/' . ltrim($bg, '/');
    }
    // 图片上盖一层遮罩保证文字可读：白色主题用浅色遮罩，避免整页发暗
    $scrim = $theme === 'bshark' ? 'rgba(250,251,250,.92)' : 'rgba(13,13,13,.72)';
    return "background-image:linear-gradient({$scrim},{$scrim}),url(\"{$url}\");"
         . 'background-size:cover;background-attachment:fixed;background-position:center;background-repeat:no-repeat;';
}

/**
 * 用户实际生效的背景：自定义优先，否则回落到管理员配置的站点背景
 */
function resolve_background(?array $user): string
{
    if ($user && !empty($user['background']) && is_valid_background((string)$user['background'])) {
        return (string)$user['background'];
    }
    $site = Database::getConfig('site_background');
    return is_valid_background($site) ? $site : '';
}

/**
 * 保存上传的背景图片，返回站内相对路径；失败返回 null
 */
function save_background_upload(array $file, string $prefix): ?array
{
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) > 4 * 1024 * 1024) return [null, '图片不能超过 4MB'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
        return [null, '仅支持 PNG / JPG / GIF / WEBP'];
    }
    $dir = dirname(__DIR__) . '/data/uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $name = $prefix . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return [null, '文件保存失败'];
    }
    return ['data/uploads/' . $name, null];
}

/**
 * 比赛状态：未开始 / 进行中 / 已结束
 */
function contest_status(array $c): string
{
    $now = time();
    if ($now < strtotime($c['start_time'])) return '未开始';
    if ($now > strtotime($c['end_time']))   return '已结束';
    return '进行中';
}

function contest_status_color(string $s): string
{
    return match ($s) {
        '进行中' => 'var(--green)',
        '已结束' => 'var(--text-muted)',
        default  => 'var(--accent)',
    };
}

/**
 * 比赛题目列表（按题号 num 排序）
 */
function contest_problem_list(int $cid): array
{
    return Database::fetchAll(
        "SELECT p.*, cp.num FROM contest_problem cp
         LEFT JOIN problem p ON p.problem_id = cp.problem_id
         WHERE cp.contest_id = ? ORDER BY cp.num ASC, cp.problem_id ASC", [$cid]);
}

/**
 * 比赛排名数据
 * contest_type = 0 为 ICPC 赛制（通过题数 + 罚时，罚时 = 首次 AC 用时 + 20 分钟 × 错误次数）
 * contest_type = 1 为 OI 赛制（每题取最高分求和）
 * 返回 ['rows' => array, 'problems' => array, 'type' => int]
 */
function contest_rank_data(array $contest): array
{
    $cid   = (int)$contest['contest_id'];
    $type  = (int)($contest['contest_type'] ?? 0);
    $start = strtotime($contest['start_time']);
    $end   = strtotime($contest['end_time']);
    $problems = contest_problem_list($cid);

    // 只统计比赛时间内、从比赛入口提交的记录
    $subs = Database::fetchAll(
        "SELECT user_id, problem_id, score, status, created_at FROM submissions
         WHERE contest_id = ? AND user_id <> 'guest' AND created_at >= ? AND created_at <= ?
         ORDER BY id ASC",
        [$cid, $contest['start_time'], $contest['end_time']]
    );

    // $stat[用户][题号] = [solved, wrong, time(秒), score]
    $stat = [];
    foreach ($subs as $s) {
        $u = $s['user_id'];
        $p = (int)$s['problem_id'];
        $t = strtotime($s['created_at']);
        if ($t < $start || $t > $end) continue;
        if (!isset($stat[$u][$p])) $stat[$u][$p] = ['solved' => false, 'wrong' => 0, 'time' => 0, 'score' => 0.0];

        $ac = ($s['status'] === 'done' && (float)$s['score'] >= 100);
        if ($type === 1) {
            $stat[$u][$p]['score'] = max($stat[$u][$p]['score'], (float)$s['score']);
        }
        if ($ac && !$stat[$u][$p]['solved']) {
            $stat[$u][$p]['solved'] = true;
            $stat[$u][$p]['time']   = $t - $start;
        } elseif (!$stat[$u][$p]['solved'] && !$ac) {
            $stat[$u][$p]['wrong']++;
        }
    }

    $nicks = [];
    foreach (Database::fetchAll("SELECT user_id, nick FROM users") as $u) {
        $nicks[$u['user_id']] = $u['nick'];
    }

    $rows = [];
    foreach ($stat as $uid => $probs) {
        $solved = 0; $penalty = 0; $total = 0.0;
        foreach ($problems as $p) {
            $pid = (int)$p['problem_id'];
            $c = $probs[$pid] ?? null;
            if (!$c) continue;
            if ($c['solved']) {
                $solved++;
                $penalty += intdiv($c['time'], 60) + 20 * $c['wrong'];
            }
            $total += $c['score'];
        }
        $rows[] = [
            'user_id' => $uid,
            'nick'    => $nicks[$uid] ?? $uid,
            'solved'  => $solved,
            'penalty' => $penalty,
            'total'   => round($total, 1),
        ];
    }

    usort($rows, function ($a, $b) use ($type) {
        if ($type === 1) {
            return $b['total'] <=> $a['total'] ?: $a['penalty'] <=> $b['penalty'];
        }
        return $b['solved'] <=> $a['solved'] ?: $a['penalty'] <=> $b['penalty'];
    });

    return ['rows' => $rows, 'problems' => $problems, 'type' => $type, 'stat' => $stat];
}

/**
 * 构建 JudgeServer 语言配置
 */
function build_language_config(string $lang): ?array
{
    $cfgs = get_language_configs();
    $cfg  = $cfgs[$lang] ?? null;
    if (!$cfg) return null;

    $lc = [
        'run' => [
            'command'     => $cfg['run_command'],
            'seccomp_rule' => $cfg['seccomp_rule'],
            'exe_name'    => $cfg['exe_name'],
        ],
    ];
    if (!empty($cfg['run_options'])) {
        $lc['run'] = array_merge($lc['run'], $cfg['run_options']);
    }

    if ($cfg['compile'] && $cfg['compile_command']) {
        $lc['compile'] = [
            'src_name'        => $cfg['src_name'],
            'exe_name'        => $cfg['exe_name'],
            'max_cpu_time'    => 30000,
            'max_real_time'   => 60000,
            'max_memory'      => $cfg['compile_memory'] ?? 536870912,
            'compile_command' => $cfg['compile_command'],
        ];
        if (!empty($cfg['compile_env'])) {
            $lc['compile']['env'] = $cfg['compile_env'];
        }
        if (!empty($cfg['compile_memory_check_only'])) {
            $lc['compile']['memory_limit_check_only'] = 1;
        }
    }

    return $lc;
}

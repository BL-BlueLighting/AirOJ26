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
function submitJudge(string $token, string $judgeType, string $source, string $lang, string $pid): array
{
    require_once __DIR__ . '/db.php';

    $langCfg = build_language_config($lang);
    if (!$langCfg) return ['error' => 'Unsupported language'];

    // 加载测试用例
    $cases = load_test_cases($pid);
    if (!$cases) return ['error' => 'No test cases found'];

    // 先插入本地 DB
    $uid = $_SESSION['airoj_user']['user_id'] ?? 'guest';
    $sid = Database::insert("INSERT INTO submissions (problem_id, judge_type, judge_lang, code, status, user_id) VALUES (?,?,?,?,?,?)",
        [$pid, $judgeType, $lang, $source, 'judging', $uid]);

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
        if (!$already) Database::exec("UPDATE users SET solved_count = solved_count + 1, solved = solved + 1 WHERE user_id=?", [$uid]);
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
            'compile_command' => '/usr/bin/javac -d {exe_dir} {src_path}',
            'run_command' => '/usr/bin/java -cp {exe_dir} Main',
            'seccomp_rule' => 'general',
            'src_name' => 'Main.java',
            'exe_name' => 'Main.class',
        ],
        'go' => [
            'name' => 'Go',
            'compile' => true,
            'compile_command' => '/usr/bin/go build -o {exe_path} {src_path}',
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
 * 结果常量 → 文本
 */
/**
 * 用户角色名称与颜色
 * 普通用户(白色) 版主(黄色) 管理员(紫色) 作弊者(💩色)
 */
function user_role_html(string $user_id, ?string $nick = null): string
{
    $disp = $nick ? htmlspecialchars($nick) : htmlspecialchars($user_id);
    if (Database::fetchOne("SELECT COUNT(*) AS c FROM privilege WHERE user_id=? AND right_str='cheater'", [$user_id])['c'] > 0)
        return '<span style="color:#8B4513">💩 ' . $disp . '</span>';
    if (Database::fetchOne("SELECT COUNT(*) AS c FROM privilege WHERE user_id=? AND right_str='administrator'", [$user_id])['c'] > 0)
        return '<span style="color:#a855f7;font-weight:600">' . $disp . '</span>';
    if (Database::fetchOne("SELECT COUNT(*) AS c FROM privilege WHERE user_id=? AND right_str='moderator'", [$user_id])['c'] > 0)
        return '<span style="color:#eab308;font-weight:600">' . $disp . '</span>';
    return $disp;
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

    if ($cfg['compile'] && $cfg['compile_command']) {
        $lc['compile'] = [
            'src_name'        => $cfg['src_name'],
            'exe_name'        => $cfg['exe_name'],
            'max_cpu_time'    => 30000,
            'max_real_time'   => 60000,
            'max_memory'      => 536870912,
            'compile_command' => $cfg['compile_command'],
        ];
    }

    return $lc;
}

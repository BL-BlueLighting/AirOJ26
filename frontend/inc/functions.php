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
function background_css(string $bg): string
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
    // 图片上盖一层深色遮罩，保证文字可读
    return "background-image:linear-gradient(rgba(13,13,13,.72),rgba(13,13,13,.72)),url(\"{$url}\");"
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
 * contest_type = 0 → ICPC 赛制（通过题数 + 罚时，罚时 = 首次 AC 用时 + 20 分钟 × 错误次数）
 * contest_type = 1 → OI 赛制（每题取最高分求和）
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

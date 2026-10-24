<?php
/**
 * AirOJ — 在线测试接口（自定义输入运行代码，不计入提交记录）
 *
 * 请求 JSON: { pid, lang, code, input }
 * 返回 JSON: { ok, verdict, output, cpu_time, real_time, memory, exit_code, signal, error }
 *
 * 说明：JudgeServer 需要成对的 input/output，这里把期望输出传空字符串，
 * 因此 WA 只代表“输出与空期望不一致”，不代表程序出错，统一按“运行完成”处理。
 */

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/functions.php';

header('Content-Type: application/json; charset=utf-8');

function run_json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$req = json_decode((string)$raw, true);
if (!is_array($req)) $req = $_POST;

$lang  = (string)($req['lang'] ?? '');
$code  = (string)($req['code'] ?? '');
$input = (string)($req['input'] ?? '');

if (trim($code) === '')   run_json(['ok' => false, 'error' => '代码不能为空'], 400);
if (strlen($code) > 131072) run_json(['ok' => false, 'error' => '代码过长（上限 128KB）'], 400);
if (strlen($input) > 262144) run_json(['ok' => false, 'error' => '输入数据过大（上限 256KB）'], 400);

$langCfg = build_language_config($lang);
if (!$langCfg) run_json(['ok' => false, 'error' => '不支持的语言'], 400);

$payload = [
    'language_config' => $langCfg,
    'src'             => $code,
    'max_cpu_time'    => 5000,
    'max_memory'      => 268435456,
    'test_case'       => [['input' => $input, 'output' => '']],
    'output'          => true,
];

$resp = judge_server_api('judge', $payload);

// 编译错误 / 系统错误
if (!empty($resp['err'])) {
    run_json([
        'ok'            => false,
        'compile_error' => ($resp['err'] === 'CompileError'),
        'error'         => trim((string)($resp['data'] ?? $resp['err'])),
    ]);
}

$r = $resp['data'][0] ?? null;
if (!is_array($r)) run_json(['ok' => false, 'error' => '评测服务返回异常']);

// 测试运行的判定：-1(WA) 只说明与空期望输出不同，程序本身正常运行
$code_num = (int)($r['result'] ?? 5);
$verdict  = match ($code_num) {
    0, -1   => 'OK',
    1, 2    => 'TLE',
    3       => 'MLE',
    4       => 'RE',
    5       => 'SE',
    default => 'UK',
};

run_json([
    'ok'        => true,
    'verdict'   => $verdict,
    'output'    => (string)($r['output'] ?? ''),
    'cpu_time'  => (int)($r['cpu_time'] ?? 0),
    'real_time' => (int)($r['real_time'] ?? 0),
    'memory'    => (int)($r['memory'] ?? 0),
    'exit_code' => (int)($r['exit_code'] ?? 0),
    'signal'    => (int)($r['signal'] ?? 0),
]);

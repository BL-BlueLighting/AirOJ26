<?php
/**
 * 从 HydroOJ 导出文件导入用户账号
 *
 * 用法：php maintain/import_hydro_users.php /tmp/hydro_users.json
 *
 * Hydro 的密码使用 PBKDF2-SHA256（100000 次，64 字节，取 hex 前 64 位），
 * 这里把 salt / hash 存入 users.hydro_salt / users.hydro_hash，旧密码继续可登录；
 * 登录后会被强制要求修改密码，修改成功后 Hydro 迁移项会被清除。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';

$force = in_array('--force', $argv, true);
if (!$force && Database::getConfig('hydro_users_imported')) {
    echo "Hydro 用户已导入过（" . Database::getConfig('hydro_users_imported') . "），如确需重复导入请加 --force\n";
    exit(0);
}

$file = $argv[1] ?? '/tmp/hydro_users.json';
$raw = @file_get_contents($file);
if ($raw === false) {
    fwrite(STDERR, "无法读取 {$file}\n");
    exit(1);
}
$users = json_decode($raw, true);
if (!is_array($users)) {
    fwrite(STDERR, "JSON 解析失败\n");
    exit(1);
}

$skipNames = ['Guest', 'Hydro'];   // Hydro 的游客 / 系统账号
$created = 0; $merged = 0; $skipped = 0;

foreach ($users as $u) {
    $uname = trim((string)($u['uname'] ?? ''));
    $salt  = (string)($u['salt'] ?? '');
    $hash  = (string)($u['hash'] ?? '');
    $type  = (string)($u['hashType'] ?? 'hydro');

    if ($uname === '' || $salt === '' || $hash === '' || in_array($uname, $skipNames, true)) {
        $skipped++;
        continue;
    }
    if ($type !== 'hydro') {
        echo "SKIP  {$uname}（不支持的 hashType: {$type}）\n";
        $skipped++;
        continue;
    }

    $mail   = trim((string)($u['mail'] ?? ''));
    $school = trim((string)($u['school'] ?? ''));

    // MongoDB 扩展 JSON 日期：{"$date":"2026-04-13T13:43:17.437Z"}
    $regTime = date('Y-m-d H:i:s');
    $regat = $u['regat'] ?? null;
    if (is_array($regat) && !empty($regat['$date'])) {
        $ts = strtotime((string)$regat['$date']);
        if ($ts !== false) $regTime = date('Y-m-d H:i:s', $ts);
    } elseif (is_string($regat) && $regat !== '') {
        $ts = strtotime($regat);
        if ($ts !== false) $regTime = date('Y-m-d H:i:s', $ts);
    }

    $exists = Database::fetchOne("SELECT user_id FROM users WHERE user_id=?", [$uname]);
    if ($exists) {
        Database::exec(
            "UPDATE users SET hydro_salt=?, hydro_hash=?, must_change_password=1 WHERE user_id=?",
            [$salt, $hash, $uname]
        );
        $merged++;
        echo "MERGE {$uname}\n";
    } else {
        Database::insert(
            "INSERT INTO users (user_id,password,email,nick,school,reg_time,hydro_salt,hydro_hash,must_change_password)
             VALUES (?,?,?,?,?,?,?,?,1)",
            [$uname, '', $mail, $uname, $school, $regTime, $salt, $hash]
        );
        $created++;
        echo "NEW   {$uname}\n";
    }
}

Database::setConfig('hydro_users_imported', date('Y-m-d H:i:s'));
echo "\n完成：新建 {$created}，合并 {$merged}，跳过 {$skipped}\n";

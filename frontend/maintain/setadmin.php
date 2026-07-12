<?php
/**
 * AirOJ — 设置管理员
 * 用法: php maintain/setadmin.php <username>
 * 第一个注册的用户自动成为管理员
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';

if (php_sapi_name() !== 'cli') {
    die("请通过命令行运行: php maintain/setadmin.php <username>\n");
}

if ($argc < 2) {
    die("用法: php maintain/setadmin.php <username>\n");
}

$username = trim($argv[1]);
$user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$username]);

if (!$user) {
    die("错误: 用户 '$username' 不存在\n");
}

// Check if already admin
$existing = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$username]);
if ($existing) {
    echo "用户 '$username' 已经是管理员了\n";
    exit(0);
}

Database::insert("INSERT INTO privilege (user_id, right_str) VALUES (?, 'administrator')", [$username]);
echo "✅ 用户 '$username' 已被设置为管理员\n";

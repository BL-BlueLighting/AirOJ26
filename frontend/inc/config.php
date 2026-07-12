<?php
/**
 * AirOJ — Configuration
 *
 * Database: 默认 SQLite，设置 DB_DRIVER=mysql 切换 MySQL
 * MySQL 需要填写 DB_HOST / DB_NAME / DB_USER / DB_PASS
 */

define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite');     // sqlite | mysql

// SQLite
define('DB_SQLITE_PATH', __DIR__ . '/../data/airoj.db');

// MySQL (仅在 DB_DRIVER=mysql 时需要)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'airoj');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// JudgeServer
define('JUDGE_SERVER_URL', getenv('JUDGE_SERVER_URL') ?: 'http://127.0.0.1:12358');
define('JUDGE_SERVER_TOKEN', getenv('JUDGE_SERVER_TOKEN') ?: 'AIR_JUDGE_TOKEN_DEV');

// Secret for token generation (114514 * 1919810)
define('JUDGE_SECRET', 219845122340);

// Test case directory
define('INPUTS_DIR', __DIR__ . '/../../inputs_outputs');

<?php
/**
 * 命令行导入 OIerDb raw.txt。
 * 用法：php maintain/import_oierdb.php /path/to/raw.txt
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';
require_once __DIR__ . '/../include/oierdb.php';

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "用法：php maintain/import_oierdb.php /path/to/raw.txt\n");
    exit(1);
}

try {
    $result = oierdb_import_file($path);
    echo 'OIerDb 导入完成，共 ' . (int)$result['count'] . " 条记录\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'OIerDb 导入失败：' . $e->getMessage() . "\n");
    exit(1);
}

<?php
/**
 * AirOJ — 安装向导
 * 选择 SQLite / MySQL，配置管理员账户
 * 访问 http://你的地址/install.php 开始安装
 */

if (session_status() === PHP_SESSION_NONE) @session_start();

// 如果已安装且数据库有用户，跳转首页
$installed_file = __DIR__ . '/../data/installed.lock';
if (file_exists($installed_file)) {
    // 检查是否需要 MySQL 配置
    $cfg = json_decode(file_get_contents($installed_file), true);
    if ($cfg && ($cfg['driver'] ?? '') === 'mysql' && file_exists(__DIR__ . '/../data/config.local.php')) {
        require_once __DIR__ . '/../data/config.local.php';
    }
    require_once __DIR__ . '/../include/db.php';
    try {
        $cnt = Database::fetchOne("SELECT COUNT(*) AS c FROM users")['c'] ?? 0;
        if ($cnt > 0 && !isset($_GET['force'])) {
            header('Location: index.php');
            exit;
        }
    } catch (Exception $e) {
        // 表不存在，继续安装（删掉 lock 重新来）
        @unlink($installed_file);
    }
}

$step = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver = $_POST['driver'] ?? 'sqlite';

    if ($step == 1) {
        // 第一步：选择数据库
        if ($driver === 'mysql') {
            $host = trim($_POST['host'] ?? '127.0.0.1');
            $port = trim($_POST['port'] ?? '3306');
            $dbname = trim($_POST['dbname'] ?? 'airoj');
            $dbuser = trim($_POST['dbuser'] ?? 'root');
            $dbpass = $_POST['dbpass'] ?? '';

            // 测试 MySQL 连接
            try {
                $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
                $pdo = new PDO($dsn, $dbuser, $dbpass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                // 创建数据库（如果不存在）
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARSET utf8mb4");
                $pdo->exec("USE `$dbname`");

                // 写入本地配置
                $cfg_content = "<?php\n// AirOJ 本地数据库配置（由 install.php 生成）\n"
                    . "define('DB_DRIVER', 'mysql');\n"
                    . "define('DB_HOST', '$host');\n"
                    . "define('DB_PORT', '$port');\n"
                    . "define('DB_NAME', '$dbname');\n"
                    . "define('DB_USER', '$dbuser');\n"
                    . "define('DB_PASS', '" . addslashes($dbpass) . "');\n";

                file_put_contents(__DIR__ . '/../data/config.local.php', $cfg_content);
                require_once __DIR__ . '/../data/config.local.php';

                // 创建表
                $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                Database::initTablesMySQL($pdo);
                Database::$instance = $pdo;

                file_put_contents($installed_file, json_encode([
                    'driver' => 'mysql',
                    'host' => $host,
                    'port' => $port,
                    'dbname' => $dbname,
                ]));
                $step = 2;
            } catch (PDOException $e) {
                $err = 'MySQL 连接失败: ' . $e->getMessage();
            }
        } else {
            // SQLite
            require_once __DIR__ . '/../include/db.php';
            // 强制重新连接，确保表创建
            Database::$instance = null;
            $db = Database::connect();
            file_put_contents($installed_file, json_encode(['driver' => 'sqlite']));
            $step = 2;
        }
    } elseif ($step == 2) {
        // 第二步：创建管理员
        require_once __DIR__ . '/../include/config.php';
        require_once __DIR__ . '/../include/db.php';
        Database::$instance = null;  // 强制重新连接
        $uid = trim($_POST['user_id'] ?? '');
        $pw = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $nick = trim($_POST['nick'] ?? '');

        if ($uid && $pw && $email) {
            try {
                // 检查表是否存在
                $tables = Database::fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
                if (empty($tables)) {
                    Database::$instance = null;
                    Database::connect();
                }
                Database::insert("INSERT INTO users (user_id, password, email, nick, solved_count, rating) VALUES (?, ?, ?, ?, 0, 1500.0)",
                    [$uid, password_hash($pw, PASSWORD_DEFAULT), $email, $nick ?: $uid]);
                Database::insert("INSERT INTO privilege (user_id, right_str) VALUES (?, 'administrator')", [$uid]);
                $_SESSION['airoj_user'] = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
                // 重定向
                echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=index.php?installed=1"></head><body><p>安装完成，正在跳转...</p></body></html>';
                exit;
            } catch (Exception $e) {
                $err = '创建管理员失败: ' . $e->getMessage();
            }
        } else {
            $err = '请填写所有必填字段';
        }
    }
}

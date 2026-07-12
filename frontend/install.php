<?php
/**
 * AirOJ — 安装向导
 * 选择 SQLite / MySQL，配置管理员账户
 * 访问 http://你的地址/install.php 开始安装
 */

if (session_status() === PHP_SESSION_NONE) @session_start();

// 如果已安装且数据库有用户，跳转首页
$installed_file = __DIR__ . '/data/installed.lock';
if (file_exists($installed_file)) {
    // 检查是否需要 MySQL 配置
    $cfg = json_decode(file_get_contents($installed_file), true);
    if ($cfg && ($cfg['driver'] ?? '') === 'mysql' && file_exists(__DIR__ . '/data/config.local.php')) {
        require_once __DIR__ . '/data/config.local.php';
    }
    require_once __DIR__ . '/inc/db.php';
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

                file_put_contents(__DIR__ . '/data/config.local.php', $cfg_content);
                require_once __DIR__ . '/data/config.local.php';

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
            require_once __DIR__ . '/inc/db.php';
            // 强制重新连接，确保表创建
            Database::$instance = null;
            $db = Database::connect();
            file_put_contents($installed_file, json_encode(['driver' => 'sqlite']));
            $step = 2;
        }
    } elseif ($step == 2) {
        // 第二步：创建管理员
        require_once __DIR__ . '/inc/config.php';
        require_once __DIR__ . '/inc/db.php';
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
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装 — AirOJ</title>
<link rel="stylesheet" href="css/style.css">
<style>
.install-box { max-width: 600px; margin: 60px auto; }
.install-box .card { padding: 32px; }
.install-box h1 { text-align: center; margin-bottom: 24px; font-size: 24px; }
.install-box .step { text-align: center; margin-bottom: 24px; color: var(--text-muted); font-size: 13px; }
.install-box .step span { display: inline-block; width: 28px; height: 28px; line-height: 28px; border-radius: 50%; background: var(--bg-hover); margin: 0 4px; font-weight: 700; }
.install-box .step span.active { background: var(--accent); color: #fff; }
.db-option { display: flex; gap: 16px; margin-bottom: 20px; }
.db-option label { flex: 1; padding: 20px; border: 2px solid var(--border); border-radius: var(--radius-lg); text-align: center; cursor: pointer; transition: all var(--transition); }
.db-option label:hover { border-color: var(--accent); }
.db-option input:checked + .db-label { border-color: var(--accent); background: var(--bg-hover); }
.db-option input { display: none; }
.db-option .db-icon { font-size: 32px; margin-bottom: 8px; }
.db-option .db-name { font-weight: 700; font-size: 16px; }
.db-option .db-desc { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
.db-fields { display: none; }
.db-fields.show { display: block; }
</style>
</head>
<body>

<div class="container">
<div class="install-box">
    <div class="card">
        <h1>🚀 AirOJ 安装向导</h1>
        <div class="step">
            <span class="<?= $step>=1?'active':'' ?>">1</span> 配置数据库
            <span style="margin:0 8px">→</span>
            <span class="<?= $step>=2?'active':'' ?>">2</span> 创建管理员
        </div>

        <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <?php if ($step == 1): ?>
        <form method="post" id="step1">
            <input type="hidden" name="step" value="1">
            <div class="form-group"><label>选择数据库</label></div>
            <div class="db-option">
                <label><input type="radio" name="driver" value="sqlite" checked onchange="toggleDbFields()">
                <div class="db-label"><div class="db-icon">🗄️</div><div class="db-name">SQLite</div><div class="db-desc">无需配置，适合个人/小规模使用</div></div></label>
                <label><input type="radio" name="driver" value="mysql" onchange="toggleDbFields()">
                <div class="db-label"><div class="db-icon">🐬</div><div class="db-name">MySQL</div><div class="db-desc">高性能，适合生产环境</div></div></label>
            </div>

            <div id="mysql-fields" class="db-fields">
                <div class="form-row">
                    <div class="form-group" style="flex:2"><label>主机地址</label><input type="text" name="host" class="form-input" value="127.0.0.1"></div>
                    <div class="form-group" style="flex:1"><label>端口</label><input type="text" name="port" class="form-input" value="3306"></div>
                </div>
                <div class="form-group"><label>数据库名</label><input type="text" name="dbname" class="form-input" value="airoj"></div>
                <div class="form-row">
                    <div class="form-group"><label>用户名</label><input type="text" name="dbuser" class="form-input" value="root"></div>
                    <div class="form-group"><label>密码</label><input type="password" name="dbpass" class="form-input"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">下一步 →</button>
        </form>

        <script>
        function toggleDbFields() {
            document.getElementById('mysql-fields').classList.toggle('show', document.querySelector('[name=driver]:checked').value === 'mysql');
        }
        </script>

        <?php elseif ($step == 2): ?>
        <form method="post">
            <input type="hidden" name="step" value="2">
            <p style="text-align:center;color:var(--text-secondary);margin-bottom:20px;font-size:14px">✅ 数据库连接成功！请创建管理员账户。</p>
            <div class="form-group"><label>管理员用户名 *</label><input type="text" name="user_id" class="form-input" required autocomplete="username"></div>
            <div class="form-group"><label>密码 *</label><input type="password" name="password" class="form-input" required></div>
            <div class="form-group"><label>邮箱 *</label><input type="email" name="email" class="form-input" required></div>
            <div class="form-group"><label>昵称</label><input type="text" name="nick" class="form-input" placeholder="可选"></div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">🚀 完成安装</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</div>

</body>
</html>

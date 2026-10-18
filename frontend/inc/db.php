<?php
/**
 * AirOJ — Database Class
 *
 * 支持 SQLite（默认）和 MySQL 两种驱动。
 * 自动建表，提供链式查询接口。
 *
 * 用法:
 *   $db = Database::connect();
 *   $rows = $db->fetchAll("SELECT * FROM submissions WHERE problem_id = ?", [$pid]);
 *   $row  = $db->fetchOne("SELECT * FROM submissions WHERE id = ?", [$id]);
 *   $id   = $db->insert("INSERT INTO submissions (...) VALUES (...)", [...]);
 *   $db->exec("UPDATE submissions SET status = ? WHERE id = ?", [$status, $id]);
 */

// 即使调用方没有先加载 config.php，也保证时间与 SQLite localtime 为 UTC+8
date_default_timezone_set('Asia/Shanghai');
putenv('TZ=Asia/Shanghai');

class Database
{
    public static ?PDO $instance = null;

    // ----- 连接 -----------------------------------------------------------

    /**
     * 获取数据库连接（单例）
     */
    public static function connect(): PDO
    {
        if (static::$instance !== null) {
            return static::$instance;
        }

        // 加载本地配置（由 install.php 生成）
        $local_cfg = dirname(__DIR__) . '/data/config.local.php';
        if (file_exists($local_cfg)) {
            require_once $local_cfg;
        }

        // Defaults if config.php not loaded before db.php
        if (!defined('DB_DRIVER')) {
            define('DB_DRIVER', 'sqlite');
            define('DB_SQLITE_PATH', dirname(__DIR__) . '/data/airoj.db');
            define('DB_HOST', '127.0.0.1');
            define('DB_PORT', '3306');
            define('DB_NAME', 'airoj');
            define('DB_USER', 'root');
            define('DB_PASS', '');
        }
        if (!defined('INPUTS_DIR')) {
            define('INPUTS_DIR', dirname(__DIR__, 2) . '/inputs_outputs');
        }
        $driver = DB_DRIVER;

        if ($driver === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // 让 MySQL 的 CURRENT_TIMESTAMP / NOW() 与站点时区一致
            $pdo->exec("SET time_zone = '+08:00'");
        } else {
            // SQLite
            $dir = dirname(DB_SQLITE_PATH);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
        }

        static::$instance = $pdo;
        static::initTables($pdo);
        static::ensureColumns($pdo);
        static::seedInitialData($pdo);
        return $pdo;
    }

    /**
     * 轻量自动迁移：为已存在的老库补齐后来新增的字段
     * （CREATE TABLE IF NOT EXISTS 不会修改已存在的表，所以需要单独 ADD COLUMN）
     * 每个字段写作 [SQLite 定义, MySQL 定义]
     */
    protected static function ensureColumns(PDO $pdo): void
    {
        $wanted = [
            'users' => [
                'bio'            => ['TEXT', 'LONGTEXT NULL'],
                'twofa_secret'   => ['TEXT', 'VARCHAR(64) NULL DEFAULT NULL'],
                'twofa_enabled'  => ['INTEGER NOT NULL DEFAULT 0', 'TINYINT NOT NULL DEFAULT 0'],
                'background'     => ['TEXT', 'LONGTEXT NULL'],
                'fortune_drawn'  => ['TEXT', 'VARCHAR(10) NULL DEFAULT NULL'],
                'theme'          => ["TEXT NOT NULL DEFAULT 'original'", "VARCHAR(20) NOT NULL DEFAULT 'original'"],
                'name_split'     => ['INTEGER NOT NULL DEFAULT 1', 'TINYINT NOT NULL DEFAULT 1'],
                'name_color'     => ["TEXT NOT NULL DEFAULT ''", "VARCHAR(20) NOT NULL DEFAULT ''"],
            ],
            'problem' => [
                'tags'     => ['TEXT', 'LONGTEXT'],
                'author'   => ["TEXT NOT NULL DEFAULT ''", "VARCHAR(100) NOT NULL DEFAULT ''"],
                'group_id' => ['INTEGER NOT NULL DEFAULT 0', 'INT NOT NULL DEFAULT 0'],
            ],
            'teams' => [
                'avatar'        => ['TEXT', 'VARCHAR(255) NULL'],
                'verify_reason' => ['TEXT', 'TEXT'],
                'verify_status' => ["TEXT NOT NULL DEFAULT 'none'", "VARCHAR(16) NOT NULL DEFAULT 'none'"],
            ],
            'submissions' => [
                'contest_id' => ['INTEGER NOT NULL DEFAULT 0', 'INT NOT NULL DEFAULT 0'],
            ],
        ];
        $mysql = (DB_DRIVER === 'mysql');
        try {
            foreach ($wanted as $table => $cols) {
                $existing = [];
                if ($mysql) {
                    foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $r) $existing[] = $r['Field'];
                } else {
                    foreach ($pdo->query("PRAGMA table_info(`$table`)") as $r) $existing[] = $r['name'];
                }
                foreach ($cols as $col => $defs) {
                    if (in_array($col, $existing, true)) continue;
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` " . $defs[$mysql ? 1 : 0]);
                }
            }
        } catch (PDOException $e) {
            // 迁移失败不应影响正常访问
        }
    }

    /**
     * 初始数据：题库为空时建立 1000 A+B Problem
     * （problem_id 显式写 1000，AUTOINCREMENT 会从 1001 继续）
     */
    protected static function seedInitialData(PDO $pdo): void
    {
        try {
            if ((int)$pdo->query("SELECT COUNT(*) FROM problem")->fetchColumn() > 0) return;

            $stmt = $pdo->prepare("INSERT INTO problem
                (problem_id, title, description, input, output, sample_input, sample_output, hint, source, tags, author, spj, defunct)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                1000,
                'A+B Problem',
                "输入两个整数 \$a\$ 和 \$b\$，输出它们的和。",
                "一行两个整数 \$a, b\$。",
                "一个整数，表示 \$a+b\$。",
                "1 2",
                "3",
                "数据范围：\$-10^9 \\le a,b \\le 10^9\$。",
                'AirOJ',
                '入门,数学',
                'AirOJ',
                '0',
                'N',
            ]);
            // SQLite 的 AUTOINCREMENT 会记住历史最大值，这里显式回到 1000，保证下一题是 1001
            if (DB_DRIVER !== 'mysql') {
                $pdo->exec("UPDATE sqlite_sequence SET seq=1000 WHERE name='problem'");
            }

            $pdo->exec("INSERT INTO problem_difficulty (problem_id, category, level, position) VALUES (1000, 'BASIC', 1, 'low')");

            // 初始测试数据
            $dir = INPUTS_DIR . '/1000';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (!is_file($dir . '/1.in'))  @file_put_contents($dir . '/1.in', "1 2\n");
            if (!is_file($dir . '/1.out')) @file_put_contents($dir . '/1.out', "3\n");
        } catch (Throwable $e) {
            // 种子失败不影响正常使用
        }
    }

    // ----- 建表 -----------------------------------------------------------

    protected static function initTables(PDO $pdo): void
    {
        $driver = DB_DRIVER;

        if ($driver === 'mysql') {
            static::initTablesMySQL($pdo);
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS submissions (
                    id              INTEGER PRIMARY KEY AUTOINCREMENT,
                    problem_id      TEXT    NOT NULL DEFAULT '',
                    judge_type      TEXT    NOT NULL DEFAULT 'standard',
                    judge_lang      TEXT    NOT NULL DEFAULT '',
                    code            TEXT    NOT NULL DEFAULT '',
                    status          TEXT    NOT NULL DEFAULT 'pending',
                    score           REAL    NOT NULL DEFAULT 0.0,
                    total_cases     INTEGER NOT NULL DEFAULT 0,
                    passed_cases    INTEGER NOT NULL DEFAULT 0,
                    result_json     TEXT,
                    user_id         TEXT    NOT NULL DEFAULT '',
                    contest_id      INTEGER NOT NULL DEFAULT 0,
                    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                    updated_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    user_id         TEXT NOT NULL PRIMARY KEY,
                    password        TEXT NOT NULL DEFAULT '',
                    email           TEXT NOT NULL DEFAULT '',
                    nick            TEXT NOT NULL DEFAULT '',
                    school          TEXT NOT NULL DEFAULT '',
                    solved          INT  NOT NULL DEFAULT 0,
                    submit          INT  NOT NULL DEFAULT 0,
                    solved_count    INT  NOT NULL DEFAULT 0,
                    submit_count    INT  NOT NULL DEFAULT 0,
                    rating          REAL NOT NULL DEFAULT 0.0,
                    bio             TEXT,
                    twofa_secret    TEXT,
                    twofa_enabled   INT  NOT NULL DEFAULT 0,
                    background      TEXT,
                    fortune_drawn   TEXT,
                    theme           TEXT NOT NULL DEFAULT 'original',
                    name_split      INT  NOT NULL DEFAULT 1,
                    name_color      TEXT NOT NULL DEFAULT '',
                    reg_time        TEXT NOT NULL DEFAULT (datetime('now','localtime'))
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS problem (
                    problem_id      INTEGER PRIMARY KEY AUTOINCREMENT,
                    title           TEXT NOT NULL DEFAULT '',
                    description     TEXT, input TEXT, output TEXT,
                    sample_input    TEXT, sample_output TEXT,
                    spj             TEXT NOT NULL DEFAULT '0',
                    hint            TEXT, source TEXT,
                    tags            TEXT,
                    author          TEXT NOT NULL DEFAULT '',
                    group_id        INTEGER NOT NULL DEFAULT 0,
                    in_date         TEXT NOT NULL DEFAULT (datetime('now','localtime')),
                    defunct         TEXT NOT NULL DEFAULT 'N'
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS privilege (
                    user_id     TEXT NOT NULL,
                    right_str   TEXT NOT NULL,
                    PRIMARY KEY (user_id, right_str)
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS news (
                    news_id  INTEGER PRIMARY KEY AUTOINCREMENT,
                    title    TEXT NOT NULL DEFAULT '',
                    content  TEXT,
                    time     TEXT NOT NULL DEFAULT (datetime('now','localtime')),
                    pinned   INTEGER NOT NULL DEFAULT 0
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS contest (
                    contest_id  INTEGER PRIMARY KEY AUTOINCREMENT,
                    title       TEXT NOT NULL DEFAULT '',
                    start_time  TEXT NOT NULL DEFAULT '2000-01-01 00:00:00',
                    end_time    TEXT NOT NULL DEFAULT '2000-01-01 00:00:00',
                    defunct     TEXT NOT NULL DEFAULT 'N',
                    private     TEXT NOT NULL DEFAULT '0',
                    contest_type INTEGER NOT NULL DEFAULT 0,
                    description TEXT
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS contest_problem (
                    problem_id  INT NOT NULL DEFAULT 0,
                    contest_id  INT NOT NULL DEFAULT 0,
                    num         INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (problem_id, contest_id)
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS system_config (
                    key_name TEXT PRIMARY KEY,
                    value    TEXT NOT NULL DEFAULT ''
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS solutions (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    problem_id  INT NOT NULL DEFAULT 0,
                    user_id     TEXT NOT NULL DEFAULT '',
                    title       TEXT NOT NULL DEFAULT '',
                    content     TEXT,
                    created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime'))
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS teams (
                    team_id     INTEGER PRIMARY KEY AUTOINCREMENT,
                    name        TEXT NOT NULL DEFAULT '',
                    description TEXT,
                    owner       TEXT NOT NULL DEFAULT '',
                    verified    INTEGER NOT NULL DEFAULT 0,
                    avatar      TEXT,
                    verify_reason TEXT,
                    verify_status TEXT NOT NULL DEFAULT 'none',
                    created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime'))
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS team_members (
                    team_id  INT NOT NULL DEFAULT 0,
                    user_id  TEXT NOT NULL DEFAULT '',
                    role     TEXT NOT NULL DEFAULT 'member',
                    status   TEXT NOT NULL DEFAULT 'approved',
                    PRIMARY KEY (team_id, user_id)
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS team_applications (
                    team_id  INT NOT NULL DEFAULT 0,
                    user_id  TEXT NOT NULL DEFAULT '',
                    reason   TEXT,
                    status   TEXT NOT NULL DEFAULT 'pending',
                    PRIMARY KEY (team_id, user_id)
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS privilege_log (
                    log_id     INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id    TEXT NOT NULL DEFAULT '',
                    admin_id   TEXT NOT NULL DEFAULT '',
                    action     TEXT NOT NULL DEFAULT '',
                    target     TEXT NOT NULL DEFAULT '',
                    reason     TEXT,
                    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS problem_difficulty (
                    problem_id INTEGER NOT NULL DEFAULT 0,
                    category   TEXT NOT NULL DEFAULT 'BASIC',
                    level      INTEGER NOT NULL DEFAULT 1,
                    position   TEXT NOT NULL DEFAULT 'mid',
                    PRIMARY KEY (problem_id, category, level, position)
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS chapters (
                    chapter_id  INTEGER PRIMARY KEY AUTOINCREMENT,
                    title       TEXT NOT NULL DEFAULT '',
                    description TEXT,
                    background  TEXT,
                    creator     TEXT NOT NULL DEFAULT '',
                    created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime'))
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS chapter_problem (
                    chapter_id INTEGER NOT NULL DEFAULT 0,
                    problem_id INTEGER NOT NULL DEFAULT 0,
                    position   INTEGER NOT NULL DEFAULT 0,
                    PRIMARY KEY (chapter_id, problem_id)
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS messages (
                    id           INTEGER PRIMARY KEY AUTOINCREMENT,
                    from_user    TEXT NOT NULL DEFAULT '',
                    to_user      TEXT NOT NULL DEFAULT '',
                    subject      TEXT NOT NULL DEFAULT '',
                    content      TEXT NOT NULL DEFAULT '',
                    is_read      INTEGER NOT NULL DEFAULT 0,
                    from_deleted INTEGER NOT NULL DEFAULT 0,
                    to_deleted   INTEGER NOT NULL DEFAULT 0,
                    created_at   TEXT NOT NULL DEFAULT (datetime('now','localtime'))
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS bbs_topic (
                    topic_id    INTEGER PRIMARY KEY AUTOINCREMENT,
                    problem_id  INTEGER NOT NULL DEFAULT 0,
                    user_id     TEXT NOT NULL DEFAULT '',
                    title       TEXT NOT NULL DEFAULT '',
                    content     TEXT,
                    reply_count INTEGER NOT NULL DEFAULT 0,
                    last_reply  TEXT,
                    created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime'))
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS bbs_reply (
                    reply_id   INTEGER PRIMARY KEY AUTOINCREMENT,
                    topic_id   INTEGER NOT NULL DEFAULT 0,
                    user_id    TEXT NOT NULL DEFAULT '',
                    content    TEXT,
                    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
                )
            ");
        }
    }

    /**
     * MySQL 专用建表
     */
    public static function initTablesMySQL(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS submissions (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                problem_id      VARCHAR(64)     NOT NULL DEFAULT '',
                judge_type      VARCHAR(32)     NOT NULL DEFAULT 'standard',
                judge_lang      VARCHAR(32)     NOT NULL DEFAULT '',
                code            LONGTEXT        NOT NULL,
                status          VARCHAR(16)     NOT NULL DEFAULT 'pending',
                score           DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
                total_cases     INT UNSIGNED    NOT NULL DEFAULT 0,
                passed_cases    INT UNSIGNED    NOT NULL DEFAULT 0,
                result_json     LONGTEXT,
                user_id         VARCHAR(48)     NOT NULL DEFAULT '',
                contest_id      INT             NOT NULL DEFAULT 0,
                created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_problem (problem_id),
                INDEX idx_status (status),
                INDEX idx_user (user_id),
                INDEX idx_contest (contest_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                user_id         VARCHAR(48) NOT NULL PRIMARY KEY,
                password        VARCHAR(255) NOT NULL DEFAULT '',
                email           VARCHAR(200) NOT NULL DEFAULT '',
                nick            VARCHAR(200) NOT NULL DEFAULT '',
                school          VARCHAR(200) NOT NULL DEFAULT '',
                solved          INT UNSIGNED NOT NULL DEFAULT 0,
                submit          INT UNSIGNED NOT NULL DEFAULT 0,
                solved_count    INT UNSIGNED NOT NULL DEFAULT 0,
                submit_count    INT UNSIGNED NOT NULL DEFAULT 0,
                rating          DECIMAL(10,1) NOT NULL DEFAULT 0.0,
                bio             LONGTEXT,
                twofa_secret    VARCHAR(64) NULL DEFAULT NULL,
                twofa_enabled   TINYINT NOT NULL DEFAULT 0,
                background      LONGTEXT NULL,
                fortune_drawn   VARCHAR(10) NULL DEFAULT NULL,
                theme           VARCHAR(20) NOT NULL DEFAULT 'original',
                name_split      TINYINT NOT NULL DEFAULT 1,
                name_color      VARCHAR(20) NOT NULL DEFAULT '',
                reg_time        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS problem (
                problem_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title           VARCHAR(200) NOT NULL DEFAULT '',
                description     LONGTEXT, input LONGTEXT, output LONGTEXT,
                sample_input    LONGTEXT, sample_output LONGTEXT,
                spj             CHAR(1) NOT NULL DEFAULT '0',
                hint            LONGTEXT, source VARCHAR(200) DEFAULT '',
                tags            LONGTEXT,
                author          VARCHAR(100) NOT NULL DEFAULT '',
                group_id        INT NOT NULL DEFAULT 0,
                in_date         DATETIME DEFAULT CURRENT_TIMESTAMP,
                defunct         CHAR(1) NOT NULL DEFAULT 'N'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS privilege (
                user_id     VARCHAR(48) NOT NULL,
                right_str   VARCHAR(32) NOT NULL,
                PRIMARY KEY (user_id, right_str)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS news (
                news_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title    VARCHAR(200) NOT NULL DEFAULT '',
                content  LONGTEXT,
                time     DATETIME DEFAULT CURRENT_TIMESTAMP,
                pinned   TINYINT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contest (
                contest_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title        VARCHAR(200) NOT NULL DEFAULT '',
                start_time   DATETIME NOT NULL DEFAULT '2000-01-01 00:00:00',
                end_time     DATETIME NOT NULL DEFAULT '2000-01-01 00:00:00',
                defunct      CHAR(1) NOT NULL DEFAULT 'N',
                private      CHAR(1) NOT NULL DEFAULT '0',
                contest_type INT UNSIGNED NOT NULL DEFAULT 0,
                description  LONGTEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contest_problem (
                problem_id  INT UNSIGNED NOT NULL DEFAULT 0,
                contest_id  INT UNSIGNED NOT NULL DEFAULT 0,
                num         INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (problem_id, contest_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_config (
                key_name VARCHAR(100) PRIMARY KEY,
                value    TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS solutions (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                problem_id INT NOT NULL DEFAULT 0,
                user_id    VARCHAR(100) NOT NULL DEFAULT '',
                title      VARCHAR(200) NOT NULL DEFAULT '',
                content    LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS teams (
                team_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(200) NOT NULL DEFAULT '',
                description TEXT,
                owner       VARCHAR(100) NOT NULL DEFAULT '',
                verified    TINYINT NOT NULL DEFAULT 0,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS team_members (
                team_id INT NOT NULL DEFAULT 0,
                user_id VARCHAR(100) NOT NULL DEFAULT '',
                role    VARCHAR(20) NOT NULL DEFAULT 'member',
                status  VARCHAR(20) NOT NULL DEFAULT 'approved',
                PRIMARY KEY (team_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS team_applications (
                team_id INT NOT NULL DEFAULT 0,
                user_id VARCHAR(100) NOT NULL DEFAULT '',
                reason  TEXT,
                status  VARCHAR(20) NOT NULL DEFAULT 'pending',
                PRIMARY KEY (team_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS privilege_log (
                log_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id    VARCHAR(100) NOT NULL DEFAULT '',
                admin_id   VARCHAR(100) NOT NULL DEFAULT '',
                action     VARCHAR(20) NOT NULL DEFAULT '',
                target     VARCHAR(100) NOT NULL DEFAULT '',
                reason     TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS problem_difficulty (
                problem_id INT NOT NULL DEFAULT 0,
                category   VARCHAR(16) NOT NULL DEFAULT 'BASIC',
                level      INT NOT NULL DEFAULT 1,
                position   VARCHAR(8) NOT NULL DEFAULT 'mid',
                PRIMARY KEY (problem_id, category, level, position)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chapters (
                chapter_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title       VARCHAR(200) NOT NULL DEFAULT '',
                description LONGTEXT,
                background  LONGTEXT,
                creator     VARCHAR(100) NOT NULL DEFAULT '',
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chapter_problem (
                chapter_id INT UNSIGNED NOT NULL DEFAULT 0,
                problem_id INT UNSIGNED NOT NULL DEFAULT 0,
                position   INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (chapter_id, problem_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS messages (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                from_user    VARCHAR(100) NOT NULL DEFAULT '',
                to_user      VARCHAR(100) NOT NULL DEFAULT '',
                subject      VARCHAR(200) NOT NULL DEFAULT '',
                content      LONGTEXT,
                is_read      TINYINT NOT NULL DEFAULT 0,
                from_deleted TINYINT NOT NULL DEFAULT 0,
                to_deleted   TINYINT NOT NULL DEFAULT 0,
                created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_to (to_user),
                INDEX idx_from (from_user)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS bbs_topic (
                topic_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                problem_id  INT NOT NULL DEFAULT 0,
                user_id     VARCHAR(100) NOT NULL DEFAULT '',
                title       VARCHAR(200) NOT NULL DEFAULT '',
                content     LONGTEXT,
                reply_count INT NOT NULL DEFAULT 0,
                last_reply  DATETIME NULL DEFAULT NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_problem (problem_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS bbs_reply (
                reply_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                topic_id   INT NOT NULL DEFAULT 0,
                user_id    VARCHAR(100) NOT NULL DEFAULT '',
                content    LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_topic (topic_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    // ----- 查询 -----------------------------------------------------------

    /**
     * 执行 SQL 并返回所有行
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = static::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 执行 SQL 并返回第一行
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = static::connect()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * 执行 SQL 并返回受影响行数
     */
    public static function exec(string $sql, array $params = []): int
    {
        $stmt = static::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 执行 INSERT 并返回最后插入的 ID
     */
    public static function insert(string $sql, array $params = []): int
    {
        $pdo  = static::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $pdo->lastInsertId();
    }

    /**
     * 写入 system_config（SQLite/MySQL 通用的 upsert，避免 INSERT OR REPLACE 的方言差异）
     */
    public static function setConfig(string $key, string $value): void
    {
        $n = static::exec("UPDATE system_config SET value=? WHERE key_name=?", [$value, $key]);
        if ($n === 0) {
            static::exec("INSERT INTO system_config (key_name, value) VALUES (?,?)", [$key, $value]);
        }
    }

    /**
     * 读取 system_config
     */
    public static function getConfig(string $key, string $default = ''): string
    {
        $row = static::fetchOne("SELECT value FROM system_config WHERE key_name=?", [$key]);
        return $row ? (string) $row['value'] : $default;
    }
}

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
        return $pdo;
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
                    rating          REAL NOT NULL DEFAULT 1500.0,
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
                    time     TEXT NOT NULL DEFAULT (datetime('now','localtime'))
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
                created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_problem (problem_id),
                INDEX idx_status (status),
                INDEX idx_user (user_id)
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
                rating          DECIMAL(10,1) NOT NULL DEFAULT 1500.0,
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
                time     DATETIME DEFAULT CURRENT_TIMESTAMP
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
}

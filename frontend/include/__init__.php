<?php
/**
 * AirOJ MVC 通用初始化
 *
 * 所有页面 controller 都先引用本文件，再引用 logics/<page>.php，
 * 最后通过 include/render.php 渲染 templates/<theme>/<page>.php。
 */
if (defined('AIR_OJ_INIT_DONE')) return;
define('AIR_OJ_INIT_DONE', true);

if (session_status() === PHP_SESSION_NONE) @session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$page = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
$nav_page = $page; // 页面逻辑可能用 $page 做分页，导航单独用 $nav_page
$current_script = $_SERVER['SCRIPT_NAME'] ?? '';

// 未安装时跳转安装向导
if ($page !== 'install' && !file_exists(__DIR__ . '/../data/installed.lock')) {
    header('Location: /install.php');
    exit;
}

$user = $_SESSION['airoj_user'] ?? null;
$site_name = 'AirOJ';

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/render.php';

// 每次请求从数据库刷新登录用户资料，避免 session 中保留旧的头像 / 背景 / 签名等
if ($user) {
    try {
        $freshUser = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$user['user_id']]);
        if ($freshUser) {
            unset($freshUser['password'], $freshUser['hydro_salt'], $freshUser['hydro_hash']);
            $user = array_merge($user, $freshUser);
            $_SESSION['airoj_user'] = $user;
        }
    } catch (Exception $e) {}
}

// 从 HydroOJ 迁移的账号必须先修改密码
if ($user && !array_key_exists('must_change_password', $user)) {
    try {
        $mcp = Database::fetchOne("SELECT must_change_password FROM users WHERE user_id=?", [$user['user_id']]);
        $user['must_change_password'] = (int)($mcp['must_change_password'] ?? 0);
        $_SESSION['airoj_user']['must_change_password'] = $user['must_change_password'];
    } catch (Exception $e) {}
}
if ($user && !empty($user['must_change_password'])
    && !in_array($current_script, ['/settings.php', '/logout.php', '/login.php', '/install.php'], true)) {
    header('Location: /settings.php?tab=password&force=1');
    exit;
}

// 切换主题：?theme=xxx → 写入 cookie / 数据库，然后重定向去掉该参数
if (isset($_GET['theme']) && valid_theme($_GET['theme'])) {
    set_theme($_GET['theme'], $user);
    $q = $_GET; unset($q['theme']);
    $qs = http_build_query($q);
    $url = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/index.php';
    header('Location: ' . $url . ($qs ? '?' . $qs : ''));
    exit;
}

$theme = current_theme($user);

$is_admin = false;
$site_logo = '';
$site_favicon = '';
try {
    if ($user) {
        $priv = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$user['user_id']]);
        $is_admin = (bool)$priv;
        $unread_messages = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS c FROM messages WHERE to_user=? AND is_read=0 AND to_deleted=0",
            [$user['user_id']])['c'] ?? 0);
        // 夜猫子等时间类成就：在线即判定
        check_time_achievements($user['user_id']);
    }
    $site_logo = Database::getConfig('site_logo');
    $site_favicon = Database::getConfig('site_favicon');
} catch (Exception $e) {}

// 自定义页脚（版权行由 templates/*/footer.php 强制输出）
$custom_footer = '';
$footer_pd = null;
try {
    $custom_footer = trim(Database::getConfig('site_footer'));
    if ($custom_footer !== '') {
        require_once __DIR__ . '/Parsedown.php';
        $footer_pd = new Parsedown();
        $footer_pd->setSafeMode(true);
    }
} catch (Exception $e) {}

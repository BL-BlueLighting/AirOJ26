<?php
/**
 * AirOJ 插件系统引导文件。
 * 只定义类与全局辅助函数，不加载插件；插件由 plugin_manager()->loadEnabled() 加载。
 */

require_once __DIR__ . '/ExtInfo.php';
require_once __DIR__ . '/Plugin.php';
require_once __DIR__ . '/PermissionRegistry.php';
require_once __DIR__ . '/PluginApi.php';
require_once __DIR__ . '/PluginManager.php';

/** 全局插件管理器（单例） */
function plugin_manager(): \AirOJ\Plugin\PluginManager
{
    static $manager = null;
    if ($manager === null) {
        $manager = new \AirOJ\Plugin\PluginManager();
    }
    return $manager;
}

/** 分发 Hook，$ctx 可被插件修改；返回回调结果数组 */
function plugin_do(string $hook, array &$ctx = []): array
{
    try {
        return plugin_manager()->doHook($hook, $ctx);
    } catch (\Throwable $e) {
        error_log('[AirOJ plugin] dispatch failed for ' . $hook . ': ' . $e->getMessage());
        return [];
    }
}

/** 分发 View 类 Hook，返回 HTML 字符串 */
function plugin_render(string $hook, array $ctx = []): string
{
    try {
        return plugin_manager()->renderHook($hook, $ctx);
    } catch (\Throwable $e) {
        error_log('[AirOJ plugin] render failed for ' . $hook . ': ' . $e->getMessage());
        return '';
    }
}

/** 模板中的 View 开头插入点 */
function plugin_view_head(array $ctx = []): void
{
    if (!isset($ctx['page'])) $ctx['page'] = $GLOBALS['page'] ?? '';
    echo plugin_render('Hook:Page/View.Head', $ctx);
}

/** 模板中的 View 结尾插入点 */
function plugin_view_tail(array $ctx = []): void
{
    if (!isset($ctx['page'])) $ctx['page'] = $GLOBALS['page'] ?? '';
    echo plugin_render('Hook:Page/View.Tail', $ctx);
}

/** 模板中的 View 中间显式插入点 */
function plugin_view_line(string $line = 'content', array $ctx = []): void
{
    if (!isset($ctx['page'])) $ctx['page'] = $GLOBALS['page'] ?? '';
    $ctx['line'] = $line;
    echo plugin_render('Hook:Page/View.Line', $ctx);
}

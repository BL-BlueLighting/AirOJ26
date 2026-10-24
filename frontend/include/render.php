<?php
/**
 * AirOJ MVC — 模板与主题资源解析
 */

/** 当前渲染主题（优先使用 init 已经解析好的 $theme） */
function current_render_theme(): string
{
    if (isset($GLOBALS['theme']) && is_string($GLOBALS['theme']) && $GLOBALS['theme'] !== '') {
        return $GLOBALS['theme'];
    }
    return function_exists('current_theme')
        ? current_theme($GLOBALS['user'] ?? null)
        : 'original';
}

/** 当前主题下的模板文件；主题没有覆盖时回落到 original */
function template_file(string $file): string
{
    $base = dirname(__DIR__) . '/templates/';
    $theme = current_render_theme();
    $themeFile = $base . $theme . '/' . $file;
    if (is_file($themeFile)) return $themeFile;
    return $base . 'original/' . $file;
}

/** 主题静态资源 URL（带缓存版本）；找不到时回落到 original */
function theme_asset(string $type, string $file, ?string $theme = null): string
{
    $base = dirname(__DIR__) . '/templates/';
    $theme = $theme ?? current_render_theme();
    foreach ([$theme, 'original'] as $t) {
        $abs = $base . $t . '/' . $type . '/' . $file;
        if (is_file($abs)) {
            return '/templates/' . $t . '/' . $type . '/' . $file . '?v=' . filemtime($abs);
        }
    }
    return '/templates/original/' . $type . '/' . $file;
}

/** 兼容旧版 include/header.php：从全局变量渲染 header */
function render_header(): void
{
    $vars = $GLOBALS;
    unset($vars['GLOBALS']);
    extract($vars, EXTR_SKIP);
    include template_file('header.php');
}

/** 兼容旧版 include/footer.php：从全局变量渲染 footer */
function render_footer(): void
{
    $vars = $GLOBALS;
    unset($vars['GLOBALS']);
    extract($vars, EXTR_SKIP);
    include template_file('footer.php');
}

/** 渲染完整页面（header + 页面模板 + footer） */
function render_page(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    include template_file('header.php');
    include template_file($template);
    include template_file('footer.php');
}

/** 渲染独立页面（登录 / 注册等自带完整 HTML 的页面） */
function render_standalone(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    include template_file($template);
}

/** 统一的错误页（带站点头部 / 页脚） */
function render_error(string $message, string $title = '出错了'): void
{
    $GLOBALS['title'] = $title;
    $GLOBALS['error_message'] = $message;
    $vars = $GLOBALS;
    unset($vars['GLOBALS']);
    extract($vars, EXTR_SKIP);
    include template_file('header.php');
    include template_file('error.php');
    include template_file('footer.php');
    exit;
}

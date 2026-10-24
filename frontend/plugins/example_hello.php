<?php
/**
 * AirOJ 插件示例。
 *
 * 插件是单个 PHP 文件，必须 return 一个 AirOJ\Plugin\Plugin 实例。
 * 直接通过浏览器访问本文件会返回 404；由插件加载器 include 时才执行。
 */
if (!defined('AIR_OJ_PLUGIN_LOADER')) {
    http_response_code(404);
    exit;
}

return new class extends \AirOJ\Plugin\Plugin {
    public function __construct()
    {
        $this->name = 'Hello Example';
        $this->author = 'AirOJ';
        $this->version = '1.0.0';
        $this->description = '演示插件：在页面内容顶部显示一条提示。默认不启用。';
        $this->permissions = ['Hook:Page/View.Head'];
    }

    public function boot(): void
    {
        $this->on('Hook:Page/View.Head', function (array $ctx): string {
            $page = htmlspecialchars((string)($ctx['page'] ?? ''));
            return '<div class="alert alert-info" style="margin-bottom:12px">'
                . '插件示例已启用 · 当前页面：' . $page
                . '</div>';
        });
    }
};

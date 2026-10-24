<?php
namespace AirOJ\Plugin;

/**
 * 插件基类。
 *
 * 插件文件应返回一个本类的实例，例如：
 *   return new class extends \AirOJ\Plugin\Plugin {
 *       public function __construct() { $this->name = '...'; ... }
 *       public function boot(): void {
 *           $this->on('Hook:Page/View.Head', fn(array $ctx) => '<div>...</div>');
 *       }
 *   };
 */
abstract class Plugin extends ExtInfo
{
    protected PluginManager $manager;
    protected string $slug = '';

    /** 由 PluginManager 调用，注入管理器与插件标识 */
    final public function attach(PluginManager $manager, string $slug): void
    {
        $this->manager = $manager;
        $this->slug = $slug;
    }

    /** 插件启用后调用，用于注册 Hook */
    public function boot(): void
    {
    }

    /** 注册一个功能钩子；未声明的权限会被忽略 */
    final protected function on(string $hook, callable $callback): void
    {
        $this->manager->registerHook($this->slug, $hook, $callback);
    }

    /** 调用一个 Call: 权限 */
    final protected function call(string $permission, mixed ...$args): mixed
    {
        return $this->manager->callPermission($this->slug, $permission, $args);
    }

    /** 查询一个 Have: 权限 */
    final protected function have(string $permission, mixed ...$args): bool
    {
        return (bool)$this->manager->callPermission($this->slug, $permission, $args);
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}

<?php
namespace AirOJ\Plugin;

/**
 * 插件发现、加载、Hook 分发与权限调用。
 */
class PluginManager
{
    private string $dir;
    /** @var array<string,Plugin> */
    private array $plugins = [];
    /** @var array<string,string> slug => error message */
    private array $errors = [];
    private bool $scanned = false;
    private bool $loaded = false;
    /** @var array<string,bool> */
    private array $loadedFiles = [];
    /** @var array<string,bool> */
    private array $active = [];
    /** @var array<string,array<string,array<int,callable>>> */
    private array $hooks = [];
    /** @var array<string,array<string,bool>> */
    private array $allowed = [];

    public function __construct(?string $dir = null)
    {
        $this->dir = rtrim($dir ?? (dirname(__DIR__, 2) . '/plugins'), '/');
    }

    public function dir(): string
    {
        return $this->dir;
    }

    /** @return array<string,Plugin> */
    public function plugins(): array
    {
        $this->scanAll();
        return $this->plugins;
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        $this->scanAll();
        return $this->errors;
    }

    public function plugin(string $slug): ?Plugin
    {
        $this->scanAll();
        return $this->plugins[$slug] ?? null;
    }

    /** 扫描全部插件并读取元信息（仅管理页使用；不执行 boot） */
    public function scanAll(): void
    {
        if ($this->scanned) return;
        $this->scanned = true;
        if (!is_dir($this->dir)) return;
        foreach (glob($this->dir . '/*.php') ?: [] as $file) {
            $this->loadFile($file);
        }
    }

    /** 加载单个插件文件；同一请求内只 include 一次 */
    private function loadFile(string $file): void
    {
        if (isset($this->loadedFiles[$file])) return;
        $this->loadedFiles[$file] = true;
        $slug = basename($file, '.php');
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug)) {
            $this->errors[$slug] = '插件文件名只能包含字母、数字、下划线和连字符';
            return;
        }
        if (!defined('AIR_OJ_PLUGIN_LOADER')) define('AIR_OJ_PLUGIN_LOADER', true);
        try {
            ob_start();
            /** @var mixed $plugin */
            $plugin = include $file;
            $extra = ob_get_clean();
            if ($extra !== '') {
                $this->errors[$slug] = '插件文件不应在加载时输出内容';
                return;
            }
            if (!$plugin instanceof Plugin) {
                $this->errors[$slug] = '插件文件必须 return 一个 AirOJ\\Plugin\\Plugin 实例';
                return;
            }
            $plugin->attach($this, $slug);
            $info = $plugin->toArray();
            if ($info['name'] === '' || $info['author'] === '' || $info['version'] === '' || $info['description'] === '') {
                $this->errors[$slug] = '插件必须声明名称、作者、版本和简介';
                return;
            }
            $normalized = [];
            foreach ($info['permissions'] as $perm) {
                if (!is_string($perm) || !PermissionRegistry::isKnown($perm)) {
                    $this->errors[$slug] = '声明了未知权限：' . (string)$perm;
                    return;
                }
                $normalized[] = $perm;
            }
            $plugin->permissions = array_values(array_unique($normalized));
            $this->plugins[$slug] = $plugin;
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) @ob_end_clean();
            $this->errors[$slug] = '加载失败：' . $e->getMessage();
        }
    }

    /** 从 DB 读取启用状态，只加载启用插件的文件，然后 boot */
    public function loadEnabled(): void
    {
        if ($this->loaded) return;
        $this->loaded = true;
        if (!is_dir($this->dir)) return;

        try {
            $rows = \Database::fetchAll("SELECT slug FROM plugins WHERE enabled=1");
        } catch (\Throwable $e) {
            return;
        }
        $enabled = [];
        foreach ($rows as $r) {
            $enabled[(string)$r['slug']] = true;
        }
        if (!$enabled) return;

        // 只 include 已启用插件，避免正常请求执行禁用插件的顶层代码
        foreach (array_keys($enabled) as $slug) {
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug)) continue;
            $file = $this->dir . '/' . $slug . '.php';
            if (is_file($file)) $this->loadFile($file);
        }

        try {
            $permRows = \Database::fetchAll("SELECT slug,permission,enabled FROM plugin_permissions");
            foreach ($permRows as $r) {
                $this->allowed[(string)$r['slug']][(string)$r['permission']] = (int)$r['enabled'] === 1;
            }
        } catch (\Throwable $e) {
            return;
        }

        foreach ($enabled as $slug => $_) {
            $plugin = $this->plugins[$slug] ?? null;
            if (!$plugin) continue;
            $this->active[$slug] = true;
            try {
                // boot 阶段允许注册钩子，但不应产生页面输出
                ob_start();
                $plugin->boot();
                $bootOutput = ob_get_clean();
                if ($bootOutput !== '') {
                    error_log('[AirOJ plugin] boot output ignored for ' . $slug);
                }
            } catch (\Throwable $e) {
                if (ob_get_level() > 0) @ob_end_clean();
                $this->active[$slug] = false;
                $this->errors[$slug] = '启动失败：' . $e->getMessage();
                error_log('[AirOJ plugin] boot failed for ' . $slug . ': ' . $e->getMessage());
            }
        }
    }

    public function isActive(string $slug): bool
    {
        return !empty($this->active[$slug]);
    }

    public function permissionAllowed(string $slug, string $permission): bool
    {
        return $this->isActive($slug) && !empty($this->allowed[$slug][$permission]);
    }

    /** 由 Plugin::on() 调用 */
    public function registerHook(string $slug, string $hook, callable $callback): void
    {
        $plugin = $this->plugins[$slug] ?? null;
        if (!$plugin || !$this->isActive($slug)) return;
        if (!PermissionRegistry::isKnown($hook) || !PermissionRegistry::isHook($hook)) return;
        if (!in_array($hook, $plugin->permissions, true)) return;
        $this->hooks[$hook][$slug][] = $callback;
    }

    /**
     * 分发 Hook。$ctx 可被回调按引用修改。
     * @return array<int,mixed> 回调返回值
     */
    public function doHook(string $hook, array &$ctx = []): array
    {
        if (!$this->loaded || !PermissionRegistry::isHook($hook)) return [];
        $out = [];
        foreach ($this->hooks[$hook] ?? [] as $slug => $callbacks) {
            if (!$this->permissionAllowed($slug, $hook)) continue;
            foreach ($callbacks as $cb) {
                try {
                    $result = $cb($ctx);
                    if ($result === false) $ctx['_cancelled'] = true;
                    $out[] = $result;
                } catch (\Throwable $e) {
                    error_log('[AirOJ plugin] hook ' . $hook . ' failed in ' . $slug . ': ' . $e->getMessage());
                }
            }
        }
        return $out;
    }

    /** 分发 View 类 Hook 并拼接字符串返回值 */
    public function renderHook(string $hook, array $ctx = []): string
    {
        $out = $this->doHook($hook, $ctx);
        $html = '';
        foreach ($out as $item) {
            if (is_string($item)) $html .= $item;
        }
        return $html;
    }

    /** 由 Plugin::call()/have() 调用 */
    public function callPermission(string $slug, string $permission, array $args): mixed
    {
        $plugin = $this->plugins[$slug] ?? null;
        if (!$plugin || !$this->isActive($slug)) return null;
        if (!in_array($permission, $plugin->permissions, true)) return null;
        if (!$this->permissionAllowed($slug, $permission)) return null;
        $def = PermissionRegistry::definition($permission);
        if (!$def || !in_array($def['type'], ['Call', 'Have'], true)) return null;
        try {
            return PluginApi::dispatch($permission, $args);
        } catch (\Throwable $e) {
            error_log('[AirOJ plugin] call ' . $permission . ' failed in ' . $slug . ': ' . $e->getMessage());
            return $def['type'] === 'Have' ? false : null;
        }
    }

    /** 把扫描结果同步到数据库；新插件默认禁用 */
    public function syncDatabase(): void
    {
        $this->scanAll();
        foreach ($this->plugins as $slug => $plugin) {
            $info = $plugin->toArray();
            try {
                $row = \Database::fetchOne("SELECT slug FROM plugins WHERE slug=?", [$slug]);
                if ($row) {
                    \Database::exec(
                        "UPDATE plugins SET name=?,author=?,version=?,description=?,icon=?,updated_at=? WHERE slug=?",
                        [$info['name'], $info['author'], $info['version'], $info['description'], $info['icon'], date('Y-m-d H:i:s'), $slug]
                    );
                } else {
                    \Database::insert(
                        "INSERT INTO plugins (slug,name,author,version,description,icon,enabled) VALUES (?,?,?,?,?,?,0)",
                        [$slug, $info['name'], $info['author'], $info['version'], $info['description'], $info['icon']]
                    );
                }
                $existing = [];
                foreach (\Database::fetchAll("SELECT permission FROM plugin_permissions WHERE slug=?", [$slug]) as $r) {
                    $existing[(string)$r['permission']] = true;
                }
                foreach ($plugin->permissions as $perm) {
                    if (!isset($existing[$perm])) {
                        \Database::insert(
                            "INSERT INTO plugin_permissions (slug,permission,enabled) VALUES (?,?,1)",
                            [$slug, $perm]
                        );
                    }
                }
                foreach (array_keys($existing) as $perm) {
                    if (!in_array($perm, $plugin->permissions, true)) {
                        \Database::exec("DELETE FROM plugin_permissions WHERE slug=? AND permission=?", [$slug, $perm]);
                    }
                }
            } catch (\Throwable $e) {
                $this->errors[$slug] = '同步数据库失败：' . $e->getMessage();
            }
        }
    }
}

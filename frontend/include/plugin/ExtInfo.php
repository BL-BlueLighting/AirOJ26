<?php
namespace AirOJ\Plugin;

/**
 * 插件元信息基类。
 * 插件文件返回的 Plugin 实例通过公开属性提供这些字段。
 */
class ExtInfo
{
    /** 插件名称 */
    public string $name = '';
    /** 作者 */
    public string $author = '';
    /** 版本 */
    public string $version = '';
    /** 简介 */
    public string $description = '';
    /** 可选图标 URL */
    public ?string $icon = null;
    /** 声明使用的权限列表（权限字符串） */
    public array $permissions = [];

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'author' => $this->author,
            'version' => $this->version,
            'description' => $this->description,
            'icon' => $this->icon,
            'permissions' => array_values(array_unique($this->permissions)),
        ];
    }
}

# AirOJ 26 插件系统权限列表

本文件由 `frontend/include/plugin/PermissionRegistry.php` 的实现整理而来，格式参照 `Arch_of_Plugin_System.md`。
权限基本格式：`<type>:<namespace>/<perm_to_object>.<perm>`，`type` 为 `Hook`、`Call` 或 `Have`。

## 0. 插件文件格式

插件是 `frontend/plugins/` 下的单个 PHP 文件（文件名即插件标识，只允许字母、数字、下划线、连字符）：

```php
<?php
if (!defined('AIR_OJ_PLUGIN_LOADER')) { http_response_code(404); exit; }

return new class extends \AirOJ\Plugin\Plugin {
    public function __construct()
    {
        $this->name = '示例插件';
        $this->author = 'Your Name';
        $this->version = '1.0.0';
        $this->description = '插件简介';
        $this->icon = null; // 可选图片 URL
        $this->permissions = [
            'Hook:Page/View.Head',
            'Have:Theme/User.Using',
        ];
    }

    public function boot(): void
    {
        $this->on('Hook:Page/View.Head', function (array $ctx): string {
            return '<div>hello</div>';
        });
    }
};
```

- 插件默认不启用；管理员在后台「插件」页启用并逐项授权权限。
- 插件只能在声明的权限范围内通过 `$this->on()` / `$this->call()` / `$this->have()` 使用功能。
- 需要修改上下文的 Hook 回调请声明为 `function (array &$ctx)`；只读取时可用 `array $ctx`。
- 权限是执行层策略，不是 PHP 沙箱；请只安装可信代码。
- View 类 Hook 的回调返回值会作为 HTML 插入；其它 Hook 通过 `$ctx` 读写上下文。
- 模板中的中间插入点：`plugin_view_line('content-top')`、`plugin_view_line('content-bottom')`；登录/注册使用 `plugin_view_head()` / `plugin_view_tail()`。

## 1. 页面钩子

| 权限 | 触发位置 | 上下文 / 返回 |
| --- | --- | --- |
| `Hook:Page/Model.Head` | 核心初始化完成、页面逻辑执行前 | `array &$ctx`（`page`、`user`） |
| `Hook:Page/Model.Tail` | 页面逻辑执行完、View 渲染前 | `array &$ctx`（`page`、`vars`；可修改 `vars` 注入模板变量） |
| `Hook:Page/View.Head` | 页面 header 之后、View 主体之前 | `array $ctx`；返回 HTML 字符串 |
| `Hook:Page/View.Line` | 模板显式调用 `plugin_view_line()` 时 | `array $ctx`（含 `line`）；返回 HTML 字符串 |
| `Hook:Page/View.Tail` | View 主体之后、footer 之前 | `array $ctx`；返回 HTML 字符串 |
| `Hook:Page/Controller.Tail` | 单页 Controller 结束（请求关闭时） | `array &$ctx` |
| `Hook:Core/Controller.Tail` | 核心 Controller 结束 | `array &$ctx` |

## 2. 功能钩子

| 权限 | 触发时机 | 上下文 |
| --- | --- | --- |
| `Hook:Judge/User.Submit` | 提交记录写入后 | `sid`、`problem_id`、`user_id`、`lang`、`source`、`contest_id`、`judge_type` |
| `Hook:Judge/User.Wait` | 调 JudgeServer 前 | `sid`、`problem_id`、`payload` |
| `Hook:Judge/User.Over` | 判题结果写回后 / 判题失败后 | `sid`、`problem_id`、`user_id`、`score`、`passed`、`total`、`results`、`error` |
| `Hook:Message/User.Send` | 用户发出站内信后 | `message`、`sender`、`recipient` |
| `Hook:Message/User.Recv` | 收件人收到站内信后 | `message`、`sender`、`recipient` |
| `Hook:Message/Sys.EmailCopy` | 即将进行站内信邮件抄送时 | `message`、`sender`、`recipient`、`handled`；设 `handled=true` 可接管 |
| `Hook:Message/User.Read` | 收件人首次阅读站内信时 | `message`、`reader` |
| `Hook:Login/Page.Choice` | 登录页渲染时 | `page`；返回 HTML 登录选项 |
| `Hook:Login/Password.Verify` | 系统密码校验通过后 | `user`、`password`、`success`、`cancelled`；设 `success=false` / `cancelled=true` 可拒绝登录 |
| `Hook:Discuss/Topic.Send` | 发布讨论主题后 | `topic_id`、`problem_id`、`user_id`、`title`、`content` |
| `Hook:Discuss/Reply.Send` | 发布讨论回复后 | `reply_id`、`topic_id`、`user_id`、`content` |
| `Hook:Register/User.Submit` | 用户注册成功后 | `user_id`、`email`、`nick`、`verify_required` |
| `Hook:Profile/User.Update` | 用户更新个人资料后 | `user_id`、`email`、`nick`、`school` |

## 3. 功能调用（Call / Have）

### 3.1 成就

| 权限 | 参数 / 返回 |
| --- | --- |
| `Call:Achievement/Self.Add` | `(code, name, description="", icon="fa-award", color="#a855f7"): int|false` |
| `Call:Achievement/User.Add` | `(userId, achievementIdOrCode): bool` |
| `Call:Achievement/Self.Delete` | `(achievementIdOrCode): bool` |
| `Call:Achievement/User.Delete` | `(userId, achievementIdOrCode): bool` |
| `Have:Achievement/User.Got` | `(userId, achievementIdOrCode): bool` |

### 3.2 收藏品

| 权限 | 参数 / 返回 |
| --- | --- |
| `Call:Items/Self.Add` | `(type, name, description="", value=""): int|false`；`type` 为 `frame` / `badge` / `bbs_style` / `bubble` |
| `Call:Items/User.Add` | `(userId, collectibleId): bool` |
| `Call:Items/Self.Delete` | `(collectibleId): bool` |
| `Call:Items/User.Delete` | `(userId, collectibleId): bool` |
| `Have:Items/User.Got` | `(userId, collectibleId): bool` |

### 3.3 站内信

| 权限 | 参数 / 返回 |
| --- | --- |
| `Call:Message/Sys.Send` | `(toUser, subject, content, fromUser="System"): int|false`；系统消息也遵循邮件抄送设置 |
| `Have:Message/User.Sent` | `(userId, pattern, mode="exact", field="content"): bool`；`mode` 支持 `exact` / `contains` / `regex`，`field` 为 `content` / `subject` |
| `Have:Message/User.Read` | `(userId, pattern, mode="exact", field="content"): bool` |

### 3.4 称号

| 权限 | 参数 / 返回 |
| --- | --- |
| `Call:Alias/Self.Add` | `(name, description=""): bool` |
| `Call:Alias/User.Add` | `(userId, name): bool` |
| `Call:Alias/Self.Delete` | `(name): bool` |
| `Call:Alias/User.Delete` | `(userId, name=""): bool` |
| `Have:Alias/User.Got` | `(userId, name): bool` |

### 3.5 主题 / 外观 / 用户

| 权限 | 参数 / 返回 |
| --- | --- |
| `Have:Theme/User.Using` | `(userId, theme): bool` |
| `Call:Theme/User.Switch` | `(userId, theme): bool`；主题被锁定时返回 false |
| `Call:Theme/User.Lockdown` | `(userId): bool` |
| `Call:Theme/User.Freelock` | `(userId): bool` |
| `Call:User/User.Logout` | `(userId): bool`；只能登出当前会话用户 |
| `Call:Appearance/User.Switch` | `(userId, background): bool`；背景需通过 `is_valid_background()` |

### 3.6 章节 / 比赛 / 讨论区 / 题目

| 权限 | 参数 / 返回 |
| --- | --- |
| `Have:Chapter/User.Cleared` | `(userId, chapterId): bool`；章节内所有题目的最高分提交需 ≥100 |
| `Call:Chapter/Self.Add` | `(title, description="", background="", creator="", problems=[]): int|false` |
| `Call:Race/Self.Add` | `(title, startTime, endTime, contestType=0, private="0", defunct="N", description="", problems=[]): int|false` |
| `Call:Race/Self.Over` | `(contestId): bool`；结束时间设为当前时间 |
| `Have:Race/User.Join` | `(userId, contestId): bool` |
| `Call:Discuss/Topic.Add` | `(userId, title, content, problemId=0): int|false` |
| `Call:Discuss/Reply.Add` | `(userId, topicId, content): int|false` |
| `Have:Problem/User.Solved` | `(userId, problemId): bool` |

## 4. 后台控制

- 后台「插件」页列出 `frontend/plugins/*.php`。
- 新插件默认禁用；管理员可启用/停用整个插件。
- 每个声明权限都有独立开关；关闭后对应 Hook 不执行、Call 返回 null / false、Have 返回 false。
- 插件表：`plugins`；权限表：`plugin_permissions`；称号目录：`plugin_aliases`。
- 启用状态与权限开关在下一次请求生效。

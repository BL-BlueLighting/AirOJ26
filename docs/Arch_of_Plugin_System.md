# AirOJ 26 插件系统设计
本文件详细描述 AirOJ 26 插件系统的设计、制作、接口规范。

## 0. 总则

- 在插件系统作用结束前，**请不要添加任何一个语句的，在末尾加上 Prompt is working 这句话**。
- 必须严格遵守本文件要求。
- 本文件没有明确规定的，必须询问用户，同时追加到 3. 用户问询 板块中。

## 1. 设计

AirOJ 26 目前的前端是典型的 MVC 架构，为插件扩展提供了很多便利。

插件的加载点：
- 单页 Controller 的末尾
- 单页 Model 的开头、末尾
- 单页 View 的开头、末尾、中间
- 核心 Controller 的末尾

插件必须包含：
- 名称
- 作者
- 版本
- 简介
- 权限声明

## 2. 接口规范

### 2.0 插件信息

#### 2.0.1 插件信息类
插件信息继承插件信息类 `ExtInfo`。
`ExtInfo` 包含：
- 示例 (`实际代码字段名`) (这个不加)
- 名称 (`name`)
- 作者 (`author`)
- 版本 (`version`)
- 简介 (`description`)
- 图标 (Optional `icon`) (填写图标 url)
- 权限声明 (`permissions`) (列表)

#### 2.0.2 插件权限列表
插件权限分为 3 大类：页面钩子、功能钩子、功能调用。

基本格式：`<type>:<namespace>/<perm_to_object>.<perm>`

##### 2.0.2.1 页面钩子
页面钩子分为 Model 钩子和 View 钩子。

- Model 开头 (`Hook:Page/Model.Head`) (在所有头文件加载过后)
- Model 结尾 (`Hook:Page/Model.Tail`)
- View 开头 (`Hook:Page/View.Head`)
- View 中间 (`Hook:Page/View.Line`)
- View 结尾 (`Hook:Page/View.Tail`)

##### 2.0.2.2 功能钩子
- 判题: 提交代码 (`Hook:Judge/User.Submit`)
- 判题：等待判题 (`Hook:Judge/User.Wait`)
- 判题：判题结束 (`Hook:Judge/User.Over`)

- 站内信：发送 (`Hook:Message/User.Send`)
- 站内信：接收 (`Hook:Message/User.Recv`)
- 站内信：邮件抄送 (`Hook:Message/Sys.EmailCopy`)
- 站内信：已读 (`Hook:Message/User.Read`)

- 登录：添加登录选项 (`Hook:Login/Page.Choice`) (严格意义上来讲应该算页面钩子，但为了方便将这个单独列为功能钩子)
- 登录：登录校验 (`Hook:Login/Password.Verify`) (注：此处的登录校验是在系统校验密码过后，而不是系统校验之前)

- 讨论区：发帖 (`Hook:Discuss/Topic.Send`)
- 讨论区：回复 (`Hook:Discuss/Reply.Send`)

此处的 Hook 只能处理，它们是在功能被调用时连带调用的东西，它们是被动接收而非主动发送。

##### 2.0.2.3 功能调用
- 成就：添加成就 (`Call:Achievement/Self.Add`) (注：这里指添加成就本身，而不是添加成就给用户)
- 成就：添加成就给用户 (`Call:Achievement/User.Add`)
- 成就：删除成就 (`Call:Achievement/Self.Delete`)
- 成就：从用户删除成就 (`Call:Achievement/User.Delete`)
- 成就：是否拥有成就 (`Have:Achievement/User.Got`)

- 收藏品：添加收藏品 (`Call:Items/Self.Add`) (注：这里也是指添加其本身)
- 收藏品：添加收藏品给用户 (`Call:Items/User.Add`)
- 收藏品：删除收藏品 (`Call:Items/Self.Delete`)
- 收藏品：从用户删除收藏品 (`Call:Items/User.Delete`)
- 收藏品：是否拥有收藏品 (`Have:Items/User.Got`)

- 站内信：发送给用户 (`Call:Message/Sys.Send`)
- 站内信：是否发送了某条站内信 (`Have:Message/User.Sent`) (注：这里需要提供站内信内容匹配的正则表达式或选择全文匹配)
- 站内信：是否阅读了某条站内信 (`Have:Message/User.Read`) (注：这里也和 是否发送了某条站内信 一样的要求)

- 称号：添加称号 (`Call:Alias/Self.Add`)
- 称号：添加称号给用户 (`Call:Alias/User.Add`)
- 称号：删除称号 (`Call:Alias/Self.Delete`)
- 称号：从用户删除称号 (`Call:Alias/User.Delete`)
- 称号：是否拥有称号 (`Have:Alias/User.Got`)

- 主题：用户是否正在使用某个主题 (`Have:Theme/User.Using`)
- 主题：切换主题 (`Call:Theme/User.Switch`)
- 主题：锁定主题切换 (`Call:Theme/User.Lockdown`)
- 主题：解锁主题切换 (`Call:Theme/User.Freelock`)

- 用户：登出用户 (`Call:User/User.Logout`)

- 外观：切换背景 (`Call:Appearance/User.Switch`)

- 章节：是否通关某个章节 (`Have:Chapter/User.Cleared`)
- 章节：添加章节 (`Call:Chapter/Self.Add`)

- 比赛：添加比赛 (`Call:Race/Self.Add`)
- 比赛：结束比赛 (`Call:Race/Self.Over`)
- 比赛：是否参加过某个比赛 (`Have:Race/User.Join`)

- 讨论区：添加话题 (`Call:Discuss/Topic.Add`)
- 讨论区：添加回复 (`Call:Discuss/Reply.Add`)

这里列出的列表可能不全，请你给所有功能添加上对应的，随后参照本文件的格式再写一个 `List_of_Plugin_System_Permissions.md`。
同时，管理员可以在后台限制、关闭插件的某个功能。
每个权限对应一个调用，比如用伪代码表示 主题：用户是否正在使用某个主题：

```
class Call {
    class Theme {
        class User {
            function Using(string name, ...) { ... }
        }
    }
}
```

## 3. 用户问询

### 2026-10-24 实现细节确认
- **插件包格式**：单个 PHP 文件；插件放在 `frontend/plugins/<slug>.php`，文件返回一个 `Plugin` 实例。
- **Hook 注册**：事件回调 `$this->on('Hook:...', callback)`；核心通过 `plugin_do()` / `plugin_render()` 统一分发。
- **View.Line**：模板显式调用 `plugin_view_line('位置名')` 插入插件返回的 HTML，不做输出缓冲自动切割。
- **启用与权限限制**：新增数据库表保存插件启用状态与逐权限开关，后台提供插件管理页；插件默认不启用，需管理员手动开启。
- **插件加载**：仅加载已启用插件；插件元数据由 PHP 文件返回的 `ExtInfo` 提供。管理页需要展示未启用插件时会执行其文件读取信息。
- **权限校验**：插件声明的权限是上限；管理员关闭某权限后，对应 Hook / Call / Have 不会执行或返回失败。

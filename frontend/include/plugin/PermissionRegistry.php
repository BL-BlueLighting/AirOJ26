<?php
namespace AirOJ\Plugin;

/**
 * 插件权限注册表：Hook / Call / Have 三类。
 * handler 为 PluginApi 中的静态方法名。
 */
class PermissionRegistry
{
    private static ?array $all = null;

    public static function all(): array
    {
        if (self::$all !== null) return self::$all;

        $h = static fn(string $group, string $description, string $signature = 'array &$ctx'): array =>
            ['type' => 'Hook', 'group' => $group, 'description' => $description, 'signature' => $signature];
        $c = static fn(string $group, string $description, string $signature, string $handler): array =>
            ['type' => 'Call', 'group' => $group, 'description' => $description, 'signature' => $signature, 'handler' => $handler];
        $v = static fn(string $group, string $description, string $signature, string $handler): array =>
            ['type' => 'Have', 'group' => $group, 'description' => $description, 'signature' => $signature, 'handler' => $handler];

        self::$all = [
            // ── 页面 / 核心钩子 ──
            'Hook:Page/Model.Head'     => $h('页面', '页面 Model 开头（所有头文件加载后、页面逻辑执行前）'),
            'Hook:Page/Model.Tail'     => $h('页面', '页面 Model 结尾（页面逻辑执行后、View 渲染前）'),
            'Hook:Page/View.Head'      => $h('页面', 'View 开头，返回值会插入页面内容区', 'array $ctx; return ?string'),
            'Hook:Page/View.Line'      => $h('页面', 'View 中间显式插入点，模板调用 plugin_view_line() 时触发', 'array $ctx; return ?string'),
            'Hook:Page/View.Tail'      => $h('页面', 'View 结尾，返回值会插入页面内容区', 'array $ctx; return ?string'),
            'Hook:Page/Controller.Tail'=> $h('页面', '单页 Controller 结束（请求关闭时）'),
            'Hook:Core/Controller.Tail'=> $h('核心', '核心 Controller 结束（include/__init__.php 末尾）'),

            // ── 评测 ──
            'Hook:Judge/User.Submit' => $h('判题', '用户提交代码、写入提交记录后触发'),
            'Hook:Judge/User.Wait'   => $h('判题', '本地记录已建立、请求 JudgeServer 前触发'),
            'Hook:Judge/User.Over'   => $h('判题', '判题结果写回数据库后触发'),

            // ── 站内信 ──
            'Hook:Message/User.Send'      => $h('站内信', '用户发出站内信后触发'),
            'Hook:Message/User.Recv'      => $h('站内信', '收件人收到站内信后触发'),
            'Hook:Message/Sys.EmailCopy'  => $h('站内信', '站内信需要邮件抄送时触发；插件可将 $ctx[\'handled\'] 设为 true 接管发送'),
            'Hook:Message/User.Read'      => $h('站内信', '收件人首次阅读站内信时触发'),

            // ── 登录 ──
            'Hook:Login/Page.Choice'     => $h('登录', '登录页渲染时触发，返回值显示为额外登录选项', 'array $ctx; return ?string'),
            'Hook:Login/Password.Verify' => $h('登录', '系统密码校验通过后触发；将 $ctx[\'success\'] 设为 false 或 $ctx[\'cancelled\'] 设为 true 可拒绝登录'),

            // ── 讨论区 ──
            'Hook:Discuss/Topic.Send' => $h('讨论区', '发布主题后触发'),
            'Hook:Discuss/Reply.Send' => $h('讨论区', '发布回复后触发'),

            // ── 注册 / 资料 ──
            'Hook:Register/User.Submit' => $h('用户', '用户注册成功后触发'),
            'Hook:Profile/User.Update'  => $h('用户', '用户更新个人资料或邮箱后触发'),

            // ── 成就 ──
            'Call:Achievement/Self.Add' => $c('成就', '创建一个成就定义', '(string $code, string $name, string $description="", string $icon="fa-award", string $color="#a855f7"): int|false', 'achievementAdd'),
            'Call:Achievement/User.Add' => $c('成就', '把成就授予用户', '(string $userId, int|string $achievementIdOrCode): bool', 'achievementUserAdd'),
            'Call:Achievement/Self.Delete' => $c('成就', '删除成就定义及其授予记录', '(int|string $achievementIdOrCode): bool', 'achievementDelete'),
            'Call:Achievement/User.Delete' => $c('成就', '从用户移除成就', '(string $userId, int|string $achievementIdOrCode): bool', 'achievementUserDelete'),
            'Have:Achievement/User.Got'    => $v('成就', '用户是否拥有成就', '(string $userId, int|string $achievementIdOrCode): bool', 'achievementUserGot'),

            // ── 收藏品 ──
            'Call:Items/Self.Add' => $c('收藏品', '创建一个收藏品定义', '(string $type, string $name, string $description="", string $value=""): int|false', 'itemAdd'),
            'Call:Items/User.Add' => $c('收藏品', '把收藏品授予用户', '(string $userId, int|string $collectibleId): bool', 'itemUserAdd'),
            'Call:Items/Self.Delete' => $c('收藏品', '删除收藏品定义及其授予记录', '(int|string $collectibleId): bool', 'itemDelete'),
            'Call:Items/User.Delete' => $c('收藏品', '从用户移除收藏品', '(string $userId, int|string $collectibleId): bool', 'itemUserDelete'),
            'Have:Items/User.Got'    => $v('收藏品', '用户是否拥有收藏品', '(string $userId, int|string $collectibleId): bool', 'itemUserGot'),

            // ── 站内信功能调用 ──
            'Call:Message/Sys.Send'    => $c('站内信', '以系统身份向用户发送站内信', '(string $toUser, string $subject, string $content, string $fromUser="System"): int|false', 'messageSysSend'),
            'Have:Message/User.Sent'   => $v('站内信', '用户是否发送过匹配的站内信', '(string $userId, string $pattern, string $mode="exact", string $field="content"): bool', 'messageUserSent'),
            'Have:Message/User.Read'   => $v('站内信', '用户是否读过匹配的站内信', '(string $userId, string $pattern, string $mode="exact", string $field="content"): bool', 'messageUserRead'),

            // ── 称号 ──
            'Call:Alias/Self.Add' => $c('称号', '创建一个称号定义', '(string $name, string $description=""): bool', 'aliasAdd'),
            'Call:Alias/User.Add' => $c('称号', '给用户设置称号', '(string $userId, string $name): bool', 'aliasUserAdd'),
            'Call:Alias/Self.Delete' => $c('称号', '删除称号定义', '(string $name): bool', 'aliasDelete'),
            'Call:Alias/User.Delete' => $c('称号', '移除用户称号（仅当匹配时）', '(string $userId, string $name=""): bool', 'aliasUserDelete'),
            'Have:Alias/User.Got'    => $v('称号', '用户是否拥有某称号', '(string $userId, string $name): bool', 'aliasUserGot'),

            // ── 主题 ──
            'Have:Theme/User.Using'  => $v('主题', '用户正在使用某主题', '(string $userId, string $theme): bool', 'themeUserUsing'),
            'Call:Theme/User.Switch' => $c('主题', '切换用户主题', '(string $userId, string $theme): bool', 'themeUserSwitch'),
            'Call:Theme/User.Lockdown' => $c('主题', '锁定用户主题切换', '(string $userId): bool', 'themeUserLock'),
            'Call:Theme/User.Freelock' => $c('主题', '解除用户主题锁定', '(string $userId): bool', 'themeUserUnlock'),

            // ── 用户 / 外观 ──
            'Call:User/User.Logout' => $c('用户', '登出当前用户', '(string $userId): bool', 'userLogout'),
            'Call:Appearance/User.Switch' => $c('外观', '切换用户页面背景', '(string $userId, string $background): bool', 'appearanceSwitch'),

            // ── 章节 ──
            'Have:Chapter/User.Cleared' => $v('章节', '用户是否通关某章节（所有题目 AC）', '(string $userId, int $chapterId): bool', 'chapterUserCleared'),
            'Call:Chapter/Self.Add'     => $c('章节', '创建章节并关联题目', '(string $title, string $description="", string $background="", string $creator="", array $problems=[]): int|false', 'chapterAdd'),

            // ── 比赛 ──
            'Call:Race/Self.Add' => $c('比赛', '创建比赛并关联题目', '(string $title, string $startTime, string $endTime, int $contestType=0, string $private="0", string $defunct="N", string $description="", array $problems=[]): int|false', 'raceAdd'),
            'Call:Race/Self.Over' => $c('比赛', '结束比赛（将结束时间设为当前时间）', '(int $contestId): bool', 'raceOver'),
            'Have:Race/User.Join' => $v('比赛', '用户是否参加过某比赛', '(string $userId, int $contestId): bool', 'raceUserJoin'),

            // ── 讨论区功能调用 ──
            'Call:Discuss/Topic.Add' => $c('讨论区', '创建讨论主题', '(string $userId, string $title, string $content, int $problemId=0): int|false', 'discussTopicAdd'),
            'Call:Discuss/Reply.Add' => $c('讨论区', '创建讨论回复', '(string $userId, int $topicId, string $content): int|false', 'discussReplyAdd'),

            // ── 题目（补充） ──
            'Have:Problem/User.Solved' => $v('题目', '用户是否已通过某题', '(string $userId, int|string $problemId): bool', 'problemUserSolved'),
        ];
        return self::$all;
    }

    public static function isKnown(string $permission): bool
    {
        return isset(self::all()[$permission]);
    }

    public static function isHook(string $permission): bool
    {
        return (self::all()[$permission]['type'] ?? '') === 'Hook';
    }

    public static function definition(string $permission): ?array
    {
        return self::all()[$permission] ?? null;
    }

    public static function groups(): array
    {
        $groups = [];
        foreach (self::all() as $permission => $def) {
            $groups[$def['group']][] = ['permission' => $permission] + $def;
        }
        return $groups;
    }
}

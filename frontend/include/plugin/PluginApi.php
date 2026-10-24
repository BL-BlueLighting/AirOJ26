<?php
namespace AirOJ\Plugin;

/**
 * Call: / Have: 权限的实际实现。
 * 所有方法都是静态方法，由 PluginManager 在校验声明与管理员开关后调用。
 */
class PluginApi
{
    public static function dispatch(string $permission, array $args): mixed
    {
        $def = PermissionRegistry::definition($permission);
        $handler = $def['handler'] ?? '';
        if ($handler === '' || !method_exists(self::class, $handler)) return null;
        return call_user_func_array([self::class, $handler], $args);
    }

    // ── 成就 ──
    public static function achievementAdd(string $code, string $name, string $description = '', string $icon = 'fa-award', string $color = '#a855f7'): int|false
    {
        $code = trim($code);
        $name = trim($name);
        if ($code === '' || $name === '') return false;
        try {
            return \Database::insert(
                "INSERT INTO achievements (code,name,description,icon,color) VALUES (?,?,?,?,?)",
                [$code, $name, $description, $icon ?: 'fa-award', $color ?: '#a855f7']
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function achievementUserAdd(string $userId, int|string $achievement): bool
    {
        $a = self::resolveAchievement($achievement);
        if (!$a || !self::userExists($userId)) return false;
        if (\Database::fetchOne("SELECT 1 AS x FROM user_achievements WHERE user_id=? AND achievement_id=?", [$userId, $a['achievement_id']])) {
            return true;
        }
        try {
            \Database::exec(
                "INSERT INTO user_achievements (user_id,achievement_id) VALUES (?,?)",
                [$userId, $a['achievement_id']]
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function achievementDelete(int|string $achievement): bool
    {
        $a = self::resolveAchievement($achievement);
        if (!$a) return false;
        \Database::exec("DELETE FROM user_achievements WHERE achievement_id=?", [$a['achievement_id']]);
        \Database::exec("DELETE FROM achievements WHERE achievement_id=?", [$a['achievement_id']]);
        return true;
    }

    public static function achievementUserDelete(string $userId, int|string $achievement): bool
    {
        $a = self::resolveAchievement($achievement);
        if (!$a || !self::userExists($userId)) return false;
        \Database::exec(
            "DELETE FROM user_achievements WHERE user_id=? AND achievement_id=?",
            [$userId, $a['achievement_id']]
        );
        return true;
    }

    public static function achievementUserGot(string $userId, int|string $achievement): bool
    {
        $a = self::resolveAchievement($achievement);
        if (!$a) return false;
        return (bool)\Database::fetchOne(
            "SELECT 1 AS x FROM user_achievements WHERE user_id=? AND achievement_id=?",
            [$userId, $a['achievement_id']]
        );
    }

    // ── 收藏品 ──
    public static function itemAdd(string $type, string $name, string $description = '', string $value = ''): int|false
    {
        $type = in_array($type, ['frame', 'badge', 'bbs_style', 'bubble'], true) ? $type : 'badge';
        $name = trim($name);
        if ($name === '') return false;
        try {
            return \Database::insert(
                "INSERT INTO collectibles (type,name,description,value) VALUES (?,?,?,?)",
                [$type, $name, $description, $value]
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function itemUserAdd(string $userId, int|string $collectibleId): bool
    {
        $c = self::resolveCollectible($collectibleId);
        if (!$c || !self::userExists($userId)) return false;
        if (\Database::fetchOne("SELECT 1 AS x FROM user_collectibles WHERE user_id=? AND collectible_id=?", [$userId, $c['collectible_id']])) {
            return true;
        }
        try {
            \Database::exec(
                "INSERT INTO user_collectibles (user_id,collectible_id) VALUES (?,?)",
                [$userId, $c['collectible_id']]
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function itemDelete(int|string $collectibleId): bool
    {
        $c = self::resolveCollectible($collectibleId);
        if (!$c) return false;
        \Database::exec("DELETE FROM user_collectibles WHERE collectible_id=?", [$c['collectible_id']]);
        \Database::exec("DELETE FROM collectibles WHERE collectible_id=?", [$c['collectible_id']]);
        return true;
    }

    public static function itemUserDelete(string $userId, int|string $collectibleId): bool
    {
        $c = self::resolveCollectible($collectibleId);
        if (!$c || !self::userExists($userId)) return false;
        \Database::exec(
            "DELETE FROM user_collectibles WHERE user_id=? AND collectible_id=?",
            [$userId, $c['collectible_id']]
        );
        return true;
    }

    public static function itemUserGot(string $userId, int|string $collectibleId): bool
    {
        $c = self::resolveCollectible($collectibleId);
        if (!$c) return false;
        return (bool)\Database::fetchOne(
            "SELECT 1 AS x FROM user_collectibles WHERE user_id=? AND collectible_id=?",
            [$userId, $c['collectible_id']]
        );
    }

    // ── 站内信 ──
    public static function messageSysSend(string $toUser, string $subject, string $content, string $fromUser = 'System'): int|false
    {
        if (!self::userExists($toUser) || trim($subject) === '' || trim($content) === '') return false;
        $mid = \Database::insert(
            "INSERT INTO messages (from_user,to_user,subject,content) VALUES (?,?,?,?)",
            [$fromUser ?: 'System', $toUser, $subject, $content]
        );
        if (!$mid) return false;
        // 系统消息同样遵循站内信邮件抄送设置
        if (function_exists('message_email_notify_enabled') && message_email_notify_enabled()) {
            $recipient = \Database::fetchOne("SELECT user_id,nick,email,email_verified FROM users WHERE user_id=?", [$toUser]);
            $sender = ['user_id' => $fromUser ?: 'System', 'nick' => $fromUser ?: 'System'];
            if ($recipient && function_exists('send_message_email')) {
                send_message_email($recipient, $sender, ['id' => $mid, 'subject' => $subject, 'content' => $content]);
            }
        }
        return $mid;
    }

    public static function messageUserSent(string $userId, string $pattern, string $mode = 'exact', string $field = 'content'): bool
    {
        return self::messageMatch($userId, $pattern, $mode, $field, 'from_user', null);
    }

    public static function messageUserRead(string $userId, string $pattern, string $mode = 'exact', string $field = 'content'): bool
    {
        return self::messageMatch($userId, $pattern, $mode, $field, 'to_user', 1);
    }

    // ── 称号 ──
    public static function aliasAdd(string $name, string $description = ''): bool
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 32) return false;
        if (\Database::fetchOne("SELECT name FROM plugin_aliases WHERE name=?", [$name])) return true;
        try {
            \Database::insert("INSERT INTO plugin_aliases (name,description) VALUES (?,?)", [$name, $description]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function aliasUserAdd(string $userId, string $name): bool
    {
        $name = trim($name);
        if ($name === '' || !self::userExists($userId)) return false;
        \Database::exec("UPDATE users SET title=? WHERE user_id=?", [$name, $userId]);
        return true;
    }

    public static function aliasDelete(string $name): bool
    {
        $name = trim($name);
        if ($name === '') return false;
        \Database::exec("DELETE FROM plugin_aliases WHERE name=?", [$name]);
        \Database::exec("UPDATE users SET title='' WHERE title=?", [$name]);
        return true;
    }

    public static function aliasUserDelete(string $userId, string $name = ''): bool
    {
        if (!self::userExists($userId)) return false;
        if ($name === '') {
            \Database::exec("UPDATE users SET title='' WHERE user_id=?", [$userId]);
        } else {
            \Database::exec("UPDATE users SET title='' WHERE user_id=? AND title=?", [$userId, $name]);
        }
        return true;
    }

    public static function aliasUserGot(string $userId, string $name): bool
    {
        $row = \Database::fetchOne("SELECT title FROM users WHERE user_id=?", [$userId]);
        return $row && (string)$row['title'] === $name;
    }

    // ── 主题 ──
    public static function themeUserUsing(string $userId, string $theme): bool
    {
        $row = \Database::fetchOne("SELECT theme FROM users WHERE user_id=?", [$userId]);
        return $row && (string)$row['theme'] === $theme;
    }

    public static function themeUserSwitch(string $userId, string $theme): bool
    {
        if (!function_exists('valid_theme') || !valid_theme($theme)) return false;
        $row = \Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$userId]);
        if (!$row) return false;
        if (!empty($row['theme_locked'])) return false;
        // 只切换当前会话用户时更新 cookie/session；切换其他用户直接写库
        if (($_SESSION['airoj_user']['user_id'] ?? '') === $userId) {
            return function_exists('set_theme') ? set_theme($theme, $row) : false;
        }
        \Database::exec("UPDATE users SET theme=? WHERE user_id=?", [$theme, $userId]);
        return true;
    }

    public static function themeUserLock(string $userId): bool
    {
        if (!self::userExists($userId)) return false;
        \Database::exec("UPDATE users SET theme_locked=1 WHERE user_id=?", [$userId]);
        return true;
    }

    public static function themeUserUnlock(string $userId): bool
    {
        if (!self::userExists($userId)) return false;
        \Database::exec("UPDATE users SET theme_locked=0 WHERE user_id=?", [$userId]);
        return true;
    }

    // ── 用户 / 外观 ──
    public static function userLogout(string $userId): bool
    {
        if (($_SESSION['airoj_user']['user_id'] ?? '') !== $userId) return false;
        unset($_SESSION['airoj_user'], $_SESSION['twofa_uid']);
        return true;
    }

    public static function appearanceSwitch(string $userId, string $background): bool
    {
        if (!function_exists('is_valid_background') || !is_valid_background($background)) return false;
        if (!self::userExists($userId)) return false;
        \Database::exec("UPDATE users SET background=? WHERE user_id=?", [$background, $userId]);
        return true;
    }

    // ── 章节 ──
    public static function chapterUserCleared(string $userId, int $chapterId): bool
    {
        $problems = \Database::fetchAll(
            "SELECT problem_id FROM chapter_problem WHERE chapter_id=? ORDER BY position ASC",
            [$chapterId]
        );
        if (!$problems) return false;
        foreach ($problems as $p) {
            $ac = \Database::fetchOne(
                "SELECT 1 AS x FROM submissions WHERE user_id=? AND problem_id=? AND status='done' AND score>=100 LIMIT 1",
                [$userId, (string)$p['problem_id']]
            );
            if (!$ac) return false;
        }
        return true;
    }

    public static function chapterAdd(string $title, string $description = '', string $background = '', string $creator = '', array $problems = []): int|false
    {
        $title = trim($title);
        if ($title === '') return false;
        if ($background !== '' && function_exists('is_valid_background') && !is_valid_background($background)) $background = '';
        $cid = \Database::insert(
            "INSERT INTO chapters (title,description,background,creator) VALUES (?,?,?,?)",
            [$title, $description, $background, $creator]
        );
        if (!$cid) return false;
        $pos = 1;
        foreach ($problems as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;
            try {
                \Database::insert(
                    "INSERT INTO chapter_problem (chapter_id,problem_id,position) VALUES (?,?,?)",
                    [$cid, $pid, $pos++]
                );
            } catch (\Throwable $e) {
            }
        }
        return $cid;
    }

    // ── 比赛 ──
    public static function raceAdd(string $title, string $startTime, string $endTime, int $contestType = 0, string $private = '0', string $defunct = 'N', string $description = '', array $problems = []): int|false
    {
        $title = trim($title);
        if ($title === '' || strtotime($startTime) === false || strtotime($endTime) === false) return false;
        $private = $private === '1' ? '1' : '0';
        $defunct = $defunct === 'Y' ? 'Y' : 'N';
        $id = \Database::insert(
            "INSERT INTO contest (title,start_time,end_time,contest_type,private,defunct,description) VALUES (?,?,?,?,?,?,?)",
            [$title, $startTime, $endTime, $contestType, $private, $defunct, $description]
        );
        if (!$id) return false;
        $num = 1;
        foreach ($problems as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;
            try {
                \Database::insert("INSERT INTO contest_problem (problem_id,contest_id,num) VALUES (?,?,?)", [$pid, $id, $num++]);
            } catch (\Throwable $e) {
            }
        }
        return $id;
    }

    public static function raceOver(int $contestId): bool
    {
        return \Database::exec(
            "UPDATE contest SET end_time=?, defunct='Y' WHERE contest_id=?",
            [date('Y-m-d H:i:s'), $contestId]
        ) > 0;
    }

    public static function raceUserJoin(string $userId, int $contestId): bool
    {
        return (bool)\Database::fetchOne(
            "SELECT 1 AS x FROM submissions WHERE user_id=? AND contest_id=? LIMIT 1",
            [$userId, $contestId]
        );
    }

    // ── 讨论区 ──
    public static function discussTopicAdd(string $userId, string $title, string $content, int $problemId = 0): int|false
    {
        if (!self::userExists($userId) || trim($title) === '' || trim($content) === '') return false;
        return \Database::insert(
            "INSERT INTO bbs_topic (problem_id,user_id,title,content,reply_count) VALUES (?,?,?,?,0)",
            [$problemId, $userId, $title, $content]
        );
    }

    public static function discussReplyAdd(string $userId, int $topicId, string $content): int|false
    {
        if (!self::userExists($userId) || trim($content) === '') return false;
        if (!\Database::fetchOne("SELECT 1 AS x FROM bbs_topic WHERE topic_id=?", [$topicId])) return false;
        $rid = \Database::insert(
            "INSERT INTO bbs_reply (topic_id,user_id,content) VALUES (?,?,?)",
            [$topicId, $userId, $content]
        );
        if ($rid) {
            \Database::exec(
                "UPDATE bbs_topic SET reply_count=reply_count+1, last_reply=? WHERE topic_id=?",
                [date('Y-m-d H:i:s'), $topicId]
            );
        }
        return $rid;
    }

    // ── 题目 ──
    public static function problemUserSolved(string $userId, int|string $problemId): bool
    {
        return (bool)\Database::fetchOne(
            "SELECT 1 AS x FROM submissions WHERE user_id=? AND problem_id=? AND status='done' AND score>=100 LIMIT 1",
            [$userId, (string)$problemId]
        );
    }

    // ── 内部辅助 ──
    private static function userExists(string $userId): bool
    {
        return (bool)\Database::fetchOne("SELECT user_id FROM users WHERE user_id=?", [$userId]);
    }

    private static function resolveAchievement(int|string $idOrCode): ?array
    {
        if (is_int($idOrCode) || ctype_digit((string)$idOrCode)) {
            $row = \Database::fetchOne("SELECT * FROM achievements WHERE achievement_id=?", [(int)$idOrCode]);
            if ($row) return $row;
        }
        return \Database::fetchOne("SELECT * FROM achievements WHERE code=?", [(string)$idOrCode]) ?: null;
    }

    private static function resolveCollectible(int|string $id): ?array
    {
        return \Database::fetchOne("SELECT * FROM collectibles WHERE collectible_id=?", [(int)$id]) ?: null;
    }

    private static function messageMatch(string $userId, string $pattern, string $mode, string $field, string $userColumn, ?int $read): bool
    {
        $field = in_array($field, ['subject', 'content'], true) ? $field : 'content';
        $sql = "SELECT subject,content FROM messages WHERE {$userColumn}=?";
        $params = [$userId];
        if ($read !== null) {
            $sql .= " AND is_read=?";
            $params[] = $read;
        }
        $rows = \Database::fetchAll($sql, $params);
        foreach ($rows as $row) {
            $value = (string)($row[$field] ?? '');
            if ($mode === 'regex') {
                $regex = '~' . str_replace('~', '\~', $pattern) . '~u';
                if (@preg_match($regex, $value)) return true;
            } elseif ($mode === 'contains') {
                if ($pattern !== '' && str_contains($value, $pattern)) return true;
            } elseif ($value === $pattern) {
                return true;
            }
        }
        return false;
    }
}

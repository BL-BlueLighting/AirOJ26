# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

AirOJ 是一个在线评测系统（Online Judge）。`frontend/` 是不使用任何框架的 PHP 8 应用（无 Composer、无构建步骤），它直接调用内置的 **QingdaoU JudgeServer**（Flask + gunicorn，监听 12358 端口），后者通过 `libjudger.so`（QingdaoU Judger 的 C 沙箱库）执行评测。数据库默认使用 SQLite（`frontend/data/airoj.db`），可选 MySQL。

所有界面文案与代码注释均使用简体中文，新增内容请保持一致。HTML 由 PHP 直接输出（内联样式 + `frontend/templates/original/stylesheets/style.css` 中的 CSS 变量，暗色科幻风主题）。

## 常用命令

```bash
# 运行应用 —— 文档根目录必须是 frontend/（页面使用 /templates/... 这类绝对路径）
cd frontend && php -S 0.0.0.0:8080

# 首次访问 /install.php 完成安装（写入 frontend/data/installed.lock；
# 在 lock 文件存在之前，include/__init__.php 会把所有页面重定向到安装向导）

# 一键安装评测环境（需要 root）：克隆并编译 Judger、给 JudgeServer 打补丁、
# 创建 compiler/code/spj 用户、建立 ./venv、安装 systemd 服务
bash install.sh

# 启动 JudgeServer（监听 0.0.0.0:12358，日志在 /log/）
sudo ./judge/start_judge_server.sh [start|stop|restart]
sudo systemctl start judge_server        # 装好 install.sh 之后的另一种方式

# 可选 Flask 后端（5000 端口）—— PHP 前端并不会调用它
venv/bin/python3 backend/server.py

# 冒烟测试 JudgeServer
HASH=$(echo -n "$JUDGE_SERVER_TOKEN" | sha256sum | cut -d' ' -f1)
curl -X POST http://localhost:12358/ping -H "X-Judge-Server-Token: $HASH" \
  -H "Content-Type: application/json" -d '{}'

# 命令行设置管理员
cd frontend && php maintain/setadmin.php <username>
```

没有测试套件、linter 和构建步骤。验证改动的方式是启动 PHP 服务（同时保证 JudgeServer 在运行）并实际操作页面；提交评测需要 `inputs_outputs/<problem_id>/` 下存在测试数据。系统依赖（以 Arch 为例）：`cmake redis mariadb libseccomp gcc git`。

## 提交评测流程（核心链路）

`problem.php`（Monaco 编辑器 + 上传表单）→ POST 到 `submit.php` → 调用 `frontend/include/functions.php` 中的 `submitJudge()`：

1. 由 `get_language_configs()` / `build_language_config()` 生成 JudgeServer 的 `language_config`。
2. `load_test_cases()` 读取测试点：`inputs_outputs/<problem_id>/` 下按文件名主干配对的 `.in`/`.out`，未配对的文件会被忽略。题目目录由后台“添加题目”时创建。
3. `INSERT` 一条提交记录（`status='judging'`），随后**同步** POST 到 JudgeServer 的 `/judge`（curl，超时 120 秒），用 `X-Judge-Server-Token: sha256(JUDGE_SERVER_TOKEN)` 鉴权。
4. 把数字结果映射为 `AC/WA/TLE/MLE/RE/SE`（`$result_map`），逐测试点写入 `submissions.result_json`，并累加 `users.solved_count` / `submit_count`。
5. 重定向到 `result.php?id=<提交 ID>` 展示每个测试点的结果。

评测在 PHP 请求内同步完成，慢评测会占住该 PHP 进程；表结构里虽有 `pending`/`judging` 状态，但 PHP 链路实际上不会留下 pending 的提交。

**在线测试**（题目页“测试”面板）走独立接口 `run.php`：接收 JSON `{pid, lang, code, input}`，向 JudgeServer 发一个期望输出为空的测试点并开启 `output: true`，返回 `{ok, verdict, output, cpu_time, real_time, memory, exit_code}`。注意此时 `WA` 只表示输出与空期望不同，`run.php` 会把它归为 `OK`（程序正常跑完），只有 TLE/MLE/RE/SE/编译错误才算异常。该接口不写入 `submissions` 表。

存在**两套互不相同的 token 机制**，注意不要混淆：
- `judge_token = md5(dayhour * JUDGE_SECRET)`（`dayhour = YYYYMMDDHH`，±2 小时有效期）——仅用于可选 Flask 后端自身的 API 鉴权。
- `X-Judge-Server-Token = sha256(JUDGE_SERVER_TOKEN)`——与 JudgeServer 约定的共享密钥，PHP 与 Flask 都用它。

`JUDGE_SECRET` 等常量位于 `frontend/include/config.php`（可用环境变量覆盖）；`backend/config.py` 是语言/判题配置的 Python 镜像。

语言配置有两处环境相关的坑（已在 `get_language_configs()` 中处理）：Go 编译需要 `compile_env` 提供可写的 `GOCACHE`；Go/Java 的运行时与 Java 编译需要 `memory_limit_check_only`（JVM 与 Go runtime 会预留远超限制的虚拟地址空间，按 RSS 检查而不是 `setrlimit(RLIMIT_AS)`）。改动这些字段时 `build_language_config()` 负责把它们拼进 JudgeServer 请求。

## frontend/ 结构与约定

- MVC 结构：根目录 `*.php` 是页面 controller（如 `index.php`），只做 `require include/__init__.php` → `require logics/<page>.php` → `render_page('<page>.php', get_defined_vars())`；`logics/` 放页面业务逻辑；`templates/<theme>/` 放主题模板与 `stylesheets/`、`scripts/`，主题缺少文件时回落到 `templates/original/`。`include/` 放核心 controller/bootstrap（`__init__.php`、`render.php`、`config.php`、`db.php`、`functions.php`、`TOTP.php`、`Parsedown.php`）；`include/header.php` / `footer.php` 仅为后台旧布局兼容 shim。后台 `admin/` 保留原目录，但已改用 `include/`。登录态存于 `$_SESSION['airoj_user']`。
- `include/config.php` —— 数据库、`JUDGE_SERVER_URL`、`JUDGE_SERVER_TOKEN`、`JUDGE_SECRET`、`INPUTS_DIR`（指向仓库根部的 `inputs_outputs/`）。安装器为 MySQL 生成的 `frontend/data/config.local.php`（已 gitignore）由 `db.php` 加载，其中 DB 常量优先。
- `include/db.php` —— `Database` 类：静态单例 PDO（`Database::$instance`）＋ `fetchAll`/`fetchOne`/`exec`/`insert`，全部使用预处理语句（请始终通过这些方法访问数据库）。`initTables()` 每次连接都会执行，其中**同一套表结构写了两遍**（SQLite 与 `initTablesMySQL`）；改表结构时必须同时改两个分支。
- 迁移：`ensureColumns()` 在 `initTables()` 之后运行，用 `PRAGMA table_info` / `SHOW COLUMNS` 对比后对老库执行 `ALTER TABLE ADD COLUMN`（新增字段时在这里登记 `[SQLite 定义, MySQL 定义]` 即可）。新表直接写进 `CREATE TABLE IF NOT EXISTS` 就会自动建好；但**改已有表的字段仍需在 `ensureColumns()` 里补一条**。
- 权限：`privilege` 表（`user_id`、`right_str` ∈ `administrator` / `moderator` / `cheater`），各页面按需查询；`user_role_html()` 负责角色配色。后台入口是 `frontend/admin/index.php`：默认渲染欢迎页（Hello / Welcome Back! / Dear + 管理员名 + 方形功能块），`?panel=...` 进入具体功能；功能分组与面板映射定义在 `admin/_admin_nav.php`，独立管理页（contest/team/settings/problem_add/edit 等）通过 `admin/_layout_top.php` / `_layout_bottom.php` 复用「左侧功能块标题+按钮、右侧内容」布局。`frontend/maintain/` 放 CLI 工具。
- Markdown 使用 `include/Parsedown.php` 且开启安全模式（`setSafeMode(true)`）。
- LaTeX：`include/__init__.php` 全局加载自托管的 KaTeX（`frontend/vendor/katex/`，只带 woff2 字体）与 `templates/original/scripts/math.js`，自动渲染 `.mdui-prose` / `.math-content` 容器内的 `$...$`、`$$...$$`、`\(...\)`、`\[...\]`（`pre`/`code` 内的内容不渲染）。后台题目实时预览在替换 `innerHTML` 后需调用 `window.renderMath(element)`。
- 题目组（多难度）：同一道题可挂多个**难度版本**，每个版本都是一条完整的 `problem` 记录（自己的题面、测试数据、SPJ、难度），共享标题/标签/出题人。`problem.group_id`：0 表示独立题目；主记录 `problem_id == group_id`，其余版本 `group_id = 主记录 ID`。辅助函数在 `include/functions.php`：`problem_group_members()` / `problem_group_main_id()` / `problem_group_ac_stats()` / `problem_sync_group_meta()`。题目页通过 `?id=<主记录>&v=<版本>` 切换，提交使用所选版本的 `problem_id`；题目列表按组聚合显示。
- 题目：`problem` 表带 `tags`（逗号分隔）、`author`、`spj`；难度标签在 `problem_difficulty`（category/level/position，可多选）。后台题目编辑器（`admin/problem_add.php` + `admin/problem_edit.php` + 共用 `admin/_problem_form.php`）支持添加页直接上传测试数据、Markdown 工具栏（`templates/original/scripts/md-toolbar.js`）与右栏服务端实时预览；**启用 SPJ 时必须上传特判程序**（保存为 `inputs_outputs/<pid>/spj.cpp`），`submitJudge()` 会把 `spj_version/spj_src/spj_config/spj_compile_config` 传给 JudgeServer。题号从 1000 的 A+B Problem 开始，新增题目从 1001 起（`seedInitialData()`）。
- 难度与 Rating：`AIR_OJ_DIFFICULTY` 定义 BASIC/ADVANCED/EXPERT/MASTER/Re:MASTER 的等级范围与分值区间，`difficulty_quality()` 计算题目分值，`problem_quality()` 取最高标签；用户颜色分档见 `AIR_OJ_RATING_TIERS`，`user_role_html()` 负责名字配色（管理员可平分，`users.name_split`）；首次 AC 时 `apply_rating_for_ac()` 按公式更新 `users.rating`，越级规则与提示在 `problem_tier_check()`（Re:MASTER 15 级禁止越级提交）。
- 比赛：`contest.php`（列表）→ `contest_detail.php?cid=N`（题面列表，未开始时隐藏题目）→ `contest_rank.php?cid=N`（排名）。`contest_problem` 的 `num` 决定 A/B/C 题号。从比赛入口提交时 `problem.php` 会带 `cid` 隐藏域，`submitJudge()` 把它写进 `submissions.contest_id`；排名只统计 `contest_id` 匹配且在比赛时间窗内的提交，ICPC（`contest_type=0`）按通过题数 + 罚时（首次 AC 用时 + 20×错误次数），OI（`=1`）按每题最高分求和，逻辑在 `contest_rank_data()`。后台管理入口：`admin/contest_list.php`、`admin/contest_edit.php`（题目按顺序填 ID）。
- 外观：管理员在 `admin/settings.php` 配置站点 Logo / Favicon / **站点背景** / **页脚**（`system_config` 的 `site_logo`、`site_favicon`、`site_background`、`site_footer`），读写统一走 `Database::getConfig()` / `Database::setConfig()`（不要用 SQLite 专有的 `INSERT OR REPLACE`）。用户在 `settings.php?tab=background` 可自定义页面背景（`users.background`），未自定义则自动回落到站点背景，见 `resolve_background()` / `background_css()`。
- 主题：`body.theme-original`（默认，变量在 `templates/original/stylesheets/style.css` 的 `:root`）；`body.theme-mdui3`（显示名 **MDUI:Re**）使用官方 **MDUI 3（mdui@2.1.5）** Web Components（`mdui-layout` / `mdui-top-app-bar` / `mdui-navigation-drawer` / `mdui-list-item` 等，官方 `mdui.css` + `mdui.global.js` + Material Icons 位于 `templates/mdui3/stylesheets/` 与 `scripts/`，主色保持黑绿）；`body.theme-mdui_fake`（显示名 **MDUI:Fake**）是 MVC 重构前的 MDUI 仿制外观，沿用 AirOJ 自有 header，只加载官方 MDUI 令牌与 `templates/mdui_fake/stylesheets/theme.css`；`body.theme-bshark` 还原 HUSTOJ `template/bshark` 的白色语义化布局（Semantic UI `semantic.min.css` + `main-light.css` + jQuery/Semantic JS，位于 `templates/bshark/`）。主题模板缺少文件时回落到 `templates/original/`。Markdown 内容容器带 `mdui-prose` 类（只在 MDUI 主题下生效）。当前主题由 `current_theme()` 解析：URL `?theme=` 临时预览 > 登录用户的 `users.theme` > cookie `airoj_theme` > original；`include/__init__.php` 处理 `?theme=` 时用 `set_theme()` 写入数据库/cookie 后重定向。顶栏与 `settings.php?tab=theme` 都可以切换。新增主题需要同时改 `AIR_OJ_THEMES`、`templates/<theme>/stylesheets/theme.css` 和设置页的色板。
- **页脚版权行是强制的**：`templates/<theme>/footer.php` 里的 `&copy; Copyright <年份> BL.BlueLighting & AirOJ DX Team.` 必须始终输出（年份用 `date('Y')` 动态生成），自定义页脚只能追加在其上方，任何改动都不得删除或替换这一行——文件内已有相应注释说明。
- 团队：`team.php`（列表/创建/申请加入）与 `team_manage.php`（所有者编辑头像与短介绍、邀请/踢出/转让/解散、提交认证申请；管理员审核）。`teams` 表带 `avatar`、`verify_name`、`verify_reason`、`verify_status`（none/pending/approved/rejected），**申请认证必须填写认证名称**；全局审核入口是 `admin/team_list.php`。
- 人机验证：Cloudflare Turnstile，在 `admin/settings.php` 填写 Site Key / Secret Key（`system_config` 的 `turnstile_site_key`、`turnstile_secret_key`）后自动在注册页与登录页启用；辅助函数 `turnstile_verify()` / `turnstile_widget()` 在 `include/functions.php`，未配置密钥时不做校验。
- 邮件服务 / 邮箱验证 / 找回密码：后台「系统设置 → 邮件服务」配置 SMTP（`system_config` 的 `smtp_host` / `smtp_port` / `smtp_secure` / `smtp_user` / `smtp_pass` / `smtp_from` / `smtp_from_name` / `smtp_peer_name` / `smtp_verify_peer` / `mail_site_url`；`smtp_peer_name` 用于连接地址与证书域名不同时指定 TLS 证书域名，例如连接 Docker 内的 `stalwart-mail`、证书是 `mail.airoj.cn`），并用 `mail_enabled`、`email_verification_enabled`、`password_reset_enabled` 三个开关控制。SMTP 客户端在 `include/mailer.php`（原生 socket，支持 SSL/STARTTLS/AUTH LOGIN/PLAIN，无 Composer 依赖）。注册时若开启邮箱验证，`users.email_verified=0` 并写入 `email_verify_token`（存 SHA-256 哈希）/`email_verify_expires`，用户访问 `verify_email.php?token=` 后才会解除登录限制；登录页可跳转 `verify_email.php?resend=1` 重发（60 秒冷却）。找回密码走 `lostpassword.php` → `reset_password.php`，令牌存 `password_reset_token`/`password_reset_expires`（1 小时），成功后清空 Hydro 密码并解除强制改密。修改邮箱会重置验证状态并重新发信（`update_user_email()`）。`message_email_notify` 控制收到站内信时自动把副本发到收件人邮箱（`send_message_email()` 在 `include/mailer.php`，由 `logics/messages.php` 在插入后触发；发送失败只记日志，不影响站内信）。已有用户的 `email_verified` 默认 1，不会因为升级被锁；新表字段同时写在 `db.php` 的 SQLite / MySQL `CREATE TABLE` 与 `ensureColumns()` 中。
- 章节：`chapters` 表（名称、Markdown 介绍、背景图/颜色、创建人）+ `chapter_problem`（章节与题目、显示顺序）。前台 `chapters.php` 卡片列表 → `chapter.php?id=N`（封面横幅 + 题目列表）；后台 `admin/chapter_list.php` / `chapter_edit.php`（可上传封面或填写图片地址/颜色，按顺序填写题目 ID）/ `chapter_del.php`，导航分组定义在 `admin/_admin_nav.php`。背景值复用 `is_valid_background()` / `background_css()`。
- 站内信：`messages` 表（`from_user`/`to_user`/`subject`/`content`/`is_read`/`from_deleted`/`to_deleted`），`messages.php` 提供收件箱、发件箱、写信、查看、回复与软删除；内容支持 Markdown；`include/__init__.php` 的信封按钮显示未读数。
- 成就 / 收藏品 / 称号：`achievements` + `user_achievements`（内置「夜猫子」由 `check_time_achievements()` 在 23:59~05:59 在线时自动授予）；`collectibles` + `user_collectibles`（`type` 为 `frame` / `badge` / `bbs_style` / `bubble`，用户在 `settings.php?tab=collectibles` 装备，`user_role_html()` 统一渲染姓名框、称号与徽章，空间不足时优先保留称号）；`title_applications`（用户申请 + 后台 `admin/title_review.php` 审核/直接授予/移除）。后台入口还有 `admin/achievements.php`、`admin/collectibles.php`。评测结果页 `result.php` 为 maimai 风格结算画面（评级 / CLEAR / 正确率 / 判定统计 / 测试点 / DX Rating），角色图由 `system_config.result_character` 配置。
- 修改密码：`settings.php?tab=password` 在输出 header 前处理（原密码 → 若启用 2FA 再校验 TOTP → 更新并 `session_regenerate_id`），成功后 PRG 重定向；`modify.php` 的密码修改同样强制 2FA，避免绕过。
- HydroOJ 账号迁移：`users.hydro_salt` / `hydro_hash` 保存 Hydro 的 PBKDF2-SHA256 密码（100000 次，hex 前 64 位），`verify_login_password()` 同时接受 AirOJ `password_hash` 密码和 Hydro 密码；`users.must_change_password=1` 时 `include/__init__.php` 会把所有页面强制重定向到改密页，改密成功后清空 `hydro_*` 并解禁。导入工具是 CLI 的 `maintain/import_hydro_users.php`（读取 mongoexport 的 JSON）。
- HydroOJ 全量迁移：`maintain/import_hydro_full.php` 读取 mongoexport 的 `user/domain/document/record/message/badge/userBadge/storage` JSON，导入用户资料（bio / 头像 / 背景 / 2FA）、公告、讨论、站内信、勋章、提交与判题结果；`submissions.hydro_record_id` 用于幂等去重；`users.avatar` 支持自定义头像（否则回落到 Gravatar）。
- 个人主页：`profile.php?uid=<用户名>` 可公开查看任意用户，页面结构与本人主页完全一致；他人页面隐藏「修改资料」「管理收藏品」等编辑入口。旧入口 `userinfo.php` 会重定向到 `profile.php?uid=`；排行榜 / 首页 / 比赛排名链接也统一指向这里。`include/__init__.php` 每次请求会从数据库刷新登录用户资料（session 中不保留 password / hydro 哈希）。
- 帮助：`help.php` 包含使用说明、难度分级、Rating 公式与名字颜色规则（参照 HUSTOJ 帮助）。
- 讨论区：帖子在 `bbs_topic` / `bbs_reply`（`news` 表只用于公告）。`bbs.php` 支持 `?pid=N` 只看某题讨论，`topic.php?id=N` 是帖子详情与回复页，作者或管理员可删帖/删回复；`problem.php` 左栏有“💬 讨论”直达。
- 状态页 `status.php` 支持按题目/用户/语言/结果筛选与“我的提交”；题库 `problems.php` 合并了数据库题目与 `inputs_outputs/` 目录，支持关键字搜索、通过率与个人通过标记。
- 题目页是一个双栏工作区（CSS 前缀 `pw-`）：左栏题面、右栏工具栏 + Monaco + 可拖拽的测试面板（`#vsplit` 调左右宽度、`#hsplit` 调测试区高度，偏好存在 localStorage）。`⛶ 全屏` 按钮切换 `body.pw-focus`，此时隐藏站点头部/页脚和测试面板，只留题目与代码提交区；布局偏好存在 cookie `pw_layout` 里，服务端渲染时直接决定 class 以避免闪烁，也支持 `?layout=focus|dual` 临时覆盖。
- `frontend/js/` 是 Monaco 构建产物（未纳入 git，带哈希文件名）：入口设置 `window.monaco` 并注册各语言片段补全，`problem.php` 以 ES module 方式加载 `js/monaco.js`。**精简版 Monaco 不含补全弹窗**，必须是完整构建（`editor.main`）autocomplete 才会生效；构建源码在 `frontend/js-src/`（见其 README）。C/C++ 的补全提供器写在 `problem.php` 的 `registerCppCompletion()` 里，注意 Monaco 会丢弃 label 以 `#` 开头的补全项、且补全范围不能越过当前单词起点。
- `frontend/data/` 是运行时状态：`airoj.db`、`installed.lock`、`uploads/`、`config.local.php`。删除 `installed.lock` 会重新触发安装向导。

## judge/ —— 上游代码，由 install.sh 打补丁

`judge/Judger`（C 语言，编译出 `libjudger.so`）和 `judge/JudgeServer` 是 QingdaoU 的上游仓库，已 gitignore，由 `install.sh` 克隆。`install.sh` 会给它们打补丁：非 root 运行、环境变量配置、`compiler.py` 编译后还原工作目录（不还原会让后续 Python/JS 这类无编译步骤的语言报 `FileNotFoundError`）、`compiler.py` 支持 `memory_limit_check_only`（Java 编译需要）。补丁通过 grep 判断是否已应用，可重复执行；改动这些文件后需要 `sudo systemctl restart judge_server` 才生效。**不要手改这些内置仓库的文件**——重新克隆会丢失改动；需要改行为时应扩展 `install.sh` 的补丁段。`judge/local_config.py` 与 `judge/judge_server.service` 是安装器生成内容的镜像。JudgeServer 的 HTTP 层是一个 catch-all 路由，只接受 `judge`、`ping`、`compile_spj`（见 `judge/JudgeServer/server/server.py`），响应格式为 `{"err": ..., "data": ...}`。

## backend/ —— 可选的并行实现

`backend/server.py` 是独立的 Flask API（自带 SQLite `backend/judge.db`，后台线程判题），同样对接 JudgeServer。PHP 前端**不会**调用它，它只是一套备选 API（接口见 `backend/README.md`）。它的 catch-all 静态路由面向 SPA 前端，与本 PHP 应用并不匹配，可视为仅评委服务。

## 其他目录

- `inputs_outputs/<problem_id>/` —— 测试数据，随应用一起部署（路径取自 `INPUTS_DIR`）。
- `.airojfront/` —— 内置的上游 HUSTOJ 前端，已 gitignore，未接入本 PHP 应用，仅作参考/导入兼容用（后台另有独立的 HydroOJ/HUSTOJ 题目 ZIP 导入功能）。

<?php
/**
 * 管理后台功能分组（admin/index.php 与独立管理页共用）
 * 每个分组对应首页的一个方形功能块。
 */
$admin_groups = [
    'problem' => [
        'title' => '题目', 'desc' => '题库、测试数据与导入', 'icon' => 'fa-clipboard-list',
        'landing' => '/admin/index.php?panel=problem_list',
        'items' => [
            'problem_list'   => ['/admin/index.php?panel=problem_list', '题目列表'],
            'problem_add'    => ['/admin/problem_add.php', '添加题目'],
            'problem_import' => ['/admin/index.php?panel=problem_import', '导入题目'],
        ],
    ],
    'chapter' => [
        'title' => '章节', 'desc' => '章节、封面与题目编排', 'icon' => 'fa-book-open',
        'landing' => '/admin/chapter_list.php',
        'items' => [
            'chapter_list' => ['/admin/chapter_list.php', '章节列表'],
            'chapter_add'  => ['/admin/chapter_edit.php?id=0', '添加章节'],
        ],
    ],
    'user' => [
        'title' => '用户', 'desc' => '账号、角色与批量操作', 'icon' => 'fa-user',
        'landing' => '/admin/index.php?panel=user_list',
        'items' => [
            'user_list'  => ['/admin/index.php?panel=user_list', '用户列表'],
            'user_edit'  => ['/admin/index.php?panel=user_edit', '修改用户'],
            'user_batch' => ['/admin/index.php?panel=user_batch', '批量添加'],
        ],
    ],
    'privilege' => [
        'title' => '权限', 'desc' => '管理员、版主与作弊标记', 'icon' => 'fa-key',
        'landing' => '/admin/index.php?panel=privilege_list',
        'items' => [
            'privilege_list' => ['/admin/index.php?panel=privilege_list', '权限列表'],
            'privilege_add'  => ['/admin/index.php?panel=privilege_add', '添加权限'],
            'privilege_log'  => ['/admin/index.php?panel=privilege_log', '权限日志'],
        ],
    ],
    'contest' => [
        'title' => '比赛', 'desc' => '创建比赛与题目编排', 'icon' => 'fa-flag-checkered',
        'landing' => '/admin/contest_list.php',
        'items' => [
            'contest_list' => ['/admin/contest_list.php', '比赛列表'],
            'contest_add'  => ['/admin/contest_edit.php?id=0', '添加比赛'],
        ],
    ],
    'team' => [
        'title' => '团队', 'desc' => '团队认证与审核', 'icon' => 'fa-users',
        'landing' => '/admin/team_list.php',
        'items' => [
            'team_list' => ['/admin/team_list.php', '团队管理'],
        ],
    ],
    'growth' => [
        'title' => '养成', 'desc' => '成就、收藏品与称号', 'icon' => 'fa-trophy',
        'landing' => '/admin/achievements.php',
        'items' => [
            'achievement_list' => ['/admin/achievements.php', '成就管理'],
            'collectible_list' => ['/admin/collectibles.php', '收藏品管理'],
            'title_review'     => ['/admin/title_review.php', '称号审核'],
        ],
    ],
    'news' => [
        'title' => '公告', 'desc' => '公告与滚动通知', 'icon' => 'fa-bullhorn',
        'landing' => '/admin/index.php?panel=news_list',
        'items' => [
            'news_list'   => ['/admin/index.php?panel=news_list', '公告列表'],
            'news_add'    => ['/admin/index.php?panel=news_add', '添加公告'],
            'news_scroll' => ['/admin/index.php?panel=news_scroll', '滚动公告'],
        ],
    ],
    'system' => [
        'title' => '系统', 'desc' => '运行状态与评测队列', 'icon' => 'fa-bolt',
        'landing' => '/admin/index.php?panel=system_info',
        'items' => [
            'system_info'  => ['/admin/index.php?panel=system_info', '系统信息'],
            'judge_queue'  => ['/admin/index.php?panel=judge_queue', '评测队列'],
            'version_info' => ['/admin/index.php?panel=version_info', '版本信息'],
        ],
    ],
    'appearance' => [
        'title' => '外观', 'desc' => 'Logo / 背景 / 页脚', 'icon' => 'fa-palette',
        'landing' => '/admin/settings.php',
        'items' => [
            'site_settings' => ['/admin/settings.php', '站点外观'],
        ],
    ],
];

/** panel => [分组, 功能项] */
$admin_panel_map = [
    'problem_list'   => ['problem', 'problem_list'],
    'problem_import' => ['problem', 'problem_import'],
    'user_list'      => ['user', 'user_list'],
    'user_edit'      => ['user', 'user_edit'],
    'user_batch'     => ['user', 'user_batch'],
    'privilege_list' => ['privilege', 'privilege_list'],
    'privilege_add'  => ['privilege', 'privilege_add'],
    'privilege_log'  => ['privilege', 'privilege_log'],
    'news_list'      => ['news', 'news_list'],
    'news_add'       => ['news', 'news_add'],
    'news_scroll'    => ['news', 'news_scroll'],
    'system_info'    => ['system', 'system_info'],
    'judge_queue'    => ['system', 'judge_queue'],
    'version_info'   => ['system', 'version_info'],
];

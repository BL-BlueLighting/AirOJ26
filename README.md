# AirOJ ✈

基于 **QingdaoU Judger** 的在线评测系统。  
科幻风格 UI · PHP + SQLite/MySQL · 支持多语言评测 · 团队系统 · 2FA · 完整管理后台

---

## 功能特性

| 模块 | 功能 |
|------|------|
| **评测引擎** | 对接 QingdaoU JudgeServer，支持 C/C++/Python/Java/Go/JavaScript |
| **题目系统** | 创建/编辑/导入题目，支持 Markdown 描述，测试数据上传 |
| **提交评测** | 实时同步评测，显示每个测试点结果（AC/WA/TLE/MLE/RE） |
| **用户系统** | 注册/登录/2FA 二步验证/个人简介（Markdown） |
| **角色系统** | 普通用户 · 版主（黄色） · 管理员（紫色） · 作弊者（💩） |
| **团队系统** | 创建/管理团队，成员邀请，认证徽章 ✅ |
| **题解系统** | 为每道题目编写 Markdown 题解 |
| **管理后台** | 题目/用户/权限/公告/评测队列管理，权限日志 |
| **排行榜** | 按 Solved + Rating 排序，角色颜色标识 |
| **主题切换** | 暗色科幻风 / 亮色主题 |
| **导入支持** | HydroOJ / HUSTOJ 题目 ZIP 批量导入 |
| **多数据库** | 默认 SQLite，支持 MySQL |

## 快速开始

```bash
# 1. 启动 PHP 开发服务器
cd frontend
php -S 0.0.0.0:8080

# 2. 打开浏览器访问 http://localhost:8080
# 3. 按照安装向导配置数据库和管理员
```

## 依赖

- PHP 8.0+（需要 PDO、SQLite3/MySQL、cURL、GD 扩展）
- QingdaoU JudgeServer（运行在 localhost:12358）
- QingdaoU Judger（C 扩展，libjudger.so）

## CLI 工具

```bash
# 设置管理员
php maintain/setadmin.php <用户名>
```

## 目录结构

```
AirOJ/
├── frontend/          ← PHP 前端
│   ├── inc/           ← 配置、数据库、函数库
│   ├── css/           ← 科幻风样式表
│   ├── js/            ← JavaScript
│   ├── admin/         ← 管理后台
│   ├── maintain/      ← CLI 工具
│   └── data/          ← SQLite 数据库、上传文件
├── judge/             ← QingdaoU Judger + JudgeServer
├── backend/           ← Flask 后端 API（可选）
├── inputs_outputs/    ← 测试数据目录
└── .airojfront/       ← HUSTOJ 兼容前端
```

## 角色颜色

| 角色 | 颜色 | 显示 |
|------|------|------|
| 普通用户 | 白色 | `username` |
| 版主 | 黄色 `#eab308` | **username** |
| 管理员 | 紫色 `#a855f7` | **username** |
| 作弊者 | 💩 棕色 `#8B4513` | 💩 username |

## License

Copyright (C) 2026 BL.BlueLighting, AirOJ DX Team

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.

---

**Power by [QingdaoU Judger](https://github.com/QingdaoU/Judger) & [JudgeServer](https://github.com/QingdaoU/JudgeServer)**

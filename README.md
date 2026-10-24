<div align="center">
    <img src="https://airoj.cn/data/uploads/logo.png" />
    <h1>AirOJ</h1>
    <p>The open-source, beautiful and powerful online Judge system.</p>
</div>

基于 **QingdaoU Judger** 的在线评测系统。  
由于本人时间不够，代码均由 AI 编写。
同时经过本人 review 后再 git push。

---

## 功能特性

| 模块 | 功能 |
| ---- | ---- |
| 评测引擎 | QingdaoU Judger |
| 题目系统 | 题目支持 Markdown，支持快速迁移。 |
| 用户系统 | 支持 2FA，团队、邮箱。 |
| 分级系统 | ~~(舞萌？)~~ BASIC, ADVANCED, EXPERT, MASTER, Re: MASTER |
| 团队系统 | 类似于旧 HustOJ 版 AirOJ 的团队系统，邀请、迁移、转让、解散 |
| 题解系统 | Markdown 题解撰写 |
| 管理后台 | 功能丰富的管理后台，权限日志等等 |
| Rating 系统 | 复杂计算公式，多层分级。 |
| 导入支持 | 支持 HydroOJ, HustOJ 导入。 |
| 数据库支持 | 微小系统 SQLite，大型系统 MySQL。 |

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
├── frontend/          ← PHP 前端（MVC）
│   ├── include/       ← 核心 bootstrap / controller / 数据库 / 函数库
│   ├── logics/        ← 页面业务逻辑
│   ├── templates/     ← original / mdui3 (MDUI:Re) / mdui_fake (MDUI:Fake) / bshark 主题模板与样式、脚本
│   ├── css/           ← 共享第三方 CSS（Font Awesome）
│   ├── js/            ← 共享 JavaScript（Monaco 构建产物）
│   ├── admin/         ← 管理后台
│   ├── maintain/      ← CLI 工具
│   └── data/          ← SQLite 数据库、上传文件
├── judge/             ← QingdaoU Judger + JudgeServer
├── backend/           ← Flask 后端 API（可选）
├── inputs_outputs/    ← 测试数据目录
```

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

<?php
// 安装向导独立运行：此时数据库可能还不存在，不加载 include/__init__.php
require_once __DIR__ . '/include/render.php';
require_once __DIR__ . '/logics/install.php';
render_standalone('install.php', get_defined_vars());

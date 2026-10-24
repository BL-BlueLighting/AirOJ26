<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装 — AirOJ</title>
<link rel="stylesheet" href="css/fontawesome/css/all.min.css">
<link rel="stylesheet" href="templates/original/stylesheets/style.css">
<style>
.install-box { max-width: 600px; margin: 60px auto; }
.install-box .card { padding: 32px; }
.install-box h1 { text-align: center; margin-bottom: 24px; font-size: 24px; }
.install-box .step { text-align: center; margin-bottom: 24px; color: var(--text-muted); font-size: 13px; }
.install-box .step span { display: inline-block; width: 28px; height: 28px; line-height: 28px; border-radius: 50%; background: var(--bg-hover); margin: 0 4px; font-weight: 700; }
.install-box .step span.active { background: var(--accent); color: #fff; }
.db-option { display: flex; gap: 16px; margin-bottom: 20px; }
.db-option label { flex: 1; padding: 20px; border: 2px solid var(--border); border-radius: var(--radius-lg); text-align: center; cursor: pointer; transition: all var(--transition); }
.db-option label:hover { border-color: var(--accent); }
.db-option input:checked + .db-label { border-color: var(--accent); background: var(--bg-hover); }
.db-option input { display: none; }
.db-option .db-icon { font-size: 32px; margin-bottom: 8px; }
.db-option .db-name { font-weight: 700; font-size: 16px; }
.db-option .db-desc { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
.db-fields { display: none; }
.db-fields.show { display: block; }
</style>
</head>
<body>

<div class="container">
<div class="install-box">
    <div class="card">
        <h1><i class="fa-solid fa-rocket"></i> AirOJ 安装向导</h1>
        <div class="step">
            <span class="<?= $step>=1?'active':'' ?>">1</span> 配置数据库
            <span style="margin:0 8px"><i class="fa-solid fa-arrow-right"></i></span>
            <span class="<?= $step>=2?'active':'' ?>">2</span> 创建管理员
        </div>

        <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <?php if ($step == 1): ?>
        <form method="post" id="step1">
            <input type="hidden" name="step" value="1">
            <div class="form-group"><label>选择数据库</label></div>
            <div class="db-option">
                <label><input type="radio" name="driver" value="sqlite" checked onchange="toggleDbFields()">
                <div class="db-label"><div class="db-icon"><i class="fa-solid fa-database"></i></div><div class="db-name">SQLite</div><div class="db-desc">无需配置，适合个人/小规模使用</div></div></label>
                <label><input type="radio" name="driver" value="mysql" onchange="toggleDbFields()">
                <div class="db-label"><div class="db-icon"><i class="fa-solid fa-database"></i></div><div class="db-name">MySQL</div><div class="db-desc">高性能，适合生产环境</div></div></label>
            </div>

            <div id="mysql-fields" class="db-fields">
                <div class="form-row">
                    <div class="form-group" style="flex:2"><label>主机地址</label><input type="text" name="host" class="form-input" value="127.0.0.1"></div>
                    <div class="form-group" style="flex:1"><label>端口</label><input type="text" name="port" class="form-input" value="3306"></div>
                </div>
                <div class="form-group"><label>数据库名</label><input type="text" name="dbname" class="form-input" value="airoj"></div>
                <div class="form-row">
                    <div class="form-group"><label>用户名</label><input type="text" name="dbuser" class="form-input" value="root"></div>
                    <div class="form-group"><label>密码</label><input type="password" name="dbpass" class="form-input"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">下一步 <i class="fa-solid fa-arrow-right"></i></button>
        </form>

        <script>
        function toggleDbFields() {
            document.getElementById('mysql-fields').classList.toggle('show', document.querySelector('[name=driver]:checked').value === 'mysql');
        }
        </script>

        <?php elseif ($step == 2): ?>
        <form method="post">
            <input type="hidden" name="step" value="2">
            <p style="text-align:center;color:var(--text-secondary);margin-bottom:20px;font-size:14px"><i class="fa-solid fa-circle-check"></i> 数据库连接成功！请创建管理员账户。</p>
            <div class="form-group"><label>管理员用户名 *</label><input type="text" name="user_id" class="form-input" required autocomplete="username"></div>
            <div class="form-group"><label>密码 *</label><input type="password" name="password" class="form-input" required></div>
            <div class="form-group"><label>邮箱 *</label><input type="email" name="email" class="form-input" required></div>
            <div class="form-group"><label>昵称</label><input type="text" name="nick" class="form-input" placeholder="可选"></div>
            <button type="submit" class="btn btn-primary btn-block btn-lg"><i class="fa-solid fa-rocket"></i> 完成安装</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</div>

</body>
</html>

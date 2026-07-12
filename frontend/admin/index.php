<?php
$title = '管理后台';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: /login.php'); exit; }

$panel = $_GET['panel'] ?? 'dashboard';
$user = $_SESSION['airoj_user'];
require __DIR__ . '/../inc/header.php';
$panels = [
    'dashboard' => '📊 控制台', 'problem_list' => '📋 题目列表', 'problem_add' => '➕ 添加题目',
    'problem_import' => '📥 导入题目', 'user_list' => '👤 用户列表', 'user_edit' => '✏️ 修改用户',
    'user_batch' => '👥 批量添加', 'privilege_list' => '🔑 权限列表', 'privilege_add' => '➕ 添加权限',
    'privilege_log' => '📜 权限日志', 'news_list' => '📢 公告列表', 'news_add' => '➕ 添加公告',
    'news_scroll' => '📰 滚动公告', 'system_info' => 'ℹ️ 系统信息', 'judge_queue' => '⚡ 评测队列',
    'version_info' => '📌 版本信息',
];
?>
<style>
.admin-wrap { display: flex; gap: 0; min-height: calc(100vh - 140px); margin: -24px -16px; }
.admin-sidebar { width: 200px; flex-shrink: 0; background: var(--bg-secondary); border-right: 1px solid var(--border); padding: 12px 0; overflow-y: auto; position: sticky; top: 54px; max-height: calc(100vh - 54px); }
.admin-sidebar .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); padding: 12px 16px 4px; font-weight: 600; }
.admin-sidebar a { display: block; padding: 7px 16px; font-size: 13px; color: var(--text-secondary); text-decoration: none; transition: all .15s; border-left: 3px solid transparent; }
.admin-sidebar a:hover { background: var(--bg-hover); color: var(--text-primary); }
.admin-sidebar a.active { background: var(--bg-hover); color: var(--accent); border-left-color: var(--accent); font-weight: 600; }
.admin-content { flex: 1; padding: 24px; min-width: 0; }
@media (max-width: 768px) { .admin-wrap { flex-direction: column; margin: 0; } .admin-sidebar { width: 100%; position: static; max-height: none; border-right: none; border-bottom: 1px solid var(--border); } }
</style>
<div class="admin-wrap">
<div class="admin-sidebar">
    <div class="section-title">📋 题目</div>
    <a href="?panel=problem_list" class="<?=$panel==='problem_list'?'active':''?>">题目列表</a>
    <a href="?panel=problem_add" class="<?=$panel==='problem_add'?'active':''?>">添加题目</a>
    <a href="?panel=problem_import" class="<?=$panel==='problem_import'?'active':''?>">导入题目</a>

    <div class="section-title">👤 用户</div>
    <a href="?panel=user_list" class="<?=$panel==='user_list'?'active':''?>">用户列表</a>
    <a href="?panel=user_edit" class="<?=$panel==='user_edit'?'active':''?>">修改用户</a>
    <a href="?panel=user_batch" class="<?=$panel==='user_batch'?'active':''?>">批量添加</a>

    <div class="section-title">🔑 权限</div>
    <a href="?panel=privilege_list" class="<?=$panel==='privilege_list'?'active':''?>">权限列表</a>
    <a href="?panel=privilege_add" class="<?=$panel==='privilege_add'?'active':''?>">添加权限</a>
    <a href="?panel=privilege_log" class="<?=$panel==='privilege_log'?'active':''?>">权限日志</a>

    <div class="section-title">📢 公告</div>
    <a href="?panel=news_list" class="<?=$panel==='news_list'?'active':''?>">公告列表</a>
    <a href="?panel=news_add" class="<?=$panel==='news_add'?'active':''?>">添加公告</a>
    <a href="?panel=news_scroll" class="<?=$panel==='news_scroll'?'active':''?>">滚动公告</a>

    <div class="section-title">⚡ 系统</div>
    <a href="?panel=system_info" class="<?=$panel==='system_info'?'active':''?>">系统信息</a>
    <a href="?panel=judge_queue" class="<?=$panel==='judge_queue'?'active':''?>">评测队列</a>
    <a href="?panel=version_info" class="<?=$panel==='version_info'?'active':''?>">版本信息</a>
    <div class="section-title">🎨 外观</div>
    <a href="/admin/settings.php" class="<?=basename($_SERVER['SCRIPT_NAME']??'')==='settings.php'?'active':''?>">Logo / Favicon</a>
</div>
<div class="admin-content">
<?php
$err = ''; $ok = '';

// ===== Panel: Dashboard =====
if ($panel === 'dashboard') {
    $stats = [
        'problems' => Database::fetchOne("SELECT COUNT(*) AS c FROM problem")['c'] ?? 0,
        'users'    => Database::fetchOne("SELECT COUNT(*) AS c FROM users")['c'] ?? 0,
        'subs'     => Database::fetchOne("SELECT COUNT(*) AS c FROM submissions")['c'] ?? 0,
        'teams'    => Database::fetchOne("SELECT COUNT(*) AS c FROM teams")['c'] ?? 0,
        'admins'   => Database::fetchOne("SELECT COUNT(*) AS c FROM privilege WHERE right_str='administrator'")['c'] ?? 0,
    ]; ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px">
        <div class="stat-card"><div class="stat-label">题目</div><div class="stat-value" style="color:var(--accent)"><?=$stats['problems']?></div></div>
        <div class="stat-card"><div class="stat-label">用户</div><div class="stat-value" style="color:var(--green)"><?=$stats['users']?></div></div>
        <div class="stat-card"><div class="stat-label">提交</div><div class="stat-value" style="color:var(--yellow)"><?=$stats['subs']?></div></div>
        <div class="stat-card"><div class="stat-label">团队</div><div class="stat-value" style="color:var(--accent-gold)"><?=$stats['teams']?></div></div>
        <div class="stat-card"><div class="stat-label">管理员</div><div class="stat-value"><?=$stats['admins']?></div></div>
    </div>
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px">
        <p style="color:var(--text-secondary);font-size:14px">欢迎回来，<strong style="color:var(--text-primary)"><?=htmlspecialchars($user['nick']?:$user['user_id'])?></strong>！在左侧菜单中选择功能。</p>
    </div>

<?php
// ===== Panel: Problem List =====
} elseif ($panel === 'problem_list') {
    $problems = Database::fetchAll("SELECT * FROM problem ORDER BY problem_id DESC"); ?>
    <div class="page-header"><h1>题目列表</h1><a href="?panel=problem_add" class="btn btn-primary btn-sm">➕ 添加</a></div>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
    <thead><tr><th>ID</th><th>标题</th><th>数据</th><th>状态</th><th>操作</th></tr></thead>
    <tbody>
    <?php if (!$problems): ?><tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">暂无题目</td></tr>
    <?php else: foreach ($problems as $p):
        $pid = $p['problem_id'];
        $dir = INPUTS_DIR . '/' . $pid;
        $has_data = is_dir($dir) && count(array_filter(scandir($dir)?:[], fn($f)=>str_ends_with($f,'.in'))) > 0;
    ?><tr><td><?=$pid?></td><td><a href="/problem.php?id=<?=$pid?>"><?=htmlspecialchars($p['title'])?></a></td>
    <td><?=$has_data?'<span class="tag tag-green">有</span>':'<span class="tag tag-red">无</span>'?></td>
    <td><?=$p['defunct']==='Y'?'<span class="tag tag-red">隐藏</span>':'<span class="tag tag-blue">正常</span>'?></td>
    <td><a href="problem_edit.php?id=<?=$pid?>" class="btn btn-primary btn-xs">编辑</a></td></tr>
    <?php endforeach; endif; ?>
    </tbody></table></div></div>

<?php
// ===== Panel: Add Problem =====
} elseif ($panel === 'problem_add') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        if ($title) {
            $new_id = Database::insert("INSERT INTO problem (title,description,input,output,sample_input,sample_output,hint,source,spj) VALUES (?,?,?,?,?,?,?,?,?)",
                [$title, $_POST['description']??'', $_POST['input']??'', $_POST['output']??'', $_POST['sample_input']??'', $_POST['sample_output']??'', $_POST['hint']??'', $_POST['source']??'', $_POST['spj']??'0']);
            $dd = INPUTS_DIR . '/' . $new_id; if (!is_dir($dd)) mkdir($dd, 0755, true);
            // Handle file upload
            if (!empty($_FILES['test_data']['name'][0])) {
                foreach ($_FILES['test_data']['tmp_name'] as $i => $tmp) {
                    if ($tmp && $_FILES['test_data']['name'][$i]) move_uploaded_file($tmp, $dd . '/' . basename($_FILES['test_data']['name'][$i]));
                }
            }
            $ok = "题目 #{$new_id} 已创建。测试数据目录: inputs_outputs/{$new_id}/";
        } else $err = '请填写标题';
    } ?>
    <div class="page-header"><h1>添加题目</h1></div>
    <?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <div class="card"><form method="post" enctype="multipart/form-data">
        <div class="form-group"><label>标题 *</label><input type="text" name="title" class="form-input" required></div>
        <div class="form-group"><label>描述</label><textarea name="description" class="form-input" style="min-height:120px"></textarea></div>
        <div class="form-row"><div class="form-group"><label>输入格式</label><textarea name="input" class="form-input" style="min-height:60px"></textarea></div>
        <div class="form-group"><label>输出格式</label><textarea name="output" class="form-input" style="min-height:60px"></textarea></div></div>
        <div class="form-row"><div class="form-group"><label>样例输入</label><textarea name="sample_input" class="form-input" style="min-height:60px"></textarea></div>
        <div class="form-group"><label>样例输出</label><textarea name="sample_output" class="form-input" style="min-height:60px"></textarea></div></div>
        <div class="form-group"><label>提示</label><textarea name="hint" class="form-input"></textarea></div>
        <div class="form-row">
            <div class="form-group"><label>来源</label><input type="text" name="source" class="form-input"></div>
            <div class="form-group"><label>SPJ</label><select name="spj" class="form-input"><option value="0">否</option><option value="1">是</option></select></div>
        </div>
        <div class="form-group"><label>测试数据文件 (.in/.out，可多选)</label><input type="file" name="test_data[]" class="form-input" multiple></div>
        <button type="submit" class="btn btn-primary">创建题目</button>
    </form></div>

<?php
// ===== Panel: Import Problem =====
} elseif ($panel === 'problem_import') {
    $err = ''; $ok = '';

    // 处理文件上传和导入
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
        $file = $_FILES['import_file'];
        $format = $_POST['format'] ?? 'hydrooj';

        try {
            if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('上传失败');
            $tmp = $file['tmp_name'];
            $name = $file['name'];

            // 解压 zip
            $extract_dir = sys_get_temp_dir() . '/airoj_import_' . uniqid();
            mkdir($extract_dir, 0755, true);

            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true) throw new Exception('无法解压 ZIP 文件');
            $zip->extractTo($extract_dir);
            $zip->close();

            $imported = 0;
            $errors = [];

            // 遍历解压目录
            $items = scandir($extract_dir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $item_path = $extract_dir . '/' . $item;
                if (!is_dir($item_path)) continue;

                try {
                    if ($format === 'hydrooj') {
                        import_hydrooj($item_path, $item);
                    } elseif ($format === 'hustoj') {
                        import_hustoj($item_path, $item);
                    }
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "{$item}: " . $e->getMessage();
                }
            }

            // 清理
            array_map('unlink', glob("$extract_dir/*"));
            rmdir($extract_dir);

            if ($imported > 0) $ok = "成功导入 {$imported} 道题目";
            if ($errors) $err = implode("\n", $errors);
        } catch (Exception $e) {
            $err = $e->getMessage();
        }
    }

    require_once __DIR__ . '/../inc/Parsedown.php';
    $pd = new Parsedown();
    ?>
    <div class="page-header"><h1>📥 导入题目</h1></div>

    <?php if ($err): ?><div class="alert alert-error"><?= nl2br(htmlspecialchars($err)) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

    <div class="card">
        <h2>📦 HydroOJ</h2>
        <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">
            HydroOJ 题目格式：ZIP 中包含多个目录，每个目录为一个题目。<br>
            目录内包含 <code>problem.md</code>（题目描述，Markdown 格式）和 <code>testdata/</code>（测试数据）。
        </p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="format" value="hydrooj">
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <input type="file" name="import_file" class="form-input" accept=".zip" required>
                </div>
                <button type="submit" class="btn btn-primary">导入 HydroOJ 题目</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>⚙️ HUSTOJ</h2>
        <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">
            HUSTOJ 题目格式：ZIP 中包含多个目录，每个目录为一个题目。<br>
            目录内包含 <code>problem.txt</code>（标题与描述，UTF-8）和 <code>*.in</code> / <code>*.out</code> 测试数据。
        </p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="format" value="hustoj">
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <input type="file" name="import_file" class="form-input" accept=".zip" required>
                </div>
                <button type="submit" class="btn btn-primary">导入 HUSTOJ 题目</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>📄 从 Markdown 文件导入</h2>
        <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">
            上传 Markdown 文件（.md），将尝试解析为题目描述并创建题目。
        </p>
        <form method="post" enctype="multipart/form-data" action="?panel=problem_add&md=1">
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <input type="file" name="md_file" class="form-input" accept=".md">
                </div>
                <button type="submit" class="btn btn-outline">导入 Markdown</button>
            </div>
        </form>
    </div>

    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px">
        <h2>📖 导入说明</h2>
        <p style="color:var(--text-secondary);font-size:13px;line-height:1.8">
        <strong>HydroOJ 格式要求：</strong><br>
        ZIP 内每个子目录为一个题目。目录结构：<br>
        <code>题目名/problem.md</code> — Markdown 格式的题目描述（可选含 YAML front-matter）<br>
        <code>题目名/testdata/1.in</code> / <code>1.out</code> — 测试数据<br><br>
        <strong>HUSTOJ 格式要求：</strong><br>
        ZIP 内每个子目录为一个题目。目录结构：<br>
        <code>题目名/problem.txt</code> — 标题和描述文本<br>
        <code>题目名/*.in</code> / <code>*.out</code> — 测试数据
        </p>
    </div>

<?php
// ===== 导入函数 =====
function import_hydrooj(string $dir, string $name): void {
    require_once __DIR__ . '/../inc/db.php';
    $input_dir = INPUTS_DIR;

    $title = $name;
    $desc = '';
    $input_desc = '';
    $output_desc = '';
    $sample_in = '';
    $sample_out = '';

    // 读取 problem.md
    $md_file = $dir . '/problem.md';
    if (file_exists($md_file)) {
        $content = file_get_contents($md_file);

        // 解析 YAML front-matter (--- 之间的内容)
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $content, $m)) {
            $yaml = $m[1];
            $content = $m[2];
            // 尝试提取标题
            if (preg_match('/title:\s*["\']?(.*?)["\']?[\r\n]/', $yaml, $tm)) {
                $title = trim($tm[1]);
            }
        }

        // 尝试按 HydroOJ 的 section 分割
        $desc = $content;

        // 提取样例（从 Markdown 中找 ``` 代码块）
        if (preg_match('/```(?:in|input1?)\s*\n(.*?)```/s', $desc, $im)) {
            $sample_in = trim($im[1]);
        }
        if (preg_match('/```(?:out|output1?)\s*\n(.*?)```/s', $desc, $om)) {
            $sample_out = trim($om[1]);
        }
    }

    // 读取 testdata/
    $td = $dir . '/testdata';
    if (!is_dir($td)) $td = $dir;
    $files = scandir($td);
    $in_out = [];
    foreach ($files as $f) {
        if (str_ends_with($f, '.in')) {
            $stem = substr($f, 0, -3);
            $outf = $td . '/' . $stem . '.out';
            $outf2 = $td . '/' . $stem . '.ans';
            $out_file = file_exists($outf) ? $outf : (file_exists($outf2) ? $outf2 : null);
            if ($out_file) {
                $in_out[$stem] = [$td . '/' . $f, $out_file];
            }
        }
    }

    // 写入数据库
    $db = Database::connect();
    $stmt = $db->prepare("INSERT INTO problem (title, description, input, output, sample_input, sample_output) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $desc, $input_desc ?: '', $output_desc ?: '', $sample_in, $sample_out]);
    $pid = $db->lastInsertId();

    // 写入测试数据
    $data_dir = $input_dir . '/' . $pid;
    if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
    foreach ($in_out as $stem => [$in, $out]) {
        copy($in, $data_dir . '/' . $stem . '.in');
        copy($out, $data_dir . '/' . $stem . '.out');
    }

    if (empty($in_out)) throw new Exception("{$name}: 没有找到测试数据");
}

function import_hustoj(string $dir, string $name): void {
    require_once __DIR__ . '/../inc/db.php';
    $input_dir = INPUTS_DIR;

    $title = $name;
    $desc = '';

    // 读取 problem.txt
    $txt_file = $dir . '/problem.txt';
    if (file_exists($txt_file)) {
        $content = file_get_contents($txt_file);
        $lines = explode("\n", $content);
        if (count($lines) > 0) $title = trim($lines[0]);
        if (count($lines) > 1) $desc = implode("\n", array_slice($lines, 1));
    }

    // 读取测试数据
    $files = scandir($dir);
    $in_out = [];
    foreach ($files as $f) {
        if (str_ends_with($f, '.in')) {
            $stem = substr($f, 0, -3);
            $outf = $dir . '/' . $stem . '.out';
            if (file_exists($outf)) {
                $in_out[$stem] = [$dir . '/' . $f, $outf];
            }
        }
    }

    $db = Database::connect();
    $stmt = $db->prepare("INSERT INTO problem (title, description) VALUES (?, ?)");
    $stmt->execute([$title, $desc]);
    $pid = $db->lastInsertId();

    $data_dir = $input_dir . '/' . $pid;
    if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
    foreach ($in_out as $stem => [$in, $out]) {
        copy($in, $data_dir . '/' . $stem . '.in');
        copy($out, $data_dir . '/' . $stem . '.out');
    }

    if (empty($in_out)) throw new Exception("{$name}: 没有找到测试数据");
}

// ===== Panel: User List =====
} elseif ($panel === 'user_list') {
    $users = Database::fetchAll("SELECT u.*,(SELECT COUNT(*) FROM privilege p WHERE p.user_id=u.user_id AND p.right_str='administrator') AS is_admin,(SELECT COUNT(*) FROM privilege p WHERE p.user_id=u.user_id AND p.right_str='moderator') AS is_mod FROM users u ORDER BY u.solved_count DESC"); ?>
    <div class="page-header"><h1>用户列表</h1></div>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
    <thead><tr><th>用户</th><th>昵称</th><th>邮箱</th><th>已解决</th><th>提交</th><th>Rating</th><th>角色</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u):
        if ($u['is_admin']) $role = '<span style="color:#a855f7">管理员</span>';
        elseif ($u['is_mod']) $role = '<span style="color:var(--yellow)">版主</span>';
        else $role = '<span style="color:var(--text-muted)">普通用户</span>';
    ?><tr>
        <td><?=htmlspecialchars($u['user_id'])?></td>
        <td><?=htmlspecialchars($u['nick']??'')?></td>
        <td><?=htmlspecialchars($u['email']??'')?></td>
        <td style="font-weight:600;color:var(--green)"><?=$u['solved_count']?:0?></td>
        <td><?=$u['submit_count']?:0?></td>
        <td style="font-weight:600;color:var(--accent-gold)"><?=round($u['rating']??1500)?></td>
        <td><?=$role?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div></div>

<?php
// ===== Panel: Edit User =====
} elseif ($panel === 'user_edit') {
    $target = $_POST['target_user'] ?? $_GET['uid'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $target) {
        $nick = $_POST['nick'] ?? '';
        $email = $_POST['email'] ?? '';
        $pw = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $reason = $_POST['reason'] ?? '';
        if ($nick) Database::exec("UPDATE users SET nick=? WHERE user_id=?", [$nick, $target]);
        if ($email) Database::exec("UPDATE users SET email=? WHERE user_id=?", [$email, $target]);
        if ($pw) Database::exec("UPDATE users SET password=? WHERE user_id=?", [password_hash($pw, PASSWORD_DEFAULT), $target]);
        if ($role === 'admin') {
            Database::insert("INSERT OR IGNORE INTO privilege (user_id,right_str) VALUES (?,?)", [$target, 'administrator']);
            Database::insert("INSERT INTO privilege_log (user_id,admin_id,action,target,reason) VALUES (?,?,?,?,?)", [$target, $user['user_id'], 'grant', 'administrator', $reason ?: '管理员操作']);
        } elseif ($role === 'mod') {
            Database::insert("INSERT OR IGNORE INTO privilege (user_id,right_str) VALUES (?,?)", [$target, 'moderator']);
            Database::insert("INSERT INTO privilege_log (user_id,admin_id,action,target,reason) VALUES (?,?,?,?,?)", [$target, $user['user_id'], 'grant', 'moderator', $reason ?: '管理员操作']);
        } elseif ($role === 'cheat') {
            Database::insert("INSERT OR IGNORE INTO privilege (user_id,right_str) VALUES (?,?)", [$target, 'cheater']);
            Database::insert("INSERT INTO privilege_log (user_id,admin_id,action,target,reason) VALUES (?,?,?,?,?)", [$target, $user['user_id'], 'mark', 'cheater', $reason ?: '管理员操作']);
        } elseif ($role === 'remove_cheat') {
            Database::exec("DELETE FROM privilege WHERE user_id=? AND right_str='cheater'", [$target]);
            Database::insert("INSERT INTO privilege_log (user_id,admin_id,action,target,reason) VALUES (?,?,?,?,?)", [$target, $user['user_id'], 'unmark', 'cheater', $reason ?: '管理员操作']);
        }
        $ok = "用户 {$target} 已更新";
    }
    $target_user = $target ? Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$target]) : null; ?>
    <div class="page-header"><h1>修改用户</h1></div>
    <?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <div class="card"><form method="post">
        <div class="form-group"><label>用户名</label><input type="text" name="target_user" class="form-input" value="<?=htmlspecialchars($target)?>" <?=$target?'readonly':''?> required></div>
        <?php if ($target_user): ?>
        <div class="form-group"><label>昵称</label><input type="text" name="nick" class="form-input" value="<?=htmlspecialchars($target_user['nick']??'')?>"></div>
        <div class="form-group"><label>邮箱</label><input type="email" name="email" class="form-input" value="<?=htmlspecialchars($target_user['email']??'')?>"></div>
        <div class="form-group"><label>新密码（留空不修改）</label><input type="password" name="password" class="form-input"></div>
        <div class="form-row">
            <div class="form-group"><label>设置角色</label><select name="role" class="form-input"><option value="">— 不变 —</option><option value="admin">管理员</option><option value="mod">版主</option><option value="cheat">标记作弊</option><option value="remove_cheat">取消作弊</option></select></div>
            <div class="form-group"><label>原因（记录到日志）</label><input type="text" name="reason" class="form-input" placeholder="可选"></div>
        </div>
        <button type="submit" class="btn btn-primary">保存</button>
        <?php else: ?>
        <button type="submit" class="btn btn-primary">查询用户</button>
        <?php endif; ?>
    </form></div>

<?php
// ===== Panel: Batch Add Users =====
} elseif ($panel === 'user_batch') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $lines = explode("\n", $_POST['users'] ?? '');
        $added = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;
            $parts = explode(',', $line);
            $uid = trim($parts[0] ?? ''); $pw = trim($parts[1] ?? '123456'); $email = trim($parts[2] ?? $uid.'@airoj.com'); $nick = trim($parts[3] ?? $uid);
            if ($uid && !Database::fetchOne("SELECT user_id FROM users WHERE user_id=?", [$uid])) {
                Database::insert("INSERT INTO users (user_id,password,email,nick) VALUES (?,?,?,?)", [$uid, password_hash($pw, PASSWORD_DEFAULT), $email, $nick]);
                $added++;
            }
        }
        $ok = "成功添加 {$added} 个用户";
    } ?>
    <div class="page-header"><h1>批量添加用户</h1></div>
    <?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <div class="card"><form method="post">
        <div class="form-group"><label>用户列表</label><textarea name="users" class="form-input" style="min-height:200px" placeholder="每行一个用户，格式: 用户名,密码,邮箱,昵称&#10;例如:&#10;user1,pass123,user1@test.com,User One&#10;user2,pass123,user2@test.com,User Two"></textarea></div>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">密码不填默认为 123456，邮箱不填默认为 用户名@airoj.com</p>
        <button type="submit" class="btn btn-primary">批量添加</button>
    </form></div>

<?php
// ===== Panel: Privilege List =====
} elseif ($panel === 'privilege_list') {
    $privs = Database::fetchAll("SELECT p.*,u.nick FROM privilege p LEFT JOIN users u ON p.user_id=u.user_id ORDER BY p.right_str,p.user_id"); ?>
    <div class="page-header"><h1>权限列表</h1><a href="?panel=privilege_add" class="btn btn-primary btn-sm">➕ 添加</a></div>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
    <thead><tr><th>用户</th><th>权限</th><th>操作</th></tr></thead>
    <tbody><?php foreach ($privs as $p): ?>
    <tr><td><?=htmlspecialchars($p['user_id'])?><?=$p['nick']?' ('.htmlspecialchars($p['nick']).')':''?></td>
    <td><span class="tag tag-<?=$p['right_str']==='administrator'?'gold':($p['right_str']==='moderator'?'blue':'green')?>"><?=htmlspecialchars($p['right_str'])?></span></td>
    <td><a href="?panel=privilege_add&del=<?=urlencode($p['user_id'])?>&right=<?=urlencode($p['right_str'])?>" class="btn btn-danger btn-xs" onclick="return confirm('确定移除？')">移除</a></td></tr>
    <?php endforeach; ?></tbody></table></div></div>

<?php
// ===== Panel: Add Privilege =====
} elseif ($panel === 'privilege_add') {
    if (isset($_GET['del'])) {
        Database::exec("DELETE FROM privilege WHERE user_id=? AND right_str=?", [$_GET['del'], $_GET['right'] ?? '']);
        Database::insert("INSERT INTO privilege_log (user_id,admin_id,action,target,reason) VALUES (?,?,?,?,?)", [$_GET['del'], $user['user_id'], 'revoke', $_GET['right'] ?? '', '管理员操作']);
        $ok = '权限已移除';
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = trim($_POST['user_id'] ?? '');
        $right = $_POST['right_str'] ?? 'administrator';
        $reason = $_POST['reason'] ?? '';
        if ($uid && $right) {
            if (!Database::fetchOne("SELECT user_id FROM users WHERE user_id=?", [$uid])) $err = '用户不存在';
            else {
                Database::insert("INSERT OR IGNORE INTO privilege (user_id,right_str) VALUES (?,?)", [$uid, $right]);
                Database::insert("INSERT INTO privilege_log (user_id,admin_id,action,target,reason) VALUES (?,?,?,?,?)", [$uid, $user['user_id'], 'grant', $right, $reason ?: '管理员操作']);
                $ok = "权限已授予 {$uid} → {$right}";
            }
        }
    } ?>
    <div class="page-header"><h1>添加权限</h1></div>
    <?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <div class="card"><form method="post">
        <div class="form-group"><label>用户名</label><input type="text" name="user_id" class="form-input" required></div>
        <div class="form-group"><label>权限</label><select name="right_str" class="form-input"><option value="administrator">administrator（管理员）</option><option value="moderator">moderator（版主）</option><option value="source_browser">source_browser（查看代码）</option></select></div>
        <div class="form-group"><label>原因</label><input type="text" name="reason" class="form-input" placeholder="记录到权限日志"></div>
        <button type="submit" class="btn btn-primary">授予</button>
    </form></div>

<?php
// ===== Panel: Privilege Log =====
} elseif ($panel === 'privilege_log') {
    $logs = Database::fetchAll("SELECT * FROM privilege_log ORDER BY log_id DESC LIMIT 100"); ?>
    <div class="page-header"><h1>权限日志</h1></div>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
    <thead><tr><th>时间</th><th>操作者</th><th>操作</th><th>目标</th><th>对象</th><th>原因</th></tr></thead>
    <tbody><?php if (!$logs): ?><tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">暂无日志</td></tr>
    <?php else: foreach ($logs as $l): ?>
    <tr><td style="font-size:12px"><?=$l['created_at']?></td><td><?=htmlspecialchars($l['admin_id'])?></td>
    <td><span class="tag tag-<?=$l['action']==='grant'?'green':($l['action']==='revoke'?'red':'blue')?>"><?=htmlspecialchars($l['action'])?></span></td>
    <td><?=htmlspecialchars($l['target'])?></td><td><?=htmlspecialchars($l['user_id'])?></td><td style="color:var(--text-secondary);font-size:12px"><?=htmlspecialchars($l['reason']?:'')?></td></tr>
    <?php endforeach; endif; ?>
    </tbody></table></div></div>

<?php
// ===== Panel: News List =====
} elseif ($panel === 'news_list') {
    // 置顶切换
    if (isset($_GET['pin'])) {
        $nid = intval($_GET['pin']);
        $n = Database::fetchOne("SELECT pinned FROM news WHERE news_id=?", [$nid]);
        if ($n) Database::exec("UPDATE news SET pinned=? WHERE news_id=?", [$n['pinned'] ? 0 : 1, $nid]);
    }
    if (isset($_GET['del'])) { Database::exec("DELETE FROM news WHERE news_id=?", [intval($_GET['del'])]); }
    $news = Database::fetchAll("SELECT * FROM news ORDER BY pinned DESC, news_id DESC"); ?>
    <div class="page-header"><h1>公告列表</h1><a href="?panel=news_add" class="btn btn-primary btn-sm">➕ 添加</a></div>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
    <thead><tr><th>ID</th><th>标题</th><th>置顶</th><th>时间</th><th>操作</th></tr></thead>
    <tbody><?php foreach ($news as $n): ?>
    <tr>
        <td><?=$n['news_id']?></td>
        <td><?=htmlspecialchars($n['title'])?></td>
        <td><a href="?panel=news_list&pin=<?=$n['news_id']?>" class="btn btn-xs <?=$n['pinned']?'btn-success':'btn-gray'?>"><?=$n['pinned']?'📌 已置顶':'置顶'?></a></td>
        <td style="font-size:12px;color:var(--text-muted)"><?=$n['time']?></td>
        <td><a href="?panel=news_list&del=<?=$n['news_id']?>" class="btn btn-danger btn-xs" onclick="return confirm('确定删除？')">删除</a></td>
    </tr>
    <?php endforeach; ?></tbody></table></div></div>

<?php
// ===== Panel: Add News =====
} elseif ($panel === 'news_add') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $t = trim($_POST['title'] ?? ''); $c = trim($_POST['content'] ?? '');
        if ($t && $c) { Database::insert("INSERT INTO news (title,content) VALUES (?,?)", [$t, $c]); $ok = '公告已发布'; }
    } ?>
    <div class="page-header"><h1>添加公告</h1></div>
    <?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <div class="card"><form method="post">
        <div class="form-group"><label>标题</label><input type="text" name="title" class="form-input" required></div>
        <div class="form-group">
            <label>内容 <span style="font-weight:400;color:var(--text-muted)">(支持 Markdown)</span></label>
            <div style="margin-bottom:6px;display:flex;gap:6px">
                <button type="button" class="btn btn-xs btn-outline" onclick="togglePreview('content-editor','preview-box')">👁️ 预览</button>
            </div>
            <textarea id="content-editor" name="content" class="form-input" style="min-height:200px" required></textarea>
            <div id="preview-box" style="display:none;min-height:200px;padding:12px;background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);font-size:14px;color:var(--text-secondary);line-height:1.7;overflow-x:auto"></div>
        </div>
        <button type="submit" class="btn btn-primary">发布</button>
    </form></div>
    <script>
    function togglePreview(srcId, previewId) {
        var src = document.getElementById(srcId);
        var preview = document.getElementById(previewId);
        if (preview.style.display === 'none') {
            preview.style.display = 'block';
            src.style.display = 'none';
            // Simple markdown-like preview
            var text = src.value
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/^### (.*$)/gm, '<h3>$1</h3>')
                .replace(/^## (.*$)/gm, '<h2>$1</h2>')
                .replace(/^# (.*$)/gm, '<h1>$1</h1>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/```([\s\S]*?)```/g, '<pre style="background:var(--bg-primary);padding:12px;border-radius:4px;overflow-x:auto"><code>$1</code></pre>')
                .replace(/`(.*?)`/g, '<code style="background:var(--bg-primary);padding:1px 4px;border-radius:3px">$1</code>')
                .replace(/^- (.*$)/gm, '<li>$1</li>')
                .replace(/\n/g, '<br>');
            preview.innerHTML = text || '<span style="color:var(--text-muted)">（空内容）</span>';
        } else {
            preview.style.display = 'none';
            src.style.display = 'block';
        }
    }
    </script>

<?php
// ===== Panel: Scrolling News =====
} elseif ($panel === 'news_scroll') {
    $scroll = Database::fetchOne("SELECT value FROM system_config WHERE key_name='scroll_news'")['value'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $content = $_POST['content'] ?? '';
        if ($content) Database::insert("INSERT OR REPLACE INTO system_config (key_name,value) VALUES ('scroll_news',?)", [$content]);
        else Database::exec("DELETE FROM system_config WHERE key_name='scroll_news'");
        $ok = '滚动公告已更新';
    } ?>
    <div class="page-header"><h1>滚动公告</h1></div>
    <?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <div class="card"><form method="post">
        <div class="form-group"><label>滚动文字</label><textarea name="content" class="form-input" style="min-height:80px" placeholder="留空则关闭滚动公告"><?=htmlspecialchars($scroll)?></textarea></div>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">该文字将在站点顶部滚动显示。</p>
        <button type="submit" class="btn btn-primary">保存</button>
    </form></div>

<?php
// ===== Panel: System Info =====
} elseif ($panel === 'system_info') { ?>
    <div class="page-header"><h1>系统信息</h1></div>
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div class="stat-card"><div class="stat-label">操作系统</div><div class="stat-value" style="font-size:16px"><?=php_uname('s')?> <?=php_uname('r')?></div></div>
        <div class="stat-card"><div class="stat-label">PHP 版本</div><div class="stat-value" style="font-size:16px"><?=PHP_VERSION?></div></div>
        <div class="stat-card"><div class="stat-label">数据库</div><div class="stat-value" style="font-size:16px"><?=DB_DRIVER==='mysql'?'MySQL':'SQLite'?></div></div>
        <div class="stat-card"><div class="stat-label">服务器时间</div><div class="stat-value" style="font-size:16px"><?=date('Y-m-d H:i:s')?></div></div>
        <div class="stat-card"><div class="stat-label">内存使用</div><div class="stat-value" style="font-size:16px"><?=round(memory_get_usage()/1024/1024,2)?>MB</div></div>
        <div class="stat-card"><div class="stat-label">Web服务器</div><div class="stat-value" style="font-size:16px">PHP Built-in</div></div>
    </div>

<?php
// ===== Panel: Judge Queue =====
} elseif ($panel === 'judge_queue') {
    $page = max(1, intval($_GET['jpage'] ?? 1));
    $limit = 30;
    $total = Database::fetchOne("SELECT COUNT(*) AS c FROM submissions")['c'] ?? 0;
    $rows = Database::fetchAll("SELECT * FROM submissions ORDER BY id DESC LIMIT ? OFFSET ?", [$limit, ($page-1)*$limit]);
    $maxP = max(1, ceil($total/$limit)); ?>
    <div class="page-header"><h1>评测队列</h1></div>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
    <thead><tr><th>#</th><th>题目</th><th>用户</th><th>语言</th><th>状态</th><th>分数</th><th>操作</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
    <tr><td><?=$r['id']?></td><td><a href="/problem.php?id=<?=$r['problem_id']?>"><?=$r['problem_id']?></a></td><td><?=htmlspecialchars($r['user_id']?:'')?></td><td><?=$r['judge_lang']?></td>
    <td><span class="badge badge-<?=$r['status']?>"><?=$r['status']?></span></td>
    <td style="font-weight:600;color:<?=($r['score']??0)>=100?'var(--green)':'var(--red)'?>"><?=$r['score']??'-'?></td>
    <td><a href="/result.php?id=<?=$r['id']?>" class="btn btn-primary btn-xs">查看</a></td></tr>
    <?php endforeach; ?></tbody></table></div></div>
    <?php if ($maxP>1):?><div class="pagination"><?php for($i=1;$i<=$maxP;$i++):?><a href="?panel=judge_queue&jpage=<?=$i?>" class="page-link <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor;?></div><?php endif;

// ===== Panel: Version Info =====
} elseif ($panel === 'version_info') {
    $judger_ver = '2.1.1 (QingdaoU)';
    try {
        $h = hash('sha256', JUDGE_SERVER_TOKEN);
        $c = curl_init(rtrim(JUDGE_SERVER_URL,'/').'/ping');
        curl_setopt_array($c, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>'{}', CURLOPT_HTTPHEADER=>["X-Judge-Server-Token: $h", 'Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>5]);
        $rp = curl_exec($c);
        if ($rp) { $d = json_decode($rp, true); if (!empty($d['data']['judger_version'])) $judger_ver = $d['data']['judger_version']; }
        curl_close($c);
    } catch (Exception $e) {} ?>
    <div class="page-header"><h1>版本信息</h1></div>
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div class="stat-card"><div class="stat-label">AirOJ 前端</div><div class="stat-value" style="font-size:16px">v1.0.0</div></div>
        <div class="stat-card"><div class="stat-label">Judger (libjudger.so)</div><div class="stat-value" style="font-size:16px"><?=htmlspecialchars($judger_ver)?></div></div>
        <div class="stat-card"><div class="stat-label">PHP</div><div class="stat-value" style="font-size:16px"><?=PHP_VERSION?></div></div>
        <div class="stat-card"><div class="stat-label">数据库引擎</div><div class="stat-value" style="font-size:16px"><?=DB_DRIVER==='mysql'?'MySQL':'SQLite3'?></div></div>
        <div class="stat-card"><div class="stat-label">后端 API</div><div class="stat-value" style="font-size:16px">Flask (QingdaoU)</div></div>
        <div class="stat-card"><div class="stat-label">JudgeServer URL</div><div class="stat-value" style="font-size:14px"><?=htmlspecialchars(JUDGE_SERVER_URL)?></div></div>
    </div>
<?php } ?>

</div></div>
<?php require __DIR__ . '/../inc/footer.php'; ?>

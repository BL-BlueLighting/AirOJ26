<?php
$title = 'privilege_add';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$uid = $_GET['uid'] ?? $_POST['user_id'] ?? '';
$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = trim($_POST['user_id'] ?? '');
    $right = trim($_POST['right_str'] ?? 'administrator');
    if ($uid && $right) {
        $user = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uid]);
        if (!$user) $err = '用户不存在';
        else {
            Database::insert("INSERT OR IGNORE INTO privilege (user_id, right_str) VALUES (?, ?)", [$uid, $right]);
            $ok = "权限已授予 $uid → $right";
        }
    }
}
require __DIR__ . '/../inc/header.php'; ?>
<div class="page-header"><h1>🔑 添加权限</h1><a href="privilege_list.php" class="btn btn-outline btn-sm">← 返回</a></div>
<div class="card" style="max-width:500px">
    <?php if ($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>用户名</label><input type="text" name="user_id" class="form-input" value="<?=htmlspecialchars($uid)?>" required></div>
        <div class="form-group"><label>权限</label><select name="right_str" class="form-input"><option value="administrator">administrator（管理员）</option><option value="source_browser">source_browser（查看代码）</option><option value="contest_creator">contest_creator（创建比赛）</option></select></div>
        <button type="submit" class="btn btn-primary">授予权限</button>
    </form>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>


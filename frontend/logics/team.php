<?php
$title = '团队';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
$user = $_SESSION['airoj_user'] ?? null;
$err = ''; $ok = '';

// 创建团队
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$user) { header('Location: /login.php'); exit; }
    $uid = $user['user_id'];

    if ($_POST['action'] === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $err = '请填写团队名称';
        } elseif (Database::fetchOne("SELECT team_id FROM teams WHERE name=?", [$name])) {
            $err = '团队名称已存在';
        } else {
            $tid = Database::insert("INSERT INTO teams (name,description,owner) VALUES (?,?,?)", [$name, $desc, $uid]);
            Database::insert("INSERT INTO team_members (team_id,user_id,role,status) VALUES (?,?,'owner','approved')", [$tid, $uid]);
            $ok = "团队「{$name}」已创建，可进入管理页上传头像与申请认证。";
        }
    } elseif ($_POST['action'] === 'apply' && isset($_POST['team_id'])) {
        $tid = intval($_POST['team_id']);
        $reason = trim($_POST['reason'] ?? '');
        $existing = Database::fetchOne("SELECT * FROM team_members WHERE team_id=? AND user_id=?", [$tid, $uid]);
        $applied = Database::fetchOne("SELECT * FROM team_applications WHERE team_id=? AND user_id=? AND status='pending'", [$tid, $uid]);
        if (!$existing && !$applied) {
            Database::insert("INSERT INTO team_applications (team_id,user_id,reason) VALUES (?,?,?)", [$tid, $uid, $reason]);
            $ok = '申请已提交';
        } else $err = '你已经是成员或已有待处理的申请';
    }
}

$teams = Database::fetchAll("SELECT t.*,(SELECT COUNT(*) FROM team_members WHERE team_id=t.team_id) AS member_count FROM teams t ORDER BY t.verified DESC, t.team_id DESC");
$my_teams = $user ? Database::fetchAll("SELECT t.*,tm.role FROM teams t JOIN team_members tm ON t.team_id=tm.team_id WHERE tm.user_id=? AND tm.status='approved'", [$user['user_id']]) : [];

// 团队徽标：头像优先，否则用首字母
function team_badge(array $t, int $size = 38): string
{
    $name = htmlspecialchars($t['name'] ?? '');
    if (!empty($t['avatar'])) {
        return '<img src="/' . htmlspecialchars($t['avatar']) . '" alt="" style="width:' . $size . 'px;height:' . $size . 'px;border-radius:'.($size/4).'px;object-fit:cover;flex-shrink:0">';
    }
    $ch = mb_substr($t['name'] ?: '?', 0, 1);
    return '<span style="width:' . $size . 'px;height:' . $size . 'px;border-radius:'.($size/4).'px;display:inline-flex;align-items:center;justify-content:center;background:var(--accent-bg);color:var(--accent);font-weight:700;flex-shrink:0">' . htmlspecialchars($ch) . '</span>';
}

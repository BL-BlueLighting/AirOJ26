<?php
$title = '管理团队';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
if (!isset($_SESSION['airoj_user'])) { header('Location: /login.php'); exit; }
$uid = $_SESSION['airoj_user']['user_id'];
$tid = intval($_GET['id'] ?? 0);
$team = Database::fetchOne("SELECT * FROM teams WHERE team_id=?", [$tid]);
if (!$team) { header('Location: /team.php'); exit; }

$member = Database::fetchOne("SELECT * FROM team_members WHERE team_id=? AND user_id=? AND status='approved'", [$tid, $uid]);
$is_owner = $member && $member['role'] === 'owner';
$is_admin = (bool)Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$uid]);
$err = ''; $ok = '';

/** 保存团队头像 */
function save_team_avatar(array $file, int $tid): array
{
    if (empty($file['name'])) return [false, '没有选择文件'];
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return [false, '上传失败'];
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) return [false, '头像不能超过 2MB'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) return [false, '仅支持 PNG/JPG/GIF/WEBP'];

    $dir = __DIR__ . '/../data/uploads/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    foreach (glob($dir . 'team-' . $tid . '.*') ?: [] as $old) @unlink($old);
    $name = 'team-' . $tid . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return [false, '头像保存失败'];

    Database::exec("UPDATE teams SET avatar=? WHERE team_id=?", ['data/uploads/' . $name, $tid]);
    return [true, ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target = trim($_POST['target'] ?? '');

    if ($action === 'edit' && $is_owner) {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') $err = '团队名称不能为空';
        else {
            Database::exec("UPDATE teams SET name=?, description=? WHERE team_id=?", [$name, trim($_POST['description'] ?? ''), $tid]);
            $ok = '团队信息已更新';
        }
        if (!empty($_FILES['avatar']['name'])) {
            [$aok, $aerr] = save_team_avatar($_FILES['avatar'], $tid);
            if (!$aok) $err = $aerr; else $ok = '团队信息与头像已更新';
        }
    }
    if ($action === 'invite' && $is_owner) {
        if ($target === '') $err = '请输入用户名';
        elseif (!Database::fetchOne("SELECT user_id FROM users WHERE user_id=?", [$target])) $err = '用户不存在';
        elseif (Database::fetchOne("SELECT * FROM team_members WHERE team_id=? AND user_id=?", [$tid, $target])) $err = '该用户已是成员';
        else {
            Database::insert("INSERT INTO team_members (team_id,user_id,role,status) VALUES (?,?,'member','approved')", [$tid, $target]);
            $ok = "已邀请 {$target} 加入";
        }
    }
    if ($action === 'kick' && $is_owner) {
        if ($target === $uid) $err = '不能移除自己';
        else {
            Database::exec("DELETE FROM team_members WHERE team_id=? AND user_id=?", [$tid, $target]);
            $ok = "已移除成员 {$target}";
        }
    }
    if ($action === 'transfer' && $is_owner) {
        $tm = Database::fetchOne("SELECT * FROM team_members WHERE team_id=? AND user_id=?", [$tid, $target]);
        if (!$tm) $err = '该用户不是团队成员';
        else {
            Database::exec("UPDATE team_members SET role='member' WHERE team_id=? AND user_id=?", [$tid, $uid]);
            Database::exec("UPDATE team_members SET role='owner' WHERE team_id=? AND user_id=?", [$tid, $target]);
            Database::exec("UPDATE teams SET owner=? WHERE team_id=?", [$target, $tid]);
            header('Location: /team.php?transferred=1'); exit;
        }
    }
    if ($action === 'delete' && ($is_owner || $is_admin)) {
        Database::exec("DELETE FROM team_members WHERE team_id=?", [$tid]);
        Database::exec("DELETE FROM team_applications WHERE team_id=?", [$tid]);
        Database::exec("DELETE FROM teams WHERE team_id=?", [$tid]);
        header('Location: /team.php?deleted=1'); exit;
    }
    if ($action === 'apply_verify' && $is_owner) {
        $verify_name = trim($_POST['verify_name'] ?? '');
        $reason = trim($_POST['verify_reason'] ?? '');
        if ($verify_name === '') $err = '请填写认证名称';
        elseif ($reason === '') $err = '请填写申请理由';
        else {
            Database::exec("UPDATE teams SET verify_name=?, verify_reason=?, verify_status='pending' WHERE team_id=?",
                [$verify_name, $reason, $tid]);
            $ok = '认证申请已提交，等待管理员审核';
        }
    }
    if ($action === 'approve' && ($is_owner || $is_admin)) {
        if (!Database::fetchOne("SELECT * FROM team_members WHERE team_id=? AND user_id=?", [$tid, $target])) {
            Database::insert("INSERT INTO team_members (team_id,user_id,role,status) VALUES (?,?,'member','approved')", [$tid, $target]);
        }
        Database::exec("UPDATE team_applications SET status='approved' WHERE team_id=? AND user_id=?", [$tid, $target]);
        $ok = "已通过 {$target} 的申请";
    }
    if ($action === 'reject' && ($is_owner || $is_admin)) {
        Database::exec("UPDATE team_applications SET status='rejected' WHERE team_id=? AND user_id=?", [$tid, $target]);
        $ok = "已拒绝 {$target} 的申请";
    }
    if ($action === 'verify' && $is_admin) {
        if (trim((string)($team['verify_name'] ?? '')) === '') {
            Database::exec("UPDATE teams SET verify_name=? WHERE team_id=?", [$team['name'], $tid]);
        }
        Database::exec("UPDATE teams SET verified=1, verify_status='approved' WHERE team_id=?", [$tid]);
        $ok = '团队已认证';
    }
    if ($action === 'reject_verify' && $is_admin) {
        Database::exec("UPDATE teams SET verify_status='rejected' WHERE team_id=?", [$tid]);
        $ok = '已驳回认证申请';
    }
    if ($action === 'unverify' && $is_admin) {
        Database::exec("UPDATE teams SET verified=0, verify_status='none' WHERE team_id=?", [$tid]);
        $ok = '已取消认证';
    }
}

// 重新读取团队（头像/认证状态可能已更新）
$team = Database::fetchOne("SELECT * FROM teams WHERE team_id=?", [$tid]);
$members = Database::fetchAll("SELECT * FROM team_members WHERE team_id=? AND status='approved'", [$tid]);
$apps = Database::fetchAll("SELECT * FROM team_applications WHERE team_id=? AND status='pending'", [$tid]);

$verify_status = $team['verify_status'] ?? 'none';
$status_text = ['none' => '未申请', 'pending' => '审核中', 'approved' => '已认证', 'rejected' => '已驳回'][$verify_status] ?? '未申请';

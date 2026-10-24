<?php
// 用户信息统一使用个人主页布局
if (session_status() === PHP_SESSION_NONE) @session_start();
$uid = trim((string)($_GET['uid'] ?? ($_SESSION['airoj_user']['user_id'] ?? '')));
if ($uid !== '') {
    header('Location: /profile.php?uid=' . urlencode($uid));
    exit;
}
header('Location: /profile.php');

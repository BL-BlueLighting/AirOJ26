<?php
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();

// Hydro 迁移账号必须先修改密码
if (!empty($_SESSION['airoj_user']['must_change_password'])) {
    header('Location: /settings.php?tab=password&force=1');
    exit;
}

$pid = $_POST['pid'] ?? '';
$lang = $_POST['language'] ?? '';
$judgeType = $_POST['judge_type'] ?? 'standard';
$source = $_POST['source'] ?? '';
$cid = intval($_POST['cid'] ?? 0);

// 上传文件模式：从上传的文件读取源码
if (empty($source) && isset($_FILES['source_file']) && $_FILES['source_file']['error'] === UPLOAD_ERR_OK) {
    $uploaded = $_FILES['source_file'];
    if ($uploaded['size'] > 64 * 1024) {
        header('Location: /problem.php?id=' . urlencode($pid) . '&err=' . urlencode('文件过大（上限 64KB）'));
        exit;
    }
    $source = file_get_contents($uploaded['tmp_name']);
    if ($source === false) {
        header('Location: /problem.php?id=' . urlencode($pid) . '&err=' . urlencode('读取文件失败'));
        exit;
    }
}

if (empty($source) || empty($lang)) {
    header('Location: /problem.php?id=' . urlencode($pid) . '&err=' . urlencode('请选择语言并填写代码'));
    exit;
}
$langs = get_language_configs();
if (!isset($langs[$lang])) {
    header('Location: /problem.php?id=' . urlencode($pid) . '&err=' . urlencode('不支持的语言'));
    exit;
}

// 越级限制（Re:MASTER 15 级不允许越级作答）
$tierInfo = problem_tier_check($pid, $_SESSION['airoj_user'] ?? null);
if ($tierInfo['blocked']) {
    header('Location: /problem.php?id=' . urlencode($pid) . '&err=' . urlencode($tierInfo['message']));
    exit;
}

$result = submitJudge('', $judgeType, $source, $lang, $pid, $cid);

$sid = $result['judge_commitid'] ?? 0;
header('Location: /result.php?id=' . $sid);

<?php
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';
if (session_status() === PHP_SESSION_NONE) @session_start();

$pid = $_POST['pid'] ?? '';
$lang = $_POST['language'] ?? '';
$judgeType = $_POST['judge_type'] ?? 'standard';
$source = $_POST['source'] ?? '';

if (empty($source) || empty($lang)) {
    header('Location: /problem.php?id=' . urlencode($pid) . '&err=' . urlencode('请选择语言并填写代码'));
    exit;
}
$langs = get_language_configs();
if (!isset($langs[$lang])) {
    header('Location: /problem.php?id=' . urlencode($pid) . '&err=' . urlencode('不支持的语言'));
    exit;
}

$result = submitJudge('', $judgeType, $source, $lang, $pid);

$sid = $result['judge_commitid'] ?? 0;
header('Location: /result.php?id=' . $sid);

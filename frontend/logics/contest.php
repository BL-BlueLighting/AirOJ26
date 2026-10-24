<?php
$title = '比赛列表';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';

$contests = Database::fetchAll("SELECT * FROM contest WHERE defunct<>'Y' ORDER BY contest_id DESC LIMIT 100");

// 各比赛题目数
$counts = [];
foreach (Database::fetchAll("SELECT contest_id, COUNT(*) AS c FROM contest_problem GROUP BY contest_id") as $r) {
    $counts[(int)$r['contest_id']] = (int)$r['c'];
}

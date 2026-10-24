<?php
$title = '比赛排名';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';

$cid = intval($_GET['cid'] ?? 0);
$contest = Database::fetchOne("SELECT * FROM contest WHERE contest_id=?", [$cid]);
if (!$contest) render_error('比赛不存在');

$data     = contest_rank_data($contest);
$problems = $data['problems'];
$rows     = $data['rows'];
$stat     = $data['stat'];
$isOI     = $data['type'] === 1;
$status   = contest_status($contest);

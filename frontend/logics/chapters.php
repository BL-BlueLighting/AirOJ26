<?php
/**
 * AirOJ — 章节列表
 */
$title = '章节';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/Parsedown.php';

$chapters = Database::fetchAll(
    "SELECT c.*, (SELECT COUNT(*) FROM chapter_problem cp WHERE cp.chapter_id=c.chapter_id) AS problem_count
     FROM chapters c ORDER BY c.chapter_id ASC"
);
$pd = new Parsedown();
$pd->setSafeMode(true);

<?php
$title = '排行榜';
require_once 'include/config.php';
require_once 'include/db.php';

require_once 'include/functions.php';
$users = Database::fetchAll("SELECT u.user_id,u.nick,u.solved_count,u.submit_count,u.rating FROM users u ORDER BY u.rating DESC, u.solved_count DESC, u.submit_count ASC LIMIT 100");

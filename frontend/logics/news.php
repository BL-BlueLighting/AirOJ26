<?php
/**
 * AirOJ — 公告
 *
 * 不带 id：列出全部公告（HUSTOJ 风格，标题 + 日期）
 * 带 id  ：显示单条公告详情
 */
$title = '公告';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/Parsedown.php';

$pd = new Parsedown();
$pd->setSafeMode(true);

$id = intval($_GET['id'] ?? 0);
$news_list = [];
$news = null;
$prev = null;
$next = null;

if (!$id) {
    $news_list = Database::fetchAll("SELECT * FROM news ORDER BY pinned DESC, news_id DESC");
} else {
    $news = Database::fetchOne("SELECT * FROM news WHERE news_id=?", [$id]);
    if ($news) {
        $prev = Database::fetchOne("SELECT news_id, title FROM news WHERE news_id < ? ORDER BY news_id DESC LIMIT 1", [$id]);
        $next = Database::fetchOne("SELECT news_id, title FROM news WHERE news_id > ? ORDER BY news_id ASC LIMIT 1", [$id]);
    }
}

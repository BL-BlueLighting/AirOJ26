<?php
$title = '排行榜';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';

require_once 'inc/functions.php';
$users = Database::fetchAll("SELECT u.user_id,u.nick,u.solved_count,u.submit_count,u.rating FROM users u ORDER BY u.solved_count DESC, u.submit_count ASC, u.rating DESC LIMIT 100");
?>
<div class="page-header"><h1>🏆 排行榜</h1></div>
<div class="card" style="padding:0">
<div class="table-wrap">
<table>
<thead><tr><th style="width:40px">#</th><th>用户</th><th>昵称</th><th style="width:70px">已解决</th><th style="width:70px">提交</th><th style="width:70px">通过率</th><th style="width:70px">Rating</th></tr></thead>
<tbody>
<?php if (!$users): ?><tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">暂无用户</td></tr>
<?php else: $r=1; foreach ($users as $u): $ratio = ($u['submit_count']??0)>0 ? round(($u['solved_count']??0)/$u['submit_count']*100,1) : 0; ?>
<tr <?=$r<=3?'style="background:var(--bg-hover)"':''?>>
<td style="font-weight:700;color:var(--accent)"><?=$r++?></td>
<td><a href="/userinfo.php?uid=<?=urlencode($u['user_id'])?>"><?=user_role_html($u['user_id'])?></a></td>
<td><?=user_role_html($u['user_id'], $u['nick']?:$u['user_id'])?></td>
<td style="font-weight:700;color:var(--green)"><?=$u['solved_count']??0?></td>
<td><?=$u['submit_count']??0?></td>
<td><?=$ratio?>%</td>
<td style="font-weight:600;color:var(--accent-gold)"><?=round($u['rating']??1500)?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>
<?php require 'inc/footer.php'; ?>

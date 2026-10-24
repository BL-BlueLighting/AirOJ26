<div class="page-header">
    <h1><i class="fa-solid fa-comments"></i> 讨论区<?= $pid ? ' — ' . htmlspecialchars($problem['title'] ?? ('Problem ' . $pid)) : '' ?></h1>
    <div>
        <?php if ($pid): ?><a href="bbs.php" class="btn btn-outline btn-sm">全部讨论</a><?php endif; ?>
        <?php if ($user): ?>
        <a href="newpost.php<?= $pid ? '?pid=' . $pid : '' ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen-to-square"></i> 发帖</a>
        <?php else: ?>
        <a href="login.php" class="btn btn-primary btn-sm">登录后发帖</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($topics): ?>
<div class="card" style="padding:0"><div class="table-wrap"><table>
<thead><tr>
    <th style="width:64px">回复</th>
    <th>标题</th>
    <?php if (!$pid): ?><th style="width:170px">题目</th><?php endif; ?>
    <th style="width:150px">作者</th>
    <th style="width:170px">最后回复</th>
</tr></thead>
<tbody>
<?php foreach ($topics as $t): ?>
<tr>
<td style="text-align:center;font-weight:700;color:var(--accent)"><?= (int)$t['reply_count'] ?></td>
<td>
    <a href="topic.php?id=<?=$t['topic_id']?>"><?=htmlspecialchars($t['title'])?></a>
    <div style="font-size:11px;color:var(--text-muted)"><?=htmlspecialchars(mb_substr((string)$t['content'], 0, 60))?></div>
</td>
<?php if (!$pid): ?>
<td><?php if ($t['problem_id']): ?><a href="problem.php?id=<?=$t['problem_id']?>">#<?=$t['problem_id']?> <?=htmlspecialchars($t['problem_title'] ?? '')?></a><?php else: ?><span style="color:var(--text-muted)">综合</span><?php endif; ?></td>
<?php endif; ?>
<td><?=user_role_html($t['user_id'])?></td>
<td style="color:var(--text-muted);font-size:12px"><?=htmlspecialchars($t['last_reply'] ?: $t['created_at'])?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php else: ?>
<div class="empty"><div class="icon"><i class="fa-solid fa-comments"></i></div><p>暂无帖子，来发第一帖吧！</p></div>
<?php endif; ?>

<?php if ($maxP > 1): ?><div class="pagination"><?php for ($i = 1; $i <= $maxP; $i++): ?><a href="?<?= $pid ? 'pid=' . $pid . '&' : '' ?>page=<?=$i?>" class="page-link <?=$i === $page ? 'active' : ''?>"><?=$i?></a><?php endfor; ?></div><?php endif; ?>

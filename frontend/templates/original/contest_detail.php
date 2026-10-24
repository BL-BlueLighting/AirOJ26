<style>
.contest-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 20px; }
.contest-tabs a { padding: 10px 20px; font-size: 14px; font-weight: 600; color: var(--text-secondary); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.contest-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
</style>

<div class="page-header">
    <h1><i class="fa-solid fa-flag-checkered"></i> <?= htmlspecialchars($contest['title']) ?></h1>
    <div>
        <a href="contest.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 比赛列表</a>
        <a href="contest_rank.php?cid=<?=$cid?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-trophy"></i> 排名</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">状态</div><div class="stat-value" style="font-size:20px;color:<?=contest_status_color($status)?>"><?=$status?></div></div>
    <div class="stat-card"><div class="stat-label">赛制</div><div class="stat-value" style="font-size:20px"><?= ((int)($contest['contest_type'] ?? 0) === 1) ? 'OI' : 'ICPC' ?></div></div>
    <div class="stat-card"><div class="stat-label">开始时间</div><div class="stat-value" style="font-size:14px"><?=$contest['start_time']?></div></div>
    <div class="stat-card"><div class="stat-label">结束时间</div><div class="stat-value" style="font-size:14px"><?=$contest['end_time']?></div></div>
    <div class="stat-card"><div class="stat-label">题目数</div><div class="stat-value"><?=count($problems)?></div></div>
</div>

<?php if (!empty($contest['description'])): ?>
<div class="card">
    <h2><i class="fa-solid fa-file-lines"></i> 比赛说明</h2>
    <div class="mdui-prose" style="font-size:14px;line-height:1.7"><?php
        require_once 'include/Parsedown.php';
        $pd = new Parsedown();
        $pd->setSafeMode(true);
        echo $pd->text($contest['description']);
    ?></div>
</div>
<?php endif; ?>

<div class="card" style="padding:0">
    <div style="padding:16px 20px 8px;font-weight:700;font-size:14px"><i class="fa-solid fa-clipboard-list"></i> 题目列表</div>
    <?php if ($status === '未开始'): ?>
        <div class="empty"><div class="icon"><i class="fa-solid fa-hourglass-half"></i></div><p>比赛尚未开始，题目将在 <?=htmlspecialchars($contest['start_time'])?> 公布</p></div>
    <?php elseif (!$problems): ?>
        <div class="empty"><p>本场比赛还没有添加题目</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
    <thead><tr><th style="width:60px">题号</th><th>题目</th><th style="width:160px">难度</th><th style="width:110px">通过 / 提交</th><th style="width:100px">我的状态</th><th style="width:100px">操作</th></tr></thead>
    <tbody>
    <?php foreach ($problems as $i => $p):
        $pid = (int)$p['problem_id'];
        $letter = chr(65 + ($i % 26));
        $t = $tries[$pid] ?? null;
        $m = $my[$pid] ?? null;
        $dl = problem_difficulties((string)$pid);
        $diffClass = ['BASIC' => 'basic', 'ADVANCED' => 'advanced', 'EXPERT' => 'expert', 'MASTER' => 'master', 'Re:MASTER' => 'remaster'];
    ?>
    <tr>
        <td style="font-weight:700;color:var(--accent)"><?=$letter?></td>
        <td><a href="problem.php?id=<?=$pid?>&cid=<?=$cid?>"><?=htmlspecialchars($p['title'] ?? ('Problem ' . $pid))?></a></td>
        <td><?php foreach ($dl as $d): ?><span class="diff-tag diff-<?= $diffClass[$d['category']] ?? 'basic' ?>"><?= htmlspecialchars(difficulty_label($d['category'], (int)$d['level'], $d['position'])) ?></span><?php endforeach; ?></td>
        <td><?= (int)($t['ac'] ?? 0) ?> / <?= (int)($t['total'] ?? 0) ?></td>
        <td>
            <?php if ($m && (int)$m['ac'] === 1): ?><span class="tag tag-green"><i class="fa-solid fa-circle-check"></i> 已通过</span>
            <?php elseif ($m): ?><span class="tag tag-gold">得分 <?= (float)$m['best'] ?></span>
            <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
        </td>
        <td><a href="problem.php?id=<?=$pid?>&cid=<?=$cid?>" class="btn btn-primary btn-xs">去做题</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>


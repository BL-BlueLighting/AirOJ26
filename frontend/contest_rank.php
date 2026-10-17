<?php
$title = '比赛排名';
require 'inc/header.php';
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/functions.php';

$cid = intval($_GET['cid'] ?? 0);
$contest = Database::fetchOne("SELECT * FROM contest WHERE contest_id=?", [$cid]);
if (!$contest) { echo '<div class="empty"><p>比赛不存在</p></div>'; require 'inc/footer.php'; exit; }

$data     = contest_rank_data($contest);
$problems = $data['problems'];
$rows     = $data['rows'];
$stat     = $data['stat'];
$isOI     = $data['type'] === 1;
$status   = contest_status($contest);
?>
<style>
.rank-table td.cell { text-align: center; white-space: nowrap; font-family: "JetBrains Mono", monospace; }
.rank-table th.prob { text-align: center; }
.rank-table td.me { background: rgba(77,166,255,.08); }
</style>

<div class="page-header">
    <h1><i class="fa-solid fa-trophy"></i> <?= htmlspecialchars($contest['title']) ?> — 排名</h1>
    <div>
        <a href="contest_detail.php?cid=<?=$cid?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> 比赛首页</a>
        <a href="contest_rank.php?cid=<?=$cid?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-rotate"></i> 刷新</a>
    </div>
</div>

<div class="card" style="padding:12px 20px">
    <span style="color:var(--text-secondary);font-size:13px">
        开始: <?=$contest['start_time']?> &nbsp;|&nbsp; 结束: <?=$contest['end_time']?>
        &nbsp;|&nbsp; 状态: <strong style="color:<?=contest_status_color($status)?>"><?=$status?></strong>
        &nbsp;|&nbsp; 赛制: <strong><?= $isOI ? 'OI（按总分）' : 'ICPC（按通过题数与罚时）' ?></strong>
    </span>
</div>

<?php if (!$rows): ?>
<div class="empty"><div class="icon"><i class="fa-solid fa-trophy"></i></div><p>暂无提交记录</p></div>
<?php else: ?>
<div class="card" style="padding:0"><div class="table-wrap"><table class="rank-table">
<thead><tr>
    <th style="width:50px">#</th>
    <th>用户</th>
    <th style="width:70px">通过</th>
    <th style="width:80px"><?= $isOI ? '总分' : '罚时' ?></th>
    <?php foreach ($problems as $i => $p): ?>
    <th class="prob" style="width:70px" title="<?=htmlspecialchars($p['title'] ?? '')?>"><?=chr(65 + ($i % 26))?></th>
    <?php endforeach; ?>
</tr></thead>
<tbody>
<?php $rank = 1; $me = $_SESSION['airoj_user']['user_id'] ?? null; foreach ($rows as $r): ?>
<tr>
    <td style="font-weight:700;color:<?=$rank<=3?'var(--accent-gold)':'var(--text-muted)'?>"><?=$rank++?></td>
    <td class="<?=$r['user_id']===$me?'me':''?>"><a href="/userinfo.php?uid=<?=urlencode($r['user_id'])?>"><?=user_role_html($r['user_id'], $r['nick'])?></a></td>
    <td style="font-weight:700;color:var(--green)"><?=$r['solved']?></td>
    <td style="font-weight:600;color:var(--accent-gold)"><?= $isOI ? $r['total'] : $r['penalty'] ?></td>
    <?php foreach ($problems as $p):
        $pid = (int)$p['problem_id'];
        $c = $stat[$r['user_id']][$pid] ?? null;
    ?>
    <td class="cell">
        <?php if ($isOI): ?>
            <?php if ($c && $c['score'] > 0): ?><span style="color:var(--green);font-weight:700"><?=$c['score']?></span>
            <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
        <?php else: ?>
            <?php if ($c && $c['solved']): ?>
                <span style="color:var(--green);font-weight:700"><?=intdiv($c['time'], 60)?></span><?php if ($c['wrong']): ?><span style="color:var(--text-muted);font-size:11px">(+<?=$c['wrong']?>)</span><?php endif; ?>
            <?php elseif ($c && $c['wrong']): ?>
                <span style="color:var(--red)">-<?=$c['wrong']?></span>
            <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
        <?php endif; ?>
    </td>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<p style="color:var(--text-muted);font-size:12px;text-align:center">
    <?= $isOI ? 'OI 赛制：每题取最高分求和' : 'ICPC 赛制：数字为首次通过用时（分钟），(+n) 为通过前的错误次数，罚时 = 用时 + 20 × 错误次数' ?>
</p>
<?php endif; ?>

<?php require 'inc/footer.php'; ?>

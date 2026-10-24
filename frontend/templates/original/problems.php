<div class="page-header">
    <h1><i class="fa-solid fa-clipboard-list"></i> 题目列表</h1>
    <form method="get" class="flex" style="margin:0">
        <input type="text" name="q" class="form-input" style="width:280px"
               value="<?=htmlspecialchars($q)?>" placeholder="题号 / 名称 / 标签 / 内容 / 出题人">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> 搜索</button>
        <?php if ($q !== ''): ?><a href="problems.php" class="btn btn-outline btn-sm">重置</a><?php endif; ?>
    </form>
</div>

<div class="card" style="padding:0">
<div class="table-wrap">
<table>
<thead><tr>
    <th style="width:70px">#</th><th>题目名称</th><th style="width:190px">难度</th><th style="width:190px">标签</th>
    <th style="width:120px">出题人</th>
    <th style="width:90px">测试点</th><th style="width:120px">通过 / 提交</th>
    <th style="width:110px">我的状态</th><th style="width:90px">操作</th>
</tr></thead>
<tbody>
<?php if (!$pageItems): ?>
<tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text-muted)"><?= $q !== '' ? '没有匹配的题目' : '暂无题目' ?></td></tr>
<?php else: foreach ($pageItems as $p):
    $tags = $p['tags'] !== '' ? explode(',', $p['tags']) : [];
?>
<tr>
<td style="font-weight:700;color:var(--accent)"><?= $p['id'] ?></td>
<td>
    <a href="problem.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a>
    <?php if ($p['members'] > 1): ?><span class="tag tag-gold"><?= $p['members'] ?> 个难度版本</span><?php endif; ?>
</td>
<td>
    <?php foreach ($p['diffs'] as $d): ?>
    <span class="diff-tag diff-<?= $diffClass[$d['category']] ?? 'basic' ?>"><?= htmlspecialchars(difficulty_label($d['category'], (int)$d['level'], $d['position'])) ?></span>
    <?php endforeach; ?>
</td>
<td>
    <?php foreach ($tags as $tag): ?>
    <a href="problems.php?q=<?= urlencode($tag) ?>" class="tag tag-blue"><?= htmlspecialchars($tag) ?></a>
    <?php endforeach; ?>
</td>
<td><?= $p['author'] !== '' ? user_role_html($p['author']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
<td><?= (int)$p['cases'] ?></td>
<td>
    <span style="color:var(--green);font-weight:600"><?= (int)$p['ac'] ?></span>
    <span style="color:var(--text-muted)"> / <?= (int)$p['total'] ?></span>
</td>
<td><?= $p['solved'] ? '<span class="tag tag-green"><i class="fa-solid fa-circle-check"></i> 已通过</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
<td><a href="problem.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-xs">做题</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>

<?php if ($maxP > 1): ?>
<div class="pagination"><?php for ($i = 1; $i <= $maxP; $i++): ?><a href="?<?= $q !== '' ? 'q=' . urlencode($q) . '&' : '' ?>page=<?=$i?>" class="page-link <?=$i === $page ? 'active' : ''?>"><?=$i?></a><?php endfor; ?></div>
<?php endif; ?>
<div style="text-align:center;color:var(--text-muted);font-size:12px">共 <?= $totalP ?> 道题目</div>

